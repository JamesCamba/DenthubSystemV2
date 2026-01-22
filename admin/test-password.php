<?php
/**
 * Test script to verify password hash
 * DELETE THIS FILE AFTER USE!
 */

require_once '../includes/config.php';
require_once '../includes/database.php';

$db = getDB();
$admin_email = 'admin@denthub.com';
$test_password = 'admin1234';

// Get admin user
$stmt = $db->prepare("SELECT user_id, username, email, password_hash FROM users WHERE email = ? AND role = 'admin'");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Admin user not found!");
}

$admin = $result->fetch_assoc();

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Password Test</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
    <div class='container py-5'>
        <div class='row justify-content-center'>
            <div class='col-md-8'>
                <div class='card shadow'>
                    <div class='card-body p-5'>
                        <h2>Password Verification Test</h2>
                        <hr>
                        <p><strong>Username:</strong> {$admin['username']}</p>
                        <p><strong>Email:</strong> {$admin['email']}</p>
                        <p><strong>Password Hash:</strong> <code style='font-size: 10px;'>{$admin['password_hash']}</code></p>
                        <hr>
                        <h4>Testing password: <code>{$test_password}</code></h4>";

$verify_result = password_verify($test_password, $admin['password_hash']);

if ($verify_result) {
    echo "<div class='alert alert-success'>
            <h5>✅ Password verification SUCCESSFUL!</h5>
            <p>The password '{$test_password}' matches the hash in the database.</p>
            <p><strong>You can login with:</strong></p>
            <ul>
                <li>Username: <code>{$admin['username']}</code></li>
                <li>Email: <code>{$admin['email']}</code></li>
                <li>Password: <code>{$test_password}</code></li>
            </ul>
          </div>";
} else {
    echo "<div class='alert alert-danger'>
            <h5>❌ Password verification FAILED!</h5>
            <p>The password '{$test_password}' does NOT match the hash in the database.</p>
            <p><strong>Action needed:</strong> Run the reset-password.php script again to update the password.</p>
          </div>";
}

echo "        <hr>
                        <div class='d-grid gap-2'>
                            <a href='login.php' class='btn btn-primary'>Go to Login</a>
                            <a href='reset-password.php' class='btn btn-secondary'>Reset Password Again</a>
                        </div>
                        <div class='alert alert-warning mt-3'>
                            <strong>⚠️ Security:</strong> Delete this file after testing!
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>";

$stmt->close();
$db->close();
?>
