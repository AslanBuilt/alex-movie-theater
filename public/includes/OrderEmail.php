<?php
declare(strict_types=1);

/**
 * OrderEmail — builds the order-confirmation receipt (subject + HTML + text)
 * from a transaction row (as returned by TransactionRepo, with ['items']).
 *
 * Table-based HTML with inline styles for client compatibility (Outlook-safe),
 * a plain-text part, and a CAN-SPAM-style footer with the venue's real postal
 * address and contact. No remote images — avoids broken/blocked assets.
 */
final class OrderEmail
{
    /**
     * @param array<string,mixed> $txn
     * @return array{subject:string,html:string,text:string}
     */
    public static function build(array $txn): array
    {
        $ref     = (string)($txn['transaction_ref'] ?? '');
        $name    = trim((string)($txn['customer_name'] ?? ''));
        $total   = (float)($txn['total_amount'] ?? 0);
        $items   = is_array($txn['items'] ?? null) ? $txn['items'] : [];
        $method  = (string)($txn['payment_method'] ?? '');
        $hasTicket = false;

        $brand   = '#8B1D33';
        $ink     = '#1a1a1a';
        $muted   = '#6b7280';
        $line    = '#e5e7eb';

        $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $money = static fn($n): string => '$' . number_format((float)$n, 2);

        // ---- line items ----
        $rowsHtml = '';
        $rowsText = '';
        foreach ($items as $li) {
            if (($li['item_type'] ?? '') === 'ticket') {
                $hasTicket = true;
            }
            $label = (string)($li['item_name'] ?? 'Item');
            $opt   = trim((string)($li['selected_option'] ?? ''));
            if ($opt !== '') {
                $label .= ' (' . $opt . ')';
            }
            $qty = (int)($li['quantity'] ?? 1);
            $sub = (float)($li['subtotal'] ?? 0);

            $rowsHtml .=
                '<tr>'
                . '<td style="padding:10px 0;border-bottom:1px solid ' . $line . ';font-size:15px;color:' . $ink . '">'
                . $e($label) . ' <span style="color:' . $muted . '">&times;' . $qty . '</span></td>'
                . '<td align="right" style="padding:10px 0;border-bottom:1px solid ' . $line . ';font-size:15px;color:' . $ink . ';white-space:nowrap">'
                . $e($money($sub)) . '</td>'
                . '</tr>';
            $rowsText .= '  ' . $label . '  x' . $qty . '   ' . $money($sub) . "\n";
        }

        $ticketNote = $hasTicket
            ? 'Your ticket purchase is confirmed — just show this email (or give your name) at the box office. No printout needed.'
            : 'Your order is confirmed. Please pick up your items at the concession counter.';

        $subject = 'Your ' . SITE_NAME . ' order' . ($ref !== '' ? ' — ' . $ref : '');

        // ---- HTML ----
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid ' . $line . '">'

            // header
            . '<tr><td style="background:' . $brand . ';padding:24px 28px">'
            . '<div style="color:#ffffff;font-size:22px;font-weight:bold;letter-spacing:.5px">' . $e(SITE_NAME) . '</div>'
            . '<div style="color:#f3d9df;font-size:13px;margin-top:2px">Order Confirmation</div>'
            . '</td></tr>'

            // greeting + ref
            . '<tr><td style="padding:28px 28px 8px">'
            . '<p style="margin:0 0 14px;font-size:16px;color:' . $ink . '">'
            . ($name !== '' ? 'Hi ' . $e($name) . ',' : 'Hi there,') . '</p>'
            . '<p style="margin:0 0 6px;font-size:15px;color:' . $muted . '">' . $e($ticketNote) . '</p>'
            . ($ref !== '' ? '<p style="margin:14px 0 0;font-size:14px;color:' . $muted . '">Confirmation: <strong style="color:' . $ink . '">' . $e($ref) . '</strong></p>' : '')
            . '</td></tr>'

            // items
            . '<tr><td style="padding:16px 28px 8px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
            . $rowsHtml
            . '<tr><td style="padding:14px 0 0;font-size:17px;font-weight:bold;color:' . $ink . '">Total</td>'
            . '<td align="right" style="padding:14px 0 0;font-size:17px;font-weight:bold;color:' . $brand . '">' . $e($money($total)) . '</td></tr>'
            . '</table></td></tr>'

            . ($method !== '' ? '<tr><td style="padding:4px 28px 8px;font-size:13px;color:' . $muted . '">Paid by ' . $e(ucfirst($method)) . '</td></tr>' : '')

            // footer
            . '<tr><td style="padding:22px 28px 26px;border-top:1px solid ' . $line . ';margin-top:8px">'
            . '<p style="margin:0 0 4px;font-size:13px;color:' . $muted . '"><strong style="color:' . $ink . '">' . $e(SITE_NAME) . '</strong></p>'
            . '<p style="margin:0;font-size:13px;color:' . $muted . ';line-height:1.5">'
            . $e(SITE_ADDRESS) . '<br>'
            . 'Phone: ' . $e(SITE_PHONE) . ' &middot; ' . $e(SITE_EMAIL)
            . '</p>'
            . '<p style="margin:12px 0 0;font-size:12px;color:#9ca3af">This is an order confirmation for a purchase you made. Questions? Just reply to this email.</p>'
            . '</td></tr>'

            . '</table></td></tr></table></body></html>';

        // ---- plain text ----
        $text = SITE_NAME . " — Order Confirmation\n"
            . ($name !== '' ? "Hi $name,\n" : "Hi there,\n")
            . "\n" . $ticketNote . "\n"
            . ($ref !== '' ? "\nConfirmation: $ref\n" : '')
            . "\nItems:\n" . $rowsText
            . 'Total: ' . $money($total) . "\n"
            . ($method !== '' ? 'Paid by ' . ucfirst($method) . "\n" : '')
            . "\n" . SITE_NAME . "\n" . SITE_ADDRESS . "\n" . SITE_PHONE . ' · ' . SITE_EMAIL . "\n"
            . "Questions? Just reply to this email.\n";

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }
}
