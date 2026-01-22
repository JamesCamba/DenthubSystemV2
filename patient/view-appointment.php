<?php
/**
 * Denthub Dental Clinic - Patient View Appointment
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

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
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment - <?php echo APP_NAME; ?></title>
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
                        <h4 class="mb-0">Appointment Details</h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Reference Number:</strong></div>
                            <div class="col-sm-8"><code><?php echo htmlspecialchars($appointment['appointment_number']); ?></code></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Service:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($appointment['service_name']); ?></div>
                        </div>
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
                        <?php if ($appointment['dentist_name']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Dentist:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($appointment['dentist_name']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($appointment['reason_for_visit']): ?>
                        <div class="row">
                            <div class="col-sm-4"><strong>Reason for Visit:</strong></div>
                            <div class="col-sm-8"><?php echo nl2br(htmlspecialchars($appointment['reason_for_visit'])); ?></div>
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

