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

// Pending lab cases
$stmt = $db->query("SELECT COUNT(*) as count FROM lab_cases WHERE status IN ('pending', 'in_progress')");
$pending_lab_cases = $stmt->fetch_assoc()['count'];

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
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="bi bi-tooth"></i> <?php echo APP_NAME; ?> - Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="appointments.php">Appointments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="patients.php">Patients</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="lab-cases.php">Lab Cases</a>
                    </li>
                    <?php if (hasRole('admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <?php endif; ?>
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
        <h2 class="mb-4">Dashboard</h2>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2">Today's Appointments</h6>
                                <h3 class="mb-0"><?php echo $today_appointments; ?></h3>
                            </div>
                            <i class="bi bi-calendar-check" style="font-size: 48px; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2">Pending Appointments</h6>
                                <h3 class="mb-0"><?php echo $pending_appointments; ?></h3>
                            </div>
                            <i class="bi bi-clock-history" style="font-size: 48px; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2">Total Patients</h6>
                                <h3 class="mb-0"><?php echo $total_patients; ?></h3>
                            </div>
                            <i class="bi bi-people" style="font-size: 48px; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2">Pending Lab Cases</h6>
                                <h3 class="mb-0"><?php echo $pending_lab_cases; ?></h3>
                            </div>
                            <i class="bi bi-briefcase" style="font-size: 48px; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Appointments -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Today's Appointments</h5>
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
                    <p class="text-muted text-center">No appointments scheduled for today.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

