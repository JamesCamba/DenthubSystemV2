<?php
/**
 * Denthub Dental Clinic - Patient Dashboard
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requirePatientLogin();

$db = getDB();
$patient_id = $_SESSION['patient_id'];

// Get patient info
$patient = getCurrentPatient();

// Get upcoming appointments
$stmt = $db->prepare("SELECT a.*, s.service_name, u.full_name as dentist_name
                      FROM appointments a
                      JOIN services s ON a.service_id = s.service_id
                      LEFT JOIN dentists d ON a.dentist_id = d.dentist_id
                      LEFT JOIN users u ON d.user_id = u.user_id
                      WHERE a.patient_id = ? AND a.appointment_date >= CURDATE()
                      ORDER BY a.appointment_date ASC, a.appointment_time ASC
                      LIMIT 10");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$upcoming_appointments = $stmt->get_result();

// Get appointment history
$stmt = $db->prepare("SELECT a.*, s.service_name, u.full_name as dentist_name
                      FROM appointments a
                      JOIN services s ON a.service_id = s.service_id
                      LEFT JOIN dentists d ON a.dentist_id = d.dentist_id
                      LEFT JOIN users u ON d.user_id = u.user_id
                      WHERE a.patient_id = ? AND a.appointment_date < CURDATE()
                      ORDER BY a.appointment_date DESC, a.appointment_time DESC
                      LIMIT 10");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$appointment_history = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php $nav_base = '../'; $nav_patient_base = ''; $nav_active = 'dashboard'; require_once '../includes/nav-public.php'; ?>

    <main class="denthub-main" style="margin-left:0;">
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col">
                <h2 class="denthub-page-title mb-1">Welcome, <?php echo htmlspecialchars($_SESSION['patient_name']); ?>!</h2>
                <p class="text-muted">Manage your appointments and profile</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4 g-3">
            <div class="col-md-4">
                <div class="card denthub-card-light h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-plus text-primary" style="font-size: 48px;"></i>
                        <h5 class="mt-3">Book Appointment</h5>
                        <a href="../book-appointment.php" class="btn btn-primary">Book Now</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card denthub-card-light h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-person-circle text-info" style="font-size: 48px;"></i>
                        <h5 class="mt-3">My Profile</h5>
                        <a href="profile.php" class="btn btn-info">View Profile</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card denthub-card-light h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-clock-history text-success" style="font-size: 48px;"></i>
                        <h5 class="mt-3">Appointment History</h5>
                        <a href="#history" class="btn btn-success">View History</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="card mb-4">
            <div class="card-header denthub-card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Upcoming Appointments</h5>
            </div>
            <div class="card-body">
                <?php if ($upcoming_appointments->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Reference #</th>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Dentist</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($apt = $upcoming_appointments->fetch_assoc()): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($apt['appointment_number']); ?></code></td>
                                        <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                                        <td><?php echo formatDate($apt['appointment_date']); ?></td>
                                        <td><?php echo formatTime($apt['appointment_time']); ?></td>
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
                    <p class="text-muted text-center">No upcoming appointments.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Appointment History -->
        <div class="card" id="history">
            <div class="card-header denthub-card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Appointment History</h5>
            </div>
            <div class="card-body">
                <?php if ($appointment_history->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Reference #</th>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Dentist</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($apt = $appointment_history->fetch_assoc()): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($apt['appointment_number']); ?></code></td>
                                        <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                                        <td><?php echo formatDate($apt['appointment_date']); ?></td>
                                        <td><?php echo formatTime($apt['appointment_time']); ?></td>
                                        <td><?php echo $apt['dentist_name'] ? htmlspecialchars($apt['dentist_name']) : 'N/A'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($apt['status']); ?>">
                                                <?php echo ucfirst($apt['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No appointment history.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

