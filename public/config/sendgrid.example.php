<?php
declare(strict_types=1);

/**
 * SendGrid credentials for transactional email (order confirmations).
 *
 * The real config/sendgrid.php is generated at deploy from GitHub secrets
 * (SENDGRID_API_KEY / MAIL_FROM / MAIL_FROM_NAME / MAIL_REPLY_TO) and is
 * gitignored. Copy this file to sendgrid.php for local testing only.
 *
 * IMPORTANT: 'from' must be a SendGrid-verified single sender OR (preferred) an
 * address on a SendGrid-authenticated domain — otherwise SendGrid rejects the
 * send and the mail fails DMARC. See the client-transactional-email skill.
 */
return [
    'api_key'   => 'SG.xxxxxxxxxxxxxxxxxxxxxx',
    'from'      => 'tickets@alexmovietheatre.com',
    'from_name' => 'The Alex',
    'reply_to'  => 'info@alexmovietheatre.com',
];
