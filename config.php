<?php
/**
 * TraceIt configuration.
 * Use environment variables or edit values below for your deployment.
 */
define('TRACEIT_ROOT', __DIR__);
define('DB_PATH', getenv('TRACEIT_DB_PATH') ?: (TRACEIT_ROOT . '/database.db'));
define('LOG_PATH', getenv('TRACEIT_LOG_PATH') ?: (TRACEIT_ROOT . '/logs/app.log'));
define('CSRF_TOKEN_NAME', 'traceit_csrf');
define('OTP_LENGTH', 6);
define('OTP_MAX_ATTEMPTS', 5);
define('OTP_LOCKOUT_SECONDS', 900); // 15 minutes
define('ITEM_TITLE_MAX', 200);
define('ITEM_DESC_MAX', 1000);
define('NAME_MAX', 100);
define('STUDENT_ID_MAX', 50);
define('CONTACT_MAX', 20);
define('PASSWORD_MIN', 8);

// Optional: set a 32+ char secret to encrypt OTPs in the database. Leave empty to store plain (not recommended for production).
define('OTP_ENCRYPTION_KEY', getenv('TRACEIT_OTP_KEY') ?: '');

// Ensure log directory exists
$logDir = dirname(LOG_PATH);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
