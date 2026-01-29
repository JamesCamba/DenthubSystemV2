<?php
/**
 * Denthub Dental Clinic - Dentist Schedule Management
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireRole('dentist');

$db = getDB();
$user = getCurrentUser();

// Get dentist ID
$stmt = $db->prepare("SELECT dentist_id FROM dentists WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$dentist = $stmt->get_result()->fetch_assoc();

if (!$dentist) {
    die('Dentist record not found.');
}

$dentist_id = $dentist['dentist_id'];
$error = '';
$success = '';

// Load existing schedules
$schedules = [];
$sStmt = $db->prepare("SELECT day_of_week, start_time, end_time FROM dentist_schedules WHERE dentist_id = ? AND is_available = TRUE");
$sStmt->bind_param("i", $dentist_id);
$sStmt->execute();
$res = $sStmt->get_result();
while ($row = $res->fetch_assoc()) {
    $schedules[(int)$row['day_of_week']] = $row;
}

$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $available = $_POST['available'] ?? [];
    $start_times = $_POST['start_time'] ?? [];
    $end_times = $_POST['end_time'] ?? [];

    // Basic validation
    foreach ($available as $day => $val) {
        $start = $start_times[$day] ?? '';
        $end = $end_times[$day] ?? '';
        if ($start && $end && $start >= $end) {
            $error = 'End time must be later than start time for ' . $dayNames[$day] . '.';
            break;
        }
    }

    if (!$error) {
        // Clear existing schedule and recreate from form
        $delStmt = $db->prepare("DELETE FROM dentist_schedules WHERE dentist_id = ?");
        $delStmt->bind_param("i", $dentist_id);
        $delStmt->execute();

        $insStmt = $db->prepare("INSERT INTO dentist_schedules (dentist_id, day_of_week, start_time, end_time, is_available) 
                                 VALUES (?, ?, ?, ?, TRUE)");

        foreach ($available as $day => $val) {
            $day = (int)$day;
            $start = $start_times[$day] ?? '';
            $end = $end_times[$day] ?? '';
            if (!$start || !$end) {
                continue;
            }
            $insStmt->bind_param("iiss", $dentist_id, $day, $start, $end);
            $insStmt->execute();
        }

        $success = 'Schedule updated successfully.';

        // Reload schedules for display
        $schedules = [];
        $sStmt->execute();
        $res = $sStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $schedules[(int)$row['day_of_week']] = $row;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - <?php echo APP_NAME; ?></title>
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
                        <a class="nav-link active" href="schedule.php">My Schedule</a>
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
        <h2 class="mb-4">My Weekly Schedule</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <p class="text-muted">
                        Configure the days and times you are available for appointments. Patients will only see time slots that match your schedule.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 20%;">Day</th>
                                    <th style="width: 10%;">Available</th>
                                    <th style="width: 35%;">Start Time</th>
                                    <th style="width: 35%;">End Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($day = 0; $day <= 6; $day++): 
                                    $hasSchedule = isset($schedules[$day]);
                                    $startValue = $hasSchedule ? $schedules[$day]['start_time'] : '09:00:00';
                                    $endValue = $hasSchedule ? $schedules[$day]['end_time'] : '17:00:00';
                                ?>
                                <tr>
                                    <td><strong><?php echo $dayNames[$day]; ?></strong></td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input" name="available[<?php echo $day; ?>]"
                                               <?php echo $hasSchedule ? 'checked' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="time" class="form-control" name="start_time[<?php echo $day; ?>]"
                                               value="<?php echo substr($startValue, 0, 5); ?>">
                                    </td>
                                    <td>
                                        <input type="time" class="form-control" name="end_time[<?php echo $day; ?>]"
                                               value="<?php echo substr($endValue, 0, 5); ?>">
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                        <button type="submit" class="btn btn-primary">Save Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

