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
 *   - never uses PHP mail(); always the SendGrid API
 *   - always sends a plain-text part alongside HTML
 *   - real reply-to, verified from-address
 *
 * If the config file is missing or api_key is empty, send() is a logged no-op
 * that returns false — so the site runs fine before a key is provisioned and a
 * mail failure never breaks the caller (e.g. the Stripe webhook).
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

    /** True when a usable SendGrid config is present. */
    public static function isConfigured(): bool
    {
        return self::config() !== null;
    }

    /**
     * Send one transactional email. Returns true on a 2xx from SendGrid.
     * Never throws — logs and returns false on any problem.
     */
    public static function send(string $toEmail, string $toName, string $subject, string $html, string $text): bool
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('[Mailer] skipped: invalid recipient "' . $toEmail . '"');
            return false;
        }

        $cfg = self::config();
        if ($cfg === null) {
            error_log('[Mailer] skipped: SendGrid not configured (no config/sendgrid.php or empty api_key). Subject: ' . $subject);
            return false;
        }

        if ($text === '') {
            $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
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
}
