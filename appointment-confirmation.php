<?php
/**
 * Denthub Dental Clinic - Appointment Confirmation
 */
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

requirePatientLogin();

$appointment_id = intval($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("SELECT a.*, p.first_name, p.last_name, p.phone, p.email, 
                      s.service_name, d.dentist_id, u.full_name as dentist_name
                      FROM appointments a
                      JOIN patients p ON a.patient_id = p.patient_id
                      JOIN services s ON a.service_id = s.service_id
                      LEFT JOIN dentists d ON a.dentist_id = d.dentist_id
                      LEFT JOIN users u ON d.user_id = u.user_id
                      WHERE a.appointment_id = ? AND a.patient_id = ?");
$stmt->bind_param("ii", $appointment_id, $_SESSION['patient_id']);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    header('Location: patient/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Confirmation - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php $nav_base = ''; $nav_patient_base = ''; $nav_active = 'book'; require_once 'includes/nav-public.php'; ?>
    <main class="denthub-main" style="margin-left:0;">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow border-success">
                    <div class="card-body p-5 text-center">
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 80px;"></i>
                        </div>
                        <h2 class="text-success mb-3">Appointment Booked Successfully!</h2>
                        <p class="text-muted mb-4">Your appointment has been submitted and is pending confirmation.</p>

                        <div class="card bg-light mb-4">
                            <div class="card-body text-start">
                                <h5 class="card-title mb-4">Appointment Details</h5>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Reference Number:</strong></div>
                                    <div class="col-sm-8"><code><?php echo htmlspecialchars($appointment['appointment_number']); ?></code></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Patient Name:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Service:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($appointment['service_name']); ?></div>
                                </div>
                                <?php if ($appointment['dentist_name']): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Dentist:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($appointment['dentist_name']); ?></div>
                                </div>
                                <?php endif; ?>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Date:</strong></div>
                                    <div class="col-sm-8"><?php echo formatDate($appointment['appointment_date']); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Time:</strong></div>
                                    <div class="col-sm-8"><?php echo formatTime($appointment['appointment_time']); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Status:</strong></div>
                                    <div class="col-sm-8">
                                        <span class="badge bg-<?php echo getStatusBadge($appointment['status']); ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if ($appointment['reason_for_visit']): ?>
                                <div class="row">
                                    <div class="col-sm-4"><strong>Reason:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($appointment['reason_for_visit']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Important:</strong> Your appointment is pending confirmation. Our staff will contact you at 
                            <strong><?php echo htmlspecialchars(maskPhone($appointment['phone'] ?? '')); ?></strong> to confirm your appointment.
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="patient/dashboard.php" class="btn btn-primary">View My Appointments</a>
                            <a href="index.php" class="btn btn-outline-secondary">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

