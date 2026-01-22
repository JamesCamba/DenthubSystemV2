<?php
/**
 * Denthub Dental Clinic - Configuration File
 * Database and System Configuration
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'denthub_clinic');

// Application Configuration
define('APP_NAME', 'Denthub Dental Clinic');
define('APP_URL', 'http://localhost/denthub');
define('TIMEZONE', 'Asia/Manila');

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour

// Security
define('PASSWORD_MIN_LENGTH', 8);

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

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

