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

// --- Login rate limiting (5 fails → captcha, 5 more → 15min lockout) ---
function getLoginClientIdentifier() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $ip . '|' . $ua);
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
        $r['lock_at_count'] = 5;
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

/** Call after a failed login. Returns true if we just triggered lockout. */
function recordFailedLoginAttempt($identifier, $attempt_type = 'unified') {
    try {
        $db = getDB();
        $now = date('Y-m-d H:i:s');
        $lock_minutes = defined('LOGIN_LOCKOUT_MINUTES') ? (int)LOGIN_LOCKOUT_MINUTES : 15;
        $max_attempts = defined('LOGIN_MAX_ATTEMPTS') ? (int)LOGIN_MAX_ATTEMPTS : 5;
        $stmt = $db->prepare("INSERT INTO login_attempts (identifier, attempt_type, failed_count, last_attempt_at) VALUES (?, ?, 1, ?)
                              ON CONFLICT (identifier, attempt_type) DO UPDATE SET
                              failed_count = login_attempts.failed_count + 1,
                              last_attempt_at = EXCLUDED.last_attempt_at");
        $stmt->bind_param("sss", $identifier, $attempt_type, $now);
        $stmt->execute();
        $r = getLoginAttemptRecord($identifier, $attempt_type);
        $failed = (int)($r['failed_count'] ?? 0);
        $captcha_passed = !empty($r['captcha_passed_at']);
        $lock_threshold = isset($r['lock_at_count']) ? (int)$r['lock_at_count'] : 5;
        if ($captcha_passed && $failed >= $lock_threshold) {
            $locked_until = date('Y-m-d H:i:s', time() + 60 * $lock_minutes);
            $upd = $db->prepare("UPDATE login_attempts SET locked_until = ?, failed_count = 0, captcha_passed_at = NULL WHERE identifier = ? AND attempt_type = ?");
            $upd->bind_param("sss", $locked_until, $identifier, $attempt_type);
            $upd->execute();
            return true;
        }
    } catch (Throwable $e) {
        error_log('recordFailedLoginAttempt: ' . $e->getMessage());
    }
    return false;
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

/** True if we need to show/solve captcha before next attempt (5 failures, no recent captcha). */
function loginNeedsCaptcha($identifier, $attempt_type = 'unified') {
    try {
        $r = getLoginAttemptRecord($identifier, $attempt_type);
        if (!$r) return false;
        $max = defined('LOGIN_MAX_ATTEMPTS') ? (int)LOGIN_MAX_ATTEMPTS : 5;
        if ((int)$r['failed_count'] < $max) return false;
        if (!empty($r['captcha_passed_at'])) return false;
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Mark that captcha was passed. When $reset_count is true (default), failed_count is set to 0 (user gets 5 more attempts).
 * When $reset_count is false (e.g. no captcha key), we only set captcha_passed_at so the next 5 failures will trigger lockout.
 */
function recordLoginCaptchaPassed($identifier, $attempt_type = 'unified', $reset_count = true) {
    try {
        $db = getDB();
        $now = date('Y-m-d H:i:s');
        if ($reset_count) {
            $stmt = $db->prepare("INSERT INTO login_attempts (identifier, attempt_type, failed_count, captcha_passed_at, last_attempt_at) VALUES (?, ?, 0, ?, ?)
                                  ON CONFLICT (identifier, attempt_type) DO UPDATE SET
                                  failed_count = 0, captcha_passed_at = EXCLUDED.captcha_passed_at, last_attempt_at = EXCLUDED.last_attempt_at");
            $stmt->bind_param("ssss", $identifier, $attempt_type, $now, $now);
            $stmt->execute();
            if ($stmt = @$db->prepare("UPDATE login_attempts SET lock_at_count = 5 WHERE identifier = ? AND attempt_type = ?")) {
                $stmt->bind_param("ss", $identifier, $attempt_type);
                @$stmt->execute();
            }
        } else {
            $stmt = $db->prepare("INSERT INTO login_attempts (identifier, attempt_type, failed_count, captcha_passed_at, last_attempt_at) VALUES (?, ?, 5, ?, ?)
                                  ON CONFLICT (identifier, attempt_type) DO UPDATE SET
                                  captcha_passed_at = EXCLUDED.captcha_passed_at, last_attempt_at = EXCLUDED.last_attempt_at");
            $stmt->bind_param("ssss", $identifier, $attempt_type, $now, $now);
            $stmt->execute();
            $upd = @$db->prepare("UPDATE login_attempts SET lock_at_count = 10 WHERE identifier = ? AND attempt_type = ?");
            if ($upd) {
                $upd->bind_param("ss", $identifier, $attempt_type);
                @$upd->execute();
            }
        }
        return true;
    } catch (Throwable $e) {
        error_log('recordLoginCaptchaPassed: ' . $e->getMessage());
        return false;
    }
}

function verifyRecaptchaV3($token, $action = 'login') {
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
    return !empty($json['success']) && (float)($json['score'] ?? 0) >= 0.5;
}

