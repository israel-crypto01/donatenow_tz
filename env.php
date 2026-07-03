<?php
// ============================================================
// backend/config/env.php  — Environment Configuration
// Copy to env.local.php for local values; never commit secrets
// ============================================================

// ── App ───────────────────────────────────────────────────────
$LOCAL_ENV = [];
$localEnvFile = __DIR__ . '/env.local.php';
if (is_file($localEnvFile)) {
    $loadedLocalEnv = require $localEnvFile;
    if (is_array($loadedLocalEnv)) {
        $LOCAL_ENV = $loadedLocalEnv;
    }
}

function env_value(string $key, mixed $default): mixed {
    global $LOCAL_ENV;
    return array_key_exists($key, $LOCAL_ENV) ? $LOCAL_ENV[$key] : $default;
}

define('APP_NAME',    'DONATE NOW Tanzania');
define('APP_URL',     env_value('APP_URL', 'http://localhost/donatenow-tz'));      // prod: https://donatenow.co.tz
define('APP_ENV',     'development');                         // development | production
define('APP_DEBUG',   true);                                  // false in production
define('MIN_DONATION', 1000);                                 // minimum TZS
define('PLATFORM_FEE', 0.025);                               // 2.5% transaction fee

// ── Database (PostgreSQL via XAMPP) ──────────────────────────
define('DB_DRIVER', env_value('DB_DRIVER', 'pgsql')); // pgsql | mysql
define('DB_HOST', env_value('DB_HOST', 'localhost'));
define('DB_PORT', env_value('DB_PORT', '5432'));
define('DB_NAME', env_value('DB_NAME', 'donatenow_tz'));
define('DB_USER', env_value('DB_USER', 'postgres'));
define('DB_PASS', env_value('DB_PASS', 'your_postgres_password'));                  // ← change this

// ── Session ───────────────────────────────────────────────────
define('SESSION_NAME',     'DONATENOW_SESSION');
define('SESSION_LIFETIME', 86400);                            // 24 hours
define('SESSION_SECURE',   false);                            // true in production (HTTPS)

// ── Email — PHPMailer + Gmail SMTP ───────────────────────────
define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      587);
define('MAIL_USER',      'noreply@donatenow.co.tz');
define('MAIL_PASS',      'your_gmail_app_password');          // Gmail App Password
define('MAIL_FROM_NAME', 'DONATE NOW Tanzania');
define('MAIL_ENC',       'tls');

// ── SMS — Beem Africa ────────────────────────────────────────
define('BEEM_API_KEY',  'your_beem_api_key');
define('BEEM_SECRET',   'your_beem_secret');
define('BEEM_SENDER',   'DONATENOW');                         // max 11 chars
define('BEEM_URL',      'https://apisms.beem.africa/v1/send');

// ── Google OAuth 2.0 ─────────────────────────────────────────
define('GOOGLE_CLIENT_ID',  'your_google_client_id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SEC', 'your_google_client_secret');
define('GOOGLE_REDIRECT',   APP_URL . '/backend/auth/google.php');

// ── Mongike Payment Gateway ─────────────────────────────────────
define('MONGIKE_API_KEY',   'mk_b6f0be6095a6495878efd3f597903767dfca08940e4505dd');
define('MONGIKE_WEBHOOK',   'https://donatenow.co.tz/backend/payment/webhook.php'); // Must be HTTPS
define('MONGIKE_BASE_URL',  'https://mongike.com/api/v1/payments/mobile-money/tanzania');

// ── CORS ──────────────────────────────────────────────────────
define('CORS_ORIGINS', [
    'http://localhost',
    'http://localhost/donatenow-tz',
    'http://127.0.0.1',
    'http://127.0.0.1/donatenow-tz',
    'https://donatenow.co.tz'
]);

// ── Rate limiting ─────────────────────────────────────────────
define('RATE_LIMIT_LOGIN',  5);    // max attempts
define('RATE_LIMIT_WINDOW', 900);  // 15 minutes in seconds

// ── Timezone ──────────────────────────────────────────────────
date_default_timezone_set('Africa/Dar_es_Salaam');
