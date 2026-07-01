<?php
declare(strict_types=1);

/**
 * Mailer — minimal SendGrid transactional sender (no Composer, cURL only),
 * mirroring StripeService's config-from-file pattern.
 *
 * Credentials live in config/sendgrid.php (generated from CI secrets at deploy,
 * gitignored), shaped like:
 *   return [
 *     'api_key'   => 'SG.xxxx',
 *     'from'      => 'tickets@alexmovietheatre.com',  // MUST be a SendGrid-verified/authenticated sender
 *     'from_name' => 'The Alex',
 *     'reply_to'  => 'info@alexmovietheatre.com',
 *   ];
 *
 * Deliverability rules honored (per client-transactional-email):
 *   - prefers the SendGrid API when configured
 *   - always sends a plain-text part alongside HTML
 *   - real reply-to, verified from-address
 *
 * FALLBACK: if config/sendgrid.php is missing or has no api_key, send() falls
 * back to PHP mail() (the host's Exim MTA) using a no-reply@<site-host> from
 * address the server is authorized for. This means receipts work with zero
 * third-party setup; note mail() has weaker deliverability (can land in spam)
 * since the domain isn't DKIM/SPF authenticated — fine for testing, swap in
 * SendGrid for production inbox reliability. A mail failure never throws, so it
 * never breaks the caller (e.g. the Stripe webhook).
 */
final class Mailer
{
    private const ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    /** @return array{api_key:string,from:string,from_name:string,reply_to:string}|null */
    private static function config(): ?array
    {
        $path = __DIR__ . '/../config/sendgrid.php';
        if (!is_file($path)) {
            return null;
        }
        $cfg = require $path;
        if (!is_array($cfg) || empty($cfg['api_key']) || empty($cfg['from'])) {
            return null;
        }
        return [
            'api_key'   => (string)$cfg['api_key'],
            'from'      => (string)$cfg['from'],
            'from_name' => (string)($cfg['from_name'] ?? 'The Alex'),
            'reply_to'  => (string)($cfg['reply_to'] ?? $cfg['from']),
        ];
    }

    /** True when a usable SendGrid config is present. (mail() fallback always works.) */
    public static function isConfigured(): bool
    {
        return self::config() !== null;
    }

    /**
     * Send one transactional email. Prefers SendGrid; falls back to PHP mail().
     * Never throws — logs and returns false on any problem.
     *
     * @param array<int,array{cid:string,bytes:string,filename?:string}> $inlineImages
     *   Images referenced from $html as `<img src="cid:THE_CID">`. Passed as
     *   real CID attachments (not data: URIs) because Gmail and other major
     *   webmail clients strip data: image sources from HTML email.
     */
    public static function send(string $toEmail, string $toName, string $subject, string $html, string $text, array $inlineImages = []): bool
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('[Mailer] skipped: invalid recipient "' . $toEmail . '"');
            return false;
        }

        if ($text === '') {
            $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
        }

        $cfg = self::config();
        if ($cfg === null) {
            // No SendGrid key provisioned — use the host mail server.
            return self::sendViaMail($toEmail, $toName, $subject, $html, $text, $inlineImages);
        }

        $payload = [
            'personalizations' => [[
                'to' => [array_filter(['email' => $toEmail, 'name' => $toName !== '' ? $toName : null])],
            ]],
            'from'     => ['email' => $cfg['from'], 'name' => $cfg['from_name']],
            'reply_to' => ['email' => $cfg['reply_to']],
            'subject'  => $subject,
            'content'  => [
                ['type' => 'text/plain', 'value' => $text],
                ['type' => 'text/html',  'value' => $html],
            ],
        ];

        if ($inlineImages) {
            $payload['attachments'] = array_map(
                static fn($img) => [
                    'content'     => base64_encode($img['bytes']),
                    'filename'    => $img['filename'] ?? ($img['cid'] . '.png'),
                    'type'        => 'image/png',
                    'disposition' => 'inline',
                    'content_id'  => $img['cid'],
                ],
                $inlineImages
            );
        }

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $cfg['api_key'],
                'Content-Type: application/json',
            ],
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            error_log('[Mailer] cURL error: ' . $err);
            return false;
        }
        if ($code < 200 || $code >= 300) {
            error_log('[Mailer] SendGrid HTTP ' . $code . ': ' . (is_string($resp) ? substr($resp, 0, 500) : ''));
            return false;
        }
        return true;
    }

    /**
     * Fallback transport: PHP mail() via the host MTA. Builds a multipart/
     * alternative (plain text + HTML) message, wrapped in multipart/related
     * when $inlineImages is non-empty so `cid:` references in $html resolve.
     * The from address is no-reply@<site host> so the local mail server is
     * authorized to send it (an off-domain from would get rejected/spam-filed).
     * Returns mail()'s boolean. Never throws.
     *
     * @param array<int,array{cid:string,bytes:string,filename?:string}> $inlineImages
     */
    private static function sendViaMail(string $toEmail, string $toName, string $subject, string $html, string $text, array $inlineImages = []): bool
    {
        $host = defined('SITE_URL') ? (parse_url(SITE_URL, PHP_URL_HOST) ?: '') : '';
        if ($host === '') {
            $host = 'parityrfp.com';
        }
        $fromEmail = 'no-reply@' . $host;
        $fromName  = defined('SITE_NAME') ? SITE_NAME : 'The Alex';
        $replyTo   = (defined('SITE_EMAIL') && SITE_EMAIL !== '') ? SITE_EMAIL : $fromEmail;

        // RFC 2047 encode the from name + subject so non-ASCII survives.
        $encName    = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $eol      = "\r\n";
        $altBound = 'alexalt_' . bin2hex(random_bytes(8));

        $altBody  = '--' . $altBound . $eol;
        $altBody .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
        $altBody .= 'Content-Transfer-Encoding: 8bit' . $eol . $eol;
        $altBody .= $text . $eol . $eol;
        $altBody .= '--' . $altBound . $eol;
        $altBody .= 'Content-Type: text/html; charset=UTF-8' . $eol;
        $altBody .= 'Content-Transfer-Encoding: 8bit' . $eol . $eol;
        $altBody .= $html . $eol . $eol;
        $altBody .= '--' . $altBound . '--' . $eol;

        if (!$inlineImages) {
            $headers  = 'From: ' . $encName . ' <' . $fromEmail . '>' . $eol;
            $headers .= 'Reply-To: ' . $replyTo . $eol;
            $headers .= 'MIME-Version: 1.0' . $eol;
            $headers .= 'Content-Type: multipart/alternative; boundary="' . $altBound . '"';
            $body = $altBody;
        } else {
            $relBound = 'alexrel_' . bin2hex(random_bytes(8));
            $headers  = 'From: ' . $encName . ' <' . $fromEmail . '>' . $eol;
            $headers .= 'Reply-To: ' . $replyTo . $eol;
            $headers .= 'MIME-Version: 1.0' . $eol;
            $headers .= 'Content-Type: multipart/related; boundary="' . $relBound . '"';

            $body  = '--' . $relBound . $eol;
            $body .= 'Content-Type: multipart/alternative; boundary="' . $altBound . '"' . $eol . $eol;
            $body .= $altBody . $eol;
            foreach ($inlineImages as $img) {
                $filename = $img['filename'] ?? ($img['cid'] . '.png');
                $body .= '--' . $relBound . $eol;
                $body .= 'Content-Type: image/png; name="' . $filename . '"' . $eol;
                $body .= 'Content-Transfer-Encoding: base64' . $eol;
                $body .= 'Content-ID: <' . $img['cid'] . '>' . $eol;
                $body .= 'Content-Disposition: inline; filename="' . $filename . '"' . $eol . $eol;
                $body .= chunk_split(base64_encode($img['bytes'])) . $eol;
            }
            $body .= '--' . $relBound . '--' . $eol;
        }

        // -f sets the envelope sender (Return-Path) → better deliverability.
        $ok = @mail($toEmail, $encSubject, $body, $headers, '-f' . $fromEmail);
        if (!$ok) {
            error_log('[Mailer] PHP mail() returned false. To: ' . $toEmail . ' Subject: ' . $subject);
        } else {
            error_log('[Mailer] sent via PHP mail() to ' . $toEmail . ' (from ' . $fromEmail . ')');
        }
        return $ok;
    }
}
