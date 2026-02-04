<?php
/**
 * Denthub Dental Clinic - Authentication Functions
 */

require_once __DIR__ . '/database.php';

session_start();

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
        header('Location: /admin/login.php');
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

