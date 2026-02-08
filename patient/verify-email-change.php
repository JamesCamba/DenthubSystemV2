<?php
/**
 * Denthub Dental Clinic - Step 2: Verify new email, then update
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requirePatientLogin();

$db = getDB();
$patient_id = (int)$_SESSION['patient_id'];
$error = '';
$success = '';

if (empty($_SESSION['pending_email_new']) || empty($_SESSION['pending_email_new_code'])) {
    unset($_SESSION['pending_email_old'], $_SESSION['pending_email_new'], $_SESSION['pending_email_new_code'], $_SESSION['pending_email_new_expires']);
    header('Location: edit-profile.php');
    exit;
}

$new_email = $_SESSION['pending_email_new'];
$new_email_masked = maskEmail($new_email);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim(sanitize($_POST['verification_code'] ?? ''));
    if (empty($code)) {
        $error = 'Please enter the verification code.';
    } elseif (time() > ($_SESSION['pending_email_new_expires'] ?? 0)) {
        $error = 'Verification code has expired. Please start again from Edit Profile.';
        unset($_SESSION['pending_email_old'], $_SESSION['pending_email_new'], $_SESSION['pending_email_new_code'], $_SESSION['pending_email_new_expires']);
    } elseif ($code !== ($_SESSION['pending_email_new_code'] ?? '')) {
        $error = 'Invalid verification code. Please try again.';
    } else {
        try {
            $stmt1 = $db->prepare("UPDATE patients SET email = ?, updated_at = NOW() WHERE patient_id = ?");
            if (!$stmt1) {
                throw new Exception('Database error');
            }
            $stmt1->bind_param("si", $new_email, $patient_id);
            $stmt1->execute();

            $stmt2 = $db->prepare("UPDATE patient_accounts SET email = ? WHERE patient_id = ?");
            if (!$stmt2) {
                throw new Exception('Database error');
            }
            $stmt2->bind_param("si", $new_email, $patient_id);
            $stmt2->execute();

            logActivity('email_changed', '', null, $patient_id);
            unset($_SESSION['pending_email_old'], $_SESSION['pending_email_new'], $_SESSION['pending_email_new_code'], $_SESSION['pending_email_new_expires']);
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
    <title>Verify new email - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php require_once '../includes/patient-sidebar.php'; ?>

    <main class="denthub-main">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="card shadow denthub-verify-email">
                    <div class="card-header denthub-card-header">
                        <h5 class="mb-0 text-white"><i class="bi bi-envelope-check me-2"></i>Verify New Email</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                            <a href="profile.php" class="btn btn-primary">Back to Profile</a>
                        <?php else: ?>
                            <p class="text-muted">We sent a 6-digit code to <?php echo htmlspecialchars($new_email_masked); ?>. Enter it below.</p>
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Verification code</label>
                                    <input type="text" class="form-control text-center" name="verification_code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-verify btn-success">Verify</button>
                                    <a href="edit-profile.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
