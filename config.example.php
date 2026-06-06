<?php
// ── SMTP Configuration Example ──
// Copy this file to config.php and fill in your real credentials.
// config.php is excluded from Git - never commit real credentials.

define('SMTP_HOST',     'smtp.your-provider.com');
define('SMTP_PORT',     465);
define('SMTP_USER',     'your-email@yourdomain.com');
define('SMTP_PASS',     'your-smtp-password');
define('SMTP_FROM',     'your-email@yourdomain.com');
define('SMTP_FROM_NAME','Your Site Name');
define('SMTP_BCC',      'your-email@yourdomain.com');
define('SMTP_REPLY_TO', 'no-reply@yourdomain.com');
