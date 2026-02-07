<?php
/**
 * Denthub Dental Clinic - Verify new email (after patient requested email change)
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requirePatientLogin();

$db = getDB();
$patient_id = (int)$_SESSION['patient_id'];
$email_param = trim($_GET['email'] ?? '');
$error = '';
$success = '';

if (empty($_SESSION['pending_email_change']) || $email_param !== $_SESSION['pending_email_change']) {
    $_SESSION['pending_email_change'] = null;
    $_SESSION['pending_email_code'] = null;
    $_SESSION['pending_email_expires'] = null;
    header('Location: edit-profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim(sanitize($_POST['verification_code'] ?? ''));
    if (empty($code)) {
        $error = 'Please enter the verification code.';
    } elseif (time() > ($_SESSION['pending_email_expires'] ?? 0)) {
        $error = 'Verification code has expired. Please request a new one from Edit Profile.';
        unset($_SESSION['pending_email_change'], $_SESSION['pending_email_code'], $_SESSION['pending_email_expires']);
    } elseif ($code !== ($_SESSION['pending_email_code'] ?? '')) {
        $error = 'Invalid verification code. Please try again.';
    } else {
        $new_email = $_SESSION['pending_email_change'];
        try {
            $db->prepare("UPDATE patients SET email = ?, updated_at = NOW() WHERE patient_id = ?")->bind_param("si", $new_email, $patient_id)->execute();
            $db->prepare("UPDATE patient_accounts SET email = ? WHERE patient_id = ?")->bind_param("si", $new_email, $patient_id)->execute();
            unset($_SESSION['pending_email_change'], $_SESSION['pending_email_code'], $_SESSION['pending_email_expires']);
            $success = 'Your email has been updated successfully.';
        } catch (Exception $e) {
            error_log('Verify email change: ' . $e->getMessage());
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify New Email - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="../index.php"><?php echo APP_NAME; ?></a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
                <li class="nav-item"><a class="nav-link" href="../logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-envelope-check"></i> Verify New Email</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                            <a href="profile.php" class="btn btn-primary">Back to Profile</a>
                        <?php else: ?>
                            <p class="text-muted">We sent a 6-digit code to <strong><?php echo htmlspecialchars($email_param); ?></strong>. Enter it below.</p>
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Verification code</label>
                                    <input type="text" class="form-control text-center" name="verification_code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success">Verify</button>
                                    <a href="edit-profile.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
