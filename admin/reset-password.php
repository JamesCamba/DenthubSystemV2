<?php
/**
 * Denthub Dental Clinic - Admin Password Reset Script
 * 
 * WARNING: This script should be deleted after use for security!
 * This script resets the admin password to a known value.
 */

require_once '../includes/config.php';
require_once '../includes/database.php';

// Set the new password here
$new_password = 'admin1234'; // Change this to your desired password
$admin_email = 'admin@denthub.com';

$db = getDB();

// Find admin user by email
$stmt = $db->prepare("SELECT user_id, username, email FROM users WHERE email = ? AND role = 'admin'");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Admin user with email '$admin_email' not found!");
}

$admin = $result->fetch_assoc();

// Hash the new password
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Update the password
$updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
$updateStmt->bind_param("si", $password_hash, $admin['user_id']);

if ($updateStmt->execute()) {
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Password Reset - Success</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css'>
</head>
<body class='bg-light'>
    <div class='container py-5'>
        <div class='row justify-content-center'>
            <div class='col-md-6'>
                <div class='card shadow'>
                    <div class='card-body p-5'>
                        <div class='text-center mb-4'>
                            <i class='bi bi-check-circle text-success' style='font-size: 64px;'></i>
                            <h2 class='mt-3 text-success'>Password Reset Successful!</h2>
                        </div>
                        <div class='alert alert-info'>
                            <h5>Admin Login Credentials:</h5>
                            <p class='mb-1'><strong>Email/Username:</strong> {$admin['email']}</p>
                            <p class='mb-1'><strong>Username:</strong> {$admin['username']}</p>
                            <p class='mb-0'><strong>New Password:</strong> <code>{$new_password}</code></p>
                        </div>
                        <div class='alert alert-warning'>
                            <strong>⚠️ Security Warning:</strong><br>
                            Please delete this file (<code>admin/reset-password.php</code>) immediately after logging in!
                        </div>
                        <div class='d-grid'>
                            <a href='login.php' class='btn btn-primary btn-lg'>Go to Admin Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>";
} else {
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Password Reset - Error</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css'>
</head>
<body class='bg-light'>
    <div class='container py-5'>
        <div class='row justify-content-center'>
            <div class='col-md-6'>
                <div class='card shadow'>
                    <div class='card-body p-5'>
                        <div class='text-center mb-4'>
                            <i class='bi bi-x-circle text-danger' style='font-size: 64px;'></i>
                            <h2 class='mt-3 text-danger'>Password Reset Failed!</h2>
                        </div>
                        <div class='alert alert-danger'>
                            <p>Error updating password. Please check the database connection and try again.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>";
}

$updateStmt->close();
$stmt->close();
$db->close();
?>
