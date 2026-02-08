<?php
/**
 * Denthub Dental Clinic - Dentist Dashboard
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireRole('dentist');

$db = getDB();
$user = getCurrentUser();

// Get dentist info
$stmt = $db->prepare("SELECT d.*, u.full_name, u.email, u.phone 
                     FROM dentists d 
                     JOIN users u ON d.user_id = u.user_id 
                     WHERE d.user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$dentist = $stmt->get_result()->fetch_assoc();
$dentist_id = $dentist['dentist_id'];

// Get today's statistics
$today = date('Y-m-d');

// Today's appointments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments 
                     WHERE dentist_id = ? AND appointment_date = ? 
                     AND status IN ('pending', 'confirmed')");
$stmt->bind_param("is", $dentist_id, $today);
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_assoc()['count'];

// Pending appointments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments 
                     WHERE dentist_id = ? AND status = 'pending'");
$stmt->bind_param("i", $dentist_id);
$stmt->execute();
$pending_appointments = $stmt->get_result()->fetch_assoc()['count'];

// Completed appointments this month (PostgreSQL-compatible date functions)
// Use EXTRACT to match current month and year
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments 
                     WHERE dentist_id = ? AND status = 'completed' 
                     AND EXTRACT(MONTH FROM appointment_date) = EXTRACT(MONTH FROM CURRENT_DATE) 
                     AND EXTRACT(YEAR FROM appointment_date) = EXTRACT(YEAR FROM CURRENT_DATE)");
$stmt->bind_param("i", $dentist_id);
$stmt->execute();
$monthly_completed = $stmt->get_result()->fetch_assoc()['count'];

// Today's appointments list
$stmt = $db->prepare("SELECT a.*, p.first_name, p.last_name, p.phone, s.service_name
                      FROM appointments a
                      JOIN patients p ON a.patient_id = p.patient_id
                      JOIN services s ON a.service_id = s.service_id
                      WHERE a.dentist_id = ? AND a.appointment_date = ? 
                      AND a.status IN ('pending', 'confirmed')
                      ORDER BY a.appointment_time ASC
                      LIMIT 10");
$stmt->bind_param("is", $dentist_id, $today);
$stmt->execute();
$today_appointments_list = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dentist Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php require_once 'navbar.php'; ?>

    <main class="denthub-main" style="margin-left:0;">
    <div class="container-fluid py-4">
        <h2 class="denthub-page-title mb-4">Welcome, Dr. <?php echo htmlspecialchars($user['full_name']); ?></h2>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card denthub-stat-card primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">Today's Appointments</h6>
                                <h3 class="mb-0"><?php echo $today_appointments; ?></h3>
                            </div>
                            <i class="bi bi-calendar-check" style="font-size: 48px; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card denthub-stat-card warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">Pending Appointments</h6>
                                <h3 class="mb-0"><?php echo $pending_appointments; ?></h3>
                            </div>
                            <i class="bi bi-clock-history" style="font-size: 48px; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card denthub-stat-card success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">Completed This Month</h6>
                                <h3 class="mb-0"><?php echo $monthly_completed; ?></h3>
                            </div>
                            <i class="bi bi-check-circle" style="font-size: 48px; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Appointments -->
        <div class="card">
            <div class="card-header denthub-card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Today's Appointments</h5>
            </div>
            <div class="card-body">
                <?php if ($today_appointments_list->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($apt = $today_appointments_list->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo formatTime($apt['appointment_time']); ?></td>
                                        <td><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($apt['status']); ?>">
                                                <?php echo ucfirst($apt['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view-appointment.php?id=<?php echo $apt['appointment_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No appointments scheduled for today.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
