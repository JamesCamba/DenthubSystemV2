<?php
/**
 * Denthub Dental Clinic - Dentist View Appointment Details
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireRole('dentist');

$appointment_id = intval($_GET['id'] ?? 0);
$db = getDB();
$user = getCurrentUser();

// Get dentist ID
$stmt = $db->prepare("SELECT dentist_id FROM dentists WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$dentist = $stmt->get_result()->fetch_assoc();
$dentist_id = $dentist['dentist_id'];

$stmt = $db->prepare("SELECT a.*, p.*, s.service_name, s.price, b.branch_name
                      FROM appointments a
                      JOIN patients p ON a.patient_id = p.patient_id
                      JOIN services s ON a.service_id = s.service_id
                      LEFT JOIN branches b ON a.branch_id = b.branch_id
                      WHERE a.appointment_id = ? AND a.dentist_id = ?");
$stmt->bind_param("ii", $appointment_id, $dentist_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    header('Location: appointments.php');
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $status = sanitize($_POST['status']);
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Validate status transition
    $current_status = $appointment['status'];
    $allowed_statuses = array_keys(getAvailableStatusOptions($current_status, $appointment['appointment_date']));
    
    if (in_array($status, $allowed_statuses)) {
        $stmt = $db->prepare("UPDATE appointments SET status = ?, notes = ? WHERE appointment_id = ?");
        $stmt->bind_param("ssi", $status, $notes, $appointment_id);
        $stmt->execute();
        
        header('Location: view-appointment.php?id=' . $appointment_id . '&updated=1');
        exit;
    }
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="bi bi-tooth"></i> <?php echo APP_NAME; ?> - Dentist
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
                        <a class="nav-link" href="appointments.php">My Appointments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule.php">My Schedule</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Appointment Details</h2>
            <a href="appointments.php" class="btn btn-secondary">Back to Appointments</a>
        </div>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Appointment updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Appointment Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
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
                                    <?php 
                                    $statusOptions = getAvailableStatusOptions($appointment['status'], $appointment['appointment_date']);
                                    $isLocked = in_array($appointment['status'], ['completed', 'cancelled', 'no_show']);
                                    ?>
                                    <?php if ($isLocked): ?>
                                        <span class="badge bg-<?php echo getStatusBadge($appointment['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                        </span>
                                        <small class="text-muted d-block mt-1">Status is locked and cannot be changed.</small>
                                    <?php else: ?>
                                        <select name="status" class="form-select form-select-sm d-inline-block" style="width: auto;">
                                            <?php foreach ($statusOptions as $value => $label): ?>
                                                <option value="<?php echo $value; ?>" <?php echo $appointment['status'] === $value ? 'selected' : ''; ?>>
                                                    <?php echo $label; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Notes:</strong></div>
                                <div class="col-sm-8">
                                    <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($appointment['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <?php if ($appointment['reason_for_visit']): ?>
                            <div class="row">
                                <div class="col-sm-4"><strong>Reason for Visit:</strong></div>
                                <div class="col-sm-8"><?php echo nl2br(htmlspecialchars($appointment['reason_for_visit'])); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!$isLocked): ?>
                            <div class="mt-4">
                                <input type="hidden" name="update_status" value="1">
                                <button type="submit" class="btn btn-primary">Update Appointment</button>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Patient Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong><br><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></p>
                        <p><strong>Patient #:</strong><br><code><?php echo htmlspecialchars($appointment['patient_number']); ?></code></p>
                        <p><strong>Phone:</strong><br><?php echo htmlspecialchars(maskPhone($appointment['phone'] ?? '')); ?></p>
                        <?php if (!empty($appointment['email'])): ?>
                        <p><strong>Email:</strong><br><?php echo htmlspecialchars(maskEmail($appointment['email'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
