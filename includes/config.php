<?php
/**
 * Denthub Dental Clinic - Configuration File
 * Database and System Configuration
 */

// Database Configuration (PostgreSQL)
// Supports both environment variables (Railway) and direct connection string
// Default to Neon Denthub v2 database when DATABASE_URL is not set
$neon_connection_string = getenv('DATABASE_URL') ?: 'postgresql://neondb_owner:npg_1MeBTYFx9XPN@ep-young-dawn-a1pvepi1-pooler.ap-southeast-1.aws.neon.tech/denthub_clinicv2?sslmode=require&channel_binding=require';

// Parse connection string
$parsed = parse_url($neon_connection_string);

// Extract SSL mode from query string if present
$sslmode = 'require';
if (isset($parsed['query'])) {
    parse_str($parsed['query'], $query_params);
    if (isset($query_params['sslmode'])) {
        $sslmode = $query_params['sslmode'];
    }
}

// Database Configuration (PostgreSQL)
// Use environment variables if available (Railway), otherwise use parsed connection string
define('DB_HOST', getenv('DB_HOST') ?: (isset($parsed['host']) ? $parsed['host'] : 'localhost'));
define('DB_USER', getenv('DB_USER') ?: (isset($parsed['user']) ? $parsed['user'] : 'postgres'));
define('DB_PASS', getenv('DB_PASS') ?: (isset($parsed['pass']) ? $parsed['pass'] : ''));
define('DB_NAME', getenv('DB_NAME') ?: (isset($parsed['path']) ? ltrim($parsed['path'], '/') : 'denthub_clinic'));
define('DB_PORT', getenv('DB_PORT') ?: (isset($parsed['port']) ? $parsed['port'] : 5432));
define('DB_SSLMODE', getenv('DB_SSLMODE') ?: $sslmode);
define('DB_TYPE', 'postgresql');

// Application Configuration
define('APP_NAME', 'Denthub Dental Clinic');
// Use Railway's PORT and RAILWAY_PUBLIC_DOMAIN if available, otherwise use localhost
$app_url = getenv('RAILWAY_PUBLIC_DOMAIN') 
    ? 'https://' . getenv('RAILWAY_PUBLIC_DOMAIN') 
    : (getenv('APP_URL') ?: 'http://localhost/denthub');
define('APP_URL', $app_url);
define('TIMEZONE', 'Asia/Manila');

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour

// Security
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '');

// Date/Time Format
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'F d, Y');
define('DISPLAY_TIME_FORMAT', 'g:i A');

// Pagination
define('ITEMS_PER_PAGE', 10);

// File Upload
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('UPLOAD_DIR', 'uploads/');

// Composer autoload (for external libraries like Metaphone 3, etc.)
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error Reporting (Set to 0 in production to hide errors from users)
$display_errors = (getenv('APP_DEBUG') === '1' || getenv('APP_DEBUG') === 'true');
error_reporting(E_ALL);
ini_set('display_errors', $display_errors ? '1' : '0');
ini_set('log_errors', '1');

// Friendly global exception handler: do not expose database or backend details to users
set_exception_handler(function (Throwable $e) {
    error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_log($e->getTraceAsString());
    if (getenv('APP_DEBUG') === '1' || getenv('APP_DEBUG') === 'true') {
        throw $e;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('HTTP/1.1 500 Internal Server Error');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body>';
    echo '<h1>Something went wrong</h1>';
    echo '<p>We\'re sorry. Please try again later or contact support.</p>';
    echo '</body></html>';
    exit(1);
});

