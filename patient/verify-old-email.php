<?php
/**
 * Denthub Dental Clinic - Step 1: Verify current (old) email before changing to new one
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/mailer.php';

requirePatientLogin();

$db = getDB();
$patient = getCurrentPatient();
$error = '';
$success = '';

if (empty($_SESSION['pending_email_new']) || empty($_SESSION['pending_email_old_code'])) {
    unset($_SESSION['pending_email_old'], $_SESSION['pending_email_new'], $_SESSION['pending_email_old_code'], $_SESSION['pending_email_old_expires']);
    header('Location: edit-profile.php');
    exit;
}

$old_email_masked = maskEmail($_SESSION['pending_email_old'] ?? '');
$new_email_masked = maskEmail($_SESSION['pending_email_new'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim(sanitize($_POST['verification_code'] ?? ''));
    if (empty($code)) {
        $error = 'Please enter the verification code.';
    } elseif (time() > ($_SESSION['pending_email_old_expires'] ?? 0)) {
        $error = 'Verification code has expired. Please start again from Edit Profile.';
        unset($_SESSION['pending_email_old'], $_SESSION['pending_email_new'], $_SESSION['pending_email_old_code'], $_SESSION['pending_email_old_expires']);
    } elseif ($code !== ($_SESSION['pending_email_old_code'] ?? '')) {
        $error = 'Invalid verification code. Please try again.';
    } else {
        // Step 1 passed: send code to NEW email
        $code_new = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $mailer = getMailer();
        $name = $patient['first_name'] . ' ' . $patient['last_name'];
        if ($mailer->sendVerificationCode($_SESSION['pending_email_new'], $code_new, $name, 'email_change')) {
            $_SESSION['pending_email_new_code'] = $code_new;
            $_SESSION['pending_email_new_expires'] = time() + 600;
            unset($_SESSION['pending_email_old_code'], $_SESSION['pending_email_old_expires']);
            header('Location: verify-email-change.php');
            exit;
        }
        $error = 'We could not send the verification code to your new email. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify current email - <?php echo APP_NAME; ?></title>
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
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-envelope-exclamation"></i> Step 1: Verify your current email</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">We sent a 6-digit code to your current email <strong><?php echo htmlspecialchars($old_email_masked); ?></strong>. Enter it below to continue.</p>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Verification code</label>
                                <input type="text" class="form-control text-center" name="verification_code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-warning">Verify and continue</button>
                                <a href="edit-profile.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
