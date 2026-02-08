<?php
/**
 * Denthub Dental Clinic - Patient Edit Profile (phone and email only; email change requires verification)
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)$_SESSION['patient_id'];

    $stmt = $db->prepare("SELECT p.email, p.phone, pa.email as account_email FROM patients p JOIN patient_accounts pa ON p.patient_id = pa.patient_id WHERE p.patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    if (!$current) {
        $error = 'Session expired. Please log in again.';
    } else {
        $new_phone = trim($_POST['phone'] ?? '');
        $new_email = trim(sanitize($_POST['email'] ?? ''));

        if (empty($new_phone)) {
            $error = 'Phone number is required.';
        } elseif (!validatePhone($new_phone)) {
            $error = 'Invalid phone number format.';
        } elseif (strlen(preg_replace('/\D/', '', $new_phone)) > 11) {
            $error = 'Philippines mobile numbers are up to 11 digits (e.g. 09161234567).';
        } elseif (!empty($new_email) && !validateEmail($new_email)) {
            $error = 'Invalid email address.';
        } else {
            try {
                // Update phone (and hash) in patients
                $phone_hash = $new_phone ? hash('sha256', $new_phone) : null;
                $upd = $db->prepare("UPDATE patients SET phone = ?, phone_hash = ?, updated_at = NOW() WHERE patient_id = ?");
                $upd->bind_param("ssi", $new_phone, $phone_hash, $patient_id);
                $upd->execute();
                logActivity('phone_changed', '', null, $patient_id);

                if ($new_email !== $current['email']) {
                    // Email change: 2-step — first verify OLD email, then NEW email
                    $check = $db->prepare("SELECT account_id FROM patient_accounts WHERE email = ? AND patient_id != ?");
                    $check->bind_param("si", $new_email, $patient_id);
                    $check->execute();
                    if ($check->get_result()->num_rows > 0) {
                        $error = 'That email is already in use by another account.';
                    } elseif (empty($current['email'])) {
                        $error = 'You do not have a current email on file. Please contact the clinic.';
                    } else {
                        $code_old = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                        $mailer = getMailer();
                        $name = $patient['first_name'] . ' ' . $patient['last_name'];
                        if ($mailer->sendVerificationCode($current['email'], $code_old, $name, 'email_change_old')) {
                            $_SESSION['pending_email_old'] = $current['email'];
                            $_SESSION['pending_email_new'] = $new_email;
                            $_SESSION['pending_email_old_code'] = $code_old;
                            $_SESSION['pending_email_old_expires'] = time() + 600;
                            header('Location: verify-old-email.php');
                            exit;
                        }
                        $error = 'We could not send the verification code to your current email. Please try again.';
                    }
                } else {
                    $success = 'Profile updated successfully.';
                }
            } catch (Exception $e) {
                error_log('Patient edit profile: ' . $e->getMessage());
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
    $patient = getCurrentPatient();
}

// If no POST or success, show form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="../index.php"><?php echo APP_NAME; ?></a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="edit-profile.php">Edit Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="../logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">You can only change your phone number and email. Changing email requires verification.</p>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                            <a href="profile.php" class="btn btn-primary">Back to Profile</a>
                        <?php else: ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="phone" required maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX"
                                       value="<?php echo htmlspecialchars($patient['phone'] ?? ''); ?>">
                                <small class="text-muted">Philippines mobile: 11 digits</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>">
                                <small class="text-muted">Changing email will require verification sent to the new address.</small>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Save changes</button>
                                <a href="profile.php" class="btn btn-secondary">Cancel</a>
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
