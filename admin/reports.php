<?php
/**
 * Denthub Dental Clinic - Reports
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireRole('admin');

$db = getDB();

// Get date range
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Daily appointments report
$stmt = $db->prepare("SELECT appointment_date, COUNT(*) as count, 
                      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                      SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                      FROM appointments 
                      WHERE appointment_date BETWEEN ? AND ?
                      GROUP BY appointment_date
                      ORDER BY appointment_date DESC");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$daily_report = $stmt->get_result();

// Service statistics
$stmt = $db->prepare("SELECT s.service_name, COUNT(a.appointment_id) as count
                      FROM services s
                      LEFT JOIN appointments a ON s.service_id = a.service_id
                      WHERE a.appointment_date BETWEEN ? AND ?
                      GROUP BY s.service_id, s.service_name
                      ORDER BY count DESC");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$service_stats = $stmt->get_result();

// Total statistics
$stmt = $db->prepare("SELECT 
                      COUNT(*) as total_appointments,
                      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                      SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                      SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                      FROM appointments 
                      WHERE appointment_date BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid py-4">
        <h2 class="mb-4">Reports</h2>

        <!-- Date Range Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Generate Report</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6>Total Appointments</h6>
                        <h3><?php echo $totals['total_appointments']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6>Completed</h6>
                        <h3><?php echo $totals['completed']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h6>Pending</h6>
                        <h3><?php echo $totals['pending']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h6>Cancelled</h6>
                        <h3><?php echo $totals['cancelled']; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Appointments Report -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Daily Appointments Report</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total Appointments</th>
                                <th>Completed</th>
                                <th>Cancelled</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($daily_report->num_rows > 0): ?>
                                <?php while ($row = $daily_report->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo formatDate($row['appointment_date']); ?></td>
                                        <td><?php echo $row['count']; ?></td>
                                        <td><span class="badge bg-success"><?php echo $row['completed']; ?></span></td>
                                        <td><span class="badge bg-danger"><?php echo $row['cancelled']; ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No data available for the selected date range.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Service Statistics -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Service Statistics</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Number of Appointments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($service_stats->num_rows > 0): ?>
                                <?php while ($row = $service_stats->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['service_name']); ?></td>
                                        <td><?php echo $row['count']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted">No data available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

