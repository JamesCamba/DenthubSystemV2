<?php
/**
 * Denthub Dental Clinic - Force Password Change (First Login)
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$db = getDB();
$error = '';
$success = '';

// If flag is not set, go back to dashboard
if (empty($_SESSION['must_change_password'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $error = 'Please enter and confirm your new password.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $user_id = (int)$_SESSION['user_id'];

        $stmt = $db->prepare("UPDATE users SET password_hash = ?, must_change_password = FALSE, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $password_hash, $user_id);

        if ($stmt->execute()) {
            $_SESSION['must_change_password'] = false;
            $success = 'Password updated successfully. You can now access the dashboard.';
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Error updating password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Temporary Password - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-key-fill text-primary" style="font-size: 48px;"></i>
                            <h2 class="mt-3">Change Temporary Password</h2>
                            <p class="text-muted">For security, please set a new password before continuing.</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Update Password</button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <a href="logout.php" class="text-muted small">Not your account? Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

