<?php
/**
 * Denthub Dental Clinic - Admin Dashboard
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$db = getDB();
$user = getCurrentUser();

// Get today's statistics
$today = date('Y-m-d');

// Today's appointments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND status IN ('pending', 'confirmed')");
$stmt->bind_param("s", $today);
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_assoc()['count'];

// Pending appointments
$stmt = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'");
$pending_appointments = $stmt->fetch_assoc()['count'];

// Total patients
$stmt = $db->query("SELECT COUNT(*) as count FROM patients");
$total_patients = $stmt->fetch_assoc()['count'];

// Today's appointments list
$stmt = $db->prepare("SELECT a.*, p.first_name, p.last_name, p.phone, s.service_name, u.full_name as dentist_name
                      FROM appointments a
                      JOIN patients p ON a.patient_id = p.patient_id
                      JOIN services s ON a.service_id = s.service_id
                      LEFT JOIN dentists d ON a.dentist_id = d.dentist_id
                      LEFT JOIN users u ON d.user_id = u.user_id
                      WHERE a.appointment_date = ? AND a.status IN ('pending', 'confirmed')
                      ORDER BY a.appointment_time ASC
                      LIMIT 10");
$stmt->bind_param("s", $today);
$stmt->execute();
$today_appointments_list = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="denthub-main">
        <h1 class="denthub-page-title">Dashboard</h1>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card denthub-stat-card primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-90">Today's Appointments</h6>
                                <h3 class="mb-0"><?php echo $today_appointments; ?></h3>
                            </div>
                            <i class="bi bi-calendar-check" style="font-size: 48px; opacity: 0.4;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card denthub-stat-card warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-90">Pending Appointments</h6>
                                <h3 class="mb-0"><?php echo $pending_appointments; ?></h3>
                            </div>
                            <i class="bi bi-clock-history" style="font-size: 48px; opacity: 0.4;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card denthub-stat-card info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-90">Total Patients</h6>
                                <h3 class="mb-0"><?php echo $total_patients; ?></h3>
                            </div>
                            <i class="bi bi-people" style="font-size: 48px; opacity: 0.4;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Appointments -->
        <div class="card shadow-sm">
            <div class="denthub-card-header denthub-header-green">
                <i class="bi bi-calendar-check me-2"></i> Today's Appointments
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
                                    <th>Dentist</th>
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
                                        <td><?php echo $apt['dentist_name'] ? htmlspecialchars($apt['dentist_name']) : 'TBA'; ?></td>
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
                    <p class="text-muted text-center py-4 mb-0">No appointments scheduled for today.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

