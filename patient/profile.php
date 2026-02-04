<?php
/**
 * Denthub Dental Clinic - Patient Profile
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requirePatientLogin();

$db = getDB();
$patient = getCurrentPatient();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)$_SESSION['patient_id'];

    // Re-fetch latest data for comparison
    $stmt = $db->prepare("SELECT email, phone, address FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();

    $new_email = sanitize($_POST['email'] ?? $current['email']);
    $new_phone = sanitize($_POST['phone'] ?? $current['phone']);
    $new_address = sanitize($_POST['address'] ?? $current['address']);

    if (!empty($new_email) && !validateEmail($new_email)) {
        $error = 'Invalid email address.';
    } elseif (!empty($new_phone) && !validatePhone($new_phone)) {
        $error = 'Invalid phone number.';
    } else {
        $changed_by = null; // patient editing their own record

        // Log and update phone + phone_hash
        if ($new_phone !== $current['phone'] || $new_address !== $current['address']) {
            // Audit trail
            logPatientChange($patient_id, 'phone', $current['phone'], $new_phone, $changed_by);
            logPatientChange($patient_id, 'address', $current['address'], $new_address, $changed_by);

            $phone_hash = $new_phone ? hash('sha256', $new_phone) : null;
            $upd = $db->prepare("UPDATE patients SET phone = ?, phone_hash = ?, address = ?, updated_at = NOW() WHERE patient_id = ?");
            $upd->bind_param("sssi", $new_phone, $phone_hash, $new_address, $patient_id);
            $upd->execute();
        }

        // Email re-verification flow: do not change email directly here.
        if ($new_email !== $current['email']) {
            $error = 'To change your email, please contact the clinic so they can verify and update it securely.';
        } else {
            $success = 'Profile updated successfully.';
        }

        // Refresh patient data for display
        $patient = getCurrentPatient();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="../index.php">
                <img src="../resources/468397831_122123862374527362_8536709169507539928_n.jpg" alt="<?php echo APP_NAME; ?>" height="40" class="me-2">
                <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../book-appointment.php">Book Appointment</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">My Profile</h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Patient Number:</strong></div>
                            <div class="col-sm-8"><code><?php echo htmlspecialchars($patient['patient_number']); ?></code></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Name:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
                        </div>
                        <?php if ($patient['middle_name']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Middle Name:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($patient['middle_name']); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Email:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($patient['email'] ?: '-'); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Phone:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($patient['phone']); ?></div>
                        </div>
                        <?php if ($patient['birthdate']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Birthdate:</strong></div>
                            <div class="col-sm-8"><?php echo formatDate($patient['birthdate']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($patient['gender']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Gender:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($patient['gender']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($patient['address']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Address:</strong></div>
                            <div class="col-sm-8"><?php echo nl2br(htmlspecialchars($patient['address'])); ?></div>
                        </div>
                        <?php endif; ?>

                        <hr>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

