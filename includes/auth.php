<?php
/**
 * Denthub Dental Clinic - Authentication Functions
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_set_cookie_params([
        'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
if (isset($_SESSION['user_id']) || isset($_SESSION['patient_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600))) {
        session_unset();
        session_destroy();
    } else {
        $_SESSION['last_activity'] = time();
    }
}

// Check if user is logged in (staff/dentist/admin)
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Check if patient is logged in
function isPatientLoggedIn() {
    return isset($_SESSION['patient_id']);
}

// Require login for staff
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

// Require login for patients
function requirePatientLogin() {
    if (!isPatientLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

// Check user role
function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

// Require specific role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: /admin/dashboard.php');
        exit;
    }
}

// Login function for staff (accepts username or email)
function login($username_or_email, $password) {
    $db = getDB();
    // Try to find user by username or email
    // PostgreSQL: use boolean comparison with TRUE instead of integer 1
    $stmt = $db->prepare("SELECT user_id, username, email, password_hash, full_name, role, branch_id, must_change_password FROM users WHERE (username = ? OR email = ?) AND is_active = TRUE");
    $stmt->bind_param("ss", $username_or_email, $username_or_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['branch_id'] = $user['branch_id'];
            $_SESSION['must_change_password'] = !empty($user['must_change_password']);
            logActivity('login_success', 'Staff login', $user['user_id'], null, $user['username'], $user['full_name'], $user['role']);
            return true;
        }
    }
    return false;
}

// Login function for patients (accepts email)
function patientLogin($email, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT pa.account_id, pa.patient_id, pa.email, pa.password_hash, p.first_name, p.last_name 
                          FROM patient_accounts pa 
                          JOIN patients p ON pa.patient_id = p.patient_id 
                          WHERE pa.email = ? AND pa.is_verified = TRUE");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $account = $result->fetch_assoc();
        if (password_verify($password, $account['password_hash'])) {
            $_SESSION['patient_id'] = $account['patient_id'];
            $_SESSION['patient_email'] = $account['email'];
            $_SESSION['patient_name'] = $account['last_name'] . ', ' . $account['first_name'];
            
            // Update last login
            $updateStmt = $db->prepare("UPDATE patient_accounts SET last_login = NOW() WHERE account_id = ?");
            $updateStmt->bind_param("i", $account['account_id']);
            $updateStmt->execute();
            
            logActivity('login_success', 'Patient login', null, $account['patient_id'], null, $account['first_name'] . ' ' . $account['last_name'], 'Patient');
            return true;
        }
    }
    return false;
}

// Logout function
function logout() {
    session_unset();
    session_destroy();
}

// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get current patient info
function getCurrentPatient() {
    if (!isPatientLoggedIn()) return null;
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $_SESSION['patient_id']);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// --- Login rate limiting helpers ---
function getLoginClientIdentifier() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $ip . '|' . $ua);
}

function getClientIpAddress() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return substr($ip, 0, 45);
}

/**
 * IP-based login rate limiting.
 *
 * Rules:
 * - 10 failed logins from same IP in 15 minutes -> block for 1 hour
 * - 50 failed logins from same IP in 1 hour     -> block for 24 hours
 */
function isIpLoginBlocked($ip) {
    try {
        $db = getDB();
        // Table is created lazily if needed
        $db->query("
            CREATE TABLE IF NOT EXISTS ip_login_limits (
                ip_address VARCHAR(45) PRIMARY KEY,
                failed_15m INTEGER DEFAULT 0,
                window_start_15m TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                failed_1h INTEGER DEFAULT 0,
                window_start_1h TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                locked_until TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $stmt = $db->prepare("SELECT locked_until FROM ip_login_limits WHERE ip_address = ?");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row || empty($row['locked_until'])) {
            return false;
        }
        return strtotime($row['locked_until']) > time();
    } catch (Throwable $e) {
        error_log('isIpLoginBlocked: ' . $e->getMessage());
        return false;
    }
}

function recordFailedLoginIp($ip, $lock_minutes = 0) {
    try {
        $db = getDB();
        $db->query("
            CREATE TABLE IF NOT EXISTS ip_login_limits (
                ip_address VARCHAR(45) PRIMARY KEY,
                failed_15m INTEGER DEFAULT 0,
                window_start_15m TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                failed_1h INTEGER DEFAULT 0,
                window_start_1h TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                locked_until TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("SELECT locked_until FROM ip_login_limits WHERE ip_address = ?");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        $locked_until = null;
        if ($lock_minutes > 0) {
            $locked_until = date('Y-m-d H:i:s', time() + 60 * $lock_minutes);
        } else {
            $locked_until = $row['locked_until'] ?? null;
        }

        if ($row) {
            $upd = $db->prepare("
                UPDATE ip_login_limits
                SET locked_until = ?, updated_at = ?
                WHERE ip_address = ?
            ");
            $upd->bind_param("sss", $locked_until, $now, $ip);
            $upd->execute();
        } else {
            $ins = $db->prepare("
                INSERT INTO ip_login_limits (ip_address, failed_15m, window_start_15m, failed_1h, window_start_1h, locked_until, created_at, updated_at)
                VALUES (?, 0, ?, 0, ?, ?, ?, ?)
            ");
            $ins->bind_param("sssssss", $ip, $now, $now, $locked_until, $now, $now);
            $ins->execute();
        }
    } catch (Throwable $e) {
        error_log('recordFailedLoginIp: ' . $e->getMessage());
    }
}

/** @return array|null { failed_count, captcha_passed_at, locked_until, lock_at_count } */
function getLoginAttemptRecord($identifier, $attempt_type = 'unified') {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT failed_count, captcha_passed_at, locked_until FROM login_attempts WHERE identifier = ? AND attempt_type = ?");
        $stmt->bind_param("ss", $identifier, $attempt_type);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        if (!$r) return null;
        // How many failed attempts AFTER a CAPTCHA pass before lockout.
        // We default to 3 per policy.
        $r['lock_at_count'] = 3;
        try {
            $stmt2 = $db->prepare("SELECT lock_at_count FROM login_attempts WHERE identifier = ? AND attempt_type = ?");
            $stmt2->bind_param("ss", $identifier, $attempt_type);
            $stmt2->execute();
            $row = $stmt2->get_result()->fetch_assoc();
            if ($row !== null && array_key_exists('lock_at_count', $row)) {
                $r['lock_at_count'] = (int)$row['lock_at_count'];
            }
        } catch (Throwable $e) {
            // Column may not exist yet
        }
        return $r;
    } catch (Throwable $e) {
        error_log('getLoginAttemptRecord: ' . $e->getMessage());
        return null;
    }
}

function isLoginLocked($identifier, $attempt_type = 'unified') {
    try {
        $r = getLoginAttemptRecord($identifier, $attempt_type);
        if (!$r || empty($r['locked_until'])) return false;
        return strtotime($r['locked_until']) > time();
    } catch (Throwable $e) {
        return false;
    }
}

function getLoginLockedUntil($identifier, $attempt_type = 'unified') {
    try {
        $r = getLoginAttemptRecord($identifier, $attempt_type);
        return ($r && !empty($r['locked_until'])) ? strtotime($r['locked_until']) : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Call after a failed login.
 * Returns an array: ['locked' => bool, 'lock_minutes' => int].
 *
 * Lockout policy (per identifier/account):
 * - First 5 failed attempts: require CAPTCHA, no lock.
 * - 6–10 failed attempts (2nd set of 5): lock for 15 minutes.
 * - 11–15 failed attempts (3rd set of 5): lock for 30 minutes.
 * - 16–20 failed attempts (4th set of 5): lock for 60 minutes.
 * Counts reset when a login succeeds (via clearLoginAttempts).
 */
function recordFailedLoginAttempt($identifier, $attempt_type = 'unified') {
    try {
        $db = getDB();
        $now = date('Y-m-d H:i:s');
        // If already locked, respect existing lockout and do not shorten it here.
        if (isLoginLocked($identifier, $attempt_type)) {
            $lockedUntilTs = getLoginLockedUntil($identifier, $attempt_type);
            $remainingMinutes = $lockedUntilTs > time() ? (int)ceil(($lockedUntilTs - time()) / 60) : 0;
            return ['locked' => true, 'lock_minutes' => max($remainingMinutes, 0)];
        }

        $stmt = $db->prepare("INSERT INTO login_attempts (identifier, attempt_type, failed_count, last_attempt_at) VALUES (?, ?, 1, ?)
                              ON CONFLICT (identifier, attempt_type) DO UPDATE SET
                              failed_count = login_attempts.failed_count + 1,
                              last_attempt_at = EXCLUDED.last_attempt_at");
        $stmt->bind_param("sss", $identifier, $attempt_type, $now);
        $stmt->execute();
        $r = getLoginAttemptRecord($identifier, $attempt_type);
        $failed = (int)($r['failed_count'] ?? 0);
        // Determine lock duration based on total failed attempts.
        $lock_minutes = 0;
        if ($failed >= 20) {
            $lock_minutes = 60;
        } elseif ($failed >= 15) {
            $lock_minutes = 30;
        } elseif ($failed >= 10) {
            $lock_minutes = 15;
        } else {
            $lock_minutes = 0;
        }

        if ($lock_minutes > 0) {
            $locked_until = date('Y-m-d H:i:s', time() + 60 * $lock_minutes);
            $upd = $db->prepare("UPDATE login_attempts SET locked_until = ?, captcha_passed_at = NULL WHERE identifier = ? AND attempt_type = ?");
            $upd->bind_param("sss", $locked_until, $identifier, $attempt_type);
            $upd->execute();
            // Track lockouts in a 24h window.
            recordIdentifierLockout($identifier, $attempt_type);
            return ['locked' => true, 'lock_minutes' => $lock_minutes];
        }
    } catch (Throwable $e) {
        error_log('recordFailedLoginAttempt: ' . $e->getMessage());
    }
    return ['locked' => false, 'lock_minutes' => 0];
}

function clearLoginAttempts($identifier, $attempt_type = 'unified') {
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE identifier = ? AND attempt_type = ?");
        $stmt->bind_param("ss", $identifier, $attempt_type);
        $stmt->execute();
    } catch (Throwable $e) {
        // ignore
    }
}

/** True if we need to show/solve captcha before next attempt (>=5 failures). */
function loginNeedsCaptcha($identifier, $attempt_type = 'unified') {
    try {
        $r = getLoginAttemptRecord($identifier, $attempt_type);
        if (!$r) return false;
        // First 5 failed attempts trigger CAPTCHA; continue requiring while failed_count >= 5.
        if ((int)$r['failed_count'] < 5) return false;
        return !isLoginLocked($identifier, $attempt_type);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Mark that captcha was passed. When $reset_count is true (default), failed_count is set to 0 (user gets a fresh set of attempts).
 */
function recordLoginCaptchaPassed($identifier, $attempt_type = 'unified', $reset_count = true) {
    try {
        $db = getDB();
        $now = date('Y-m-d H:i:s');
        // For the new policy we no longer reset failed_count on CAPTCHA pass;
        // we only record that CAPTCHA was solved recently.
        $stmt = $db->prepare("INSERT INTO login_attempts (identifier, attempt_type, failed_count, captcha_passed_at, last_attempt_at) VALUES (?, ?, 0, ?, ?)
                              ON CONFLICT (identifier, attempt_type) DO UPDATE SET
                              captcha_passed_at = EXCLUDED.captcha_passed_at,
                              last_attempt_at = EXCLUDED.last_attempt_at");
        $stmt->bind_param("ssss", $identifier, $attempt_type, $now, $now);
        $stmt->execute();
        return true;
    } catch (Throwable $e) {
        error_log('recordLoginCaptchaPassed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Track account/device lockouts in a 24h rolling window.
 * Policy: 3 lockouts within 24 hours -> lock for 24 hours.
 */
function recordIdentifierLockout($identifier, $attempt_type = 'unified') {
    try {
        $db = getDB();
        $db->query("
            CREATE TABLE IF NOT EXISTS identifier_lockout_limits (
                identifier VARCHAR(64) NOT NULL,
                attempt_type VARCHAR(20) NOT NULL,
                lockouts_count INTEGER DEFAULT 0,
                window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (identifier, attempt_type)
            )
        ");

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("SELECT lockouts_count, window_start FROM identifier_lockout_limits WHERE identifier = ? AND attempt_type = ?");
        $stmt->bind_param("ss", $identifier, $attempt_type);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        $count = 0;
        $ws = $now;
        if ($row) {
            $ws = $row['window_start'];
            if (strtotime($ws) < time() - 24 * 60 * 60) {
                $count = 0;
                $ws = $now;
            } else {
                $count = (int)$row['lockouts_count'];
            }
        }
        $count++;

        if ($row) {
            $upd = $db->prepare("UPDATE identifier_lockout_limits SET lockouts_count = ?, window_start = ?, updated_at = ? WHERE identifier = ? AND attempt_type = ?");
            $upd->bind_param("issss", $count, $ws, $now, $identifier, $attempt_type);
            $upd->execute();
        } else {
            $ins = $db->prepare("INSERT INTO identifier_lockout_limits (identifier, attempt_type, lockouts_count, window_start, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->bind_param("ssisss", $identifier, $attempt_type, $count, $ws, $now, $now);
            $ins->execute();
        }

        // If 3 lockouts reached within 24h, enforce 24h lock at login_attempts level.
        if ($count >= 3) {
            $locked_until = date('Y-m-d H:i:s', time() + 24 * 60 * 60);
            $upd2 = $db->prepare("UPDATE login_attempts SET locked_until = ?, failed_count = 0, captcha_passed_at = NULL WHERE identifier = ? AND attempt_type = ?");
            $upd2->bind_param("sss", $locked_until, $identifier, $attempt_type);
            $upd2->execute();
        }
    } catch (Throwable $e) {
        error_log('recordIdentifierLockout: ' . $e->getMessage());
    }
}

/**
 * Device CAPTCHA failure tracking.
 * Policy: repeated CAPTCHA failures after login attempts -> lock device for 24 hours.
 */
function recordCaptchaFailureAndMaybeLock($identifier, $attempt_type = 'unified') {
    try {
        $db = getDB();
        $db->query("
            CREATE TABLE IF NOT EXISTS captcha_fail_limits (
                identifier VARCHAR(64) NOT NULL,
                attempt_type VARCHAR(20) NOT NULL,
                failed_count INTEGER DEFAULT 0,
                window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                locked_until TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (identifier, attempt_type)
            )
        ");

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("SELECT failed_count, window_start, locked_until FROM captcha_fail_limits WHERE identifier = ? AND attempt_type = ?");
        $stmt->bind_param("ss", $identifier, $attempt_type);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && !empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
            // Already locked
            return true;
        }

        $count = 0;
        $ws = $now;
        if ($row) {
            $ws = $row['window_start'];
            // Reset window after 15 minutes without failures
            if (strtotime($ws) < time() - 15 * 60) {
                $count = 0;
                $ws = $now;
            } else {
                $count = (int)$row['failed_count'];
            }
        }
        $count++;

        $locked_until = null;
        if ($count >= 3) {
            $locked_until = date('Y-m-d H:i:s', time() + 24 * 60 * 60);
            // Lock device at login_attempts too so isLoginLocked() catches it.
            $upd = $db->prepare("
                INSERT INTO login_attempts (identifier, attempt_type, failed_count, locked_until, last_attempt_at)
                VALUES (?, ?, 0, ?, ?)
                ON CONFLICT (identifier, attempt_type) DO UPDATE SET
                    locked_until = EXCLUDED.locked_until,
                    failed_count = 0,
                    captcha_passed_at = NULL,
                    last_attempt_at = EXCLUDED.last_attempt_at
            ");
            $upd->bind_param("ssss", $identifier, $attempt_type, $locked_until, $now);
            $upd->execute();
        }

        if ($row) {
            $upd2 = $db->prepare("UPDATE captcha_fail_limits SET failed_count = ?, window_start = ?, locked_until = ?, updated_at = ? WHERE identifier = ? AND attempt_type = ?");
            $upd2->bind_param("isssss", $count, $ws, $locked_until, $now, $identifier, $attempt_type);
            $upd2->execute();
        } else {
            $ins = $db->prepare("INSERT INTO captcha_fail_limits (identifier, attempt_type, failed_count, window_start, locked_until, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("ssissss", $identifier, $attempt_type, $count, $ws, $locked_until, $now, $now);
            $ins->execute();
        }

        return $locked_until !== null;
    } catch (Throwable $e) {
        error_log('recordCaptchaFailureAndMaybeLock: ' . $e->getMessage());
        return false;
    }
}

function clearCaptchaFailures($identifier, $attempt_type = 'unified') {
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM captcha_fail_limits WHERE identifier = ? AND attempt_type = ?");
        $stmt->bind_param("ss", $identifier, $attempt_type);
        $stmt->execute();
    } catch (Throwable $e) {
        // ignore
    }
}

/**
 * Registration IP limits.
 * Policy: 3 registrations per IP per day -> block for 24 hours.
 */
function isIpRegistrationBlocked($ip) {
    try {
        $db = getDB();
        $db->query("
            CREATE TABLE IF NOT EXISTS ip_registration_limits (
                ip_address VARCHAR(45) PRIMARY KEY,
                day_date DATE DEFAULT CURRENT_DATE,
                reg_count INTEGER DEFAULT 0,
                locked_until TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $stmt = $db->prepare("SELECT locked_until FROM ip_registration_limits WHERE ip_address = ?");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row || empty($row['locked_until'])) return false;
        return strtotime($row['locked_until']) > time();
    } catch (Throwable $e) {
        error_log('isIpRegistrationBlocked: ' . $e->getMessage());
        return false;
    }
}

function recordRegistrationForIp($ip) {
    try {
        $db = getDB();
        $db->query("
            CREATE TABLE IF NOT EXISTS ip_registration_limits (
                ip_address VARCHAR(45) PRIMARY KEY,
                day_date DATE DEFAULT CURRENT_DATE,
                reg_count INTEGER DEFAULT 0,
                locked_until TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("SELECT day_date, reg_count, locked_until FROM ip_registration_limits WHERE ip_address = ?");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && !empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
            return false;
        }

        $today = date('Y-m-d');
        $count = 0;
        $day_date = $today;

        if ($row) {
            $day_date = $row['day_date'] ?: $today;
            if ($day_date !== $today) {
                $count = 0;
                $day_date = $today;
            } else {
                $count = (int)$row['reg_count'];
            }
        }

        $count++;
        $locked_until = null;
        if ($count > 3) {
            $locked_until = date('Y-m-d H:i:s', time() + 24 * 60 * 60);
        }

        if ($row) {
            $upd = $db->prepare("UPDATE ip_registration_limits SET day_date = ?, reg_count = ?, locked_until = ?, updated_at = ? WHERE ip_address = ?");
            $upd->bind_param("sisss", $day_date, $count, $locked_until, $now, $ip);
            $upd->execute();
        } else {
            $ins = $db->prepare("INSERT INTO ip_registration_limits (ip_address, day_date, reg_count, locked_until, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->bind_param("ssisss", $ip, $day_date, $count, $locked_until, $now, $now);
            $ins->execute();
        }

        return $locked_until === null;
    } catch (Throwable $e) {
        error_log('recordRegistrationForIp: ' . $e->getMessage());
        return true;
    }
}

/**
 * Verify reCAPTCHA v2 (checkbox/invisible) response.
 */
function verifyRecaptchaV2($token) {
    $secret = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';
    if ($secret === '') return true;
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = ['secret' => $secret, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''];
    $opts = ['http' => [
        'method' => 'POST',
        'header' => 'Content-type: application/x-www-form-urlencoded',
        'content' => http_build_query($data)
    ]];
    $ctx = stream_context_create($opts);
    $result = @file_get_contents($url, false, $ctx);
    if ($result === false) return false;
    $json = json_decode($result, true);
    return !empty($json['success']);
}

