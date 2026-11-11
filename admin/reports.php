<?php
/**
 * Denthub Dental Clinic - Reports (daily/weekly/monthly/annually, export CSV / Print to PDF)
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/export.php';

requireRole('admin');

$db = getDB();

// Period quick-select
$period = $_GET['period'] ?? '';
$todayDt = appTodayImmutable();
$today = $todayDt->format('Y-m-d');
if ($period === 'daily') {
    $start_date = $end_date = $today;
} elseif ($period === 'weekly') {
    $dow = (int)$todayDt->format('N');
    $start_date = $todayDt->modify('-' . ($dow - 1) . ' days')->format('Y-m-d');
    $end_date = $today;
} elseif ($period === 'monthly') {
    $start_date = $todayDt->modify('first day of this month')->format('Y-m-d');
    $end_date = $today;
} elseif ($period === 'annually') {
    $start_date = $todayDt->format('Y') . '-01-01';
    $end_date = $today;
} else {
    $rawStart = $_GET['start_date'] ?? appTodayImmutable()->modify('first day of this month')->format('Y-m-d');
    $rawEnd = $_GET['end_date'] ?? $today;
    $start_date = parseBookingDate($rawStart) ? $rawStart : appTodayImmutable()->modify('first day of this month')->format('Y-m-d');
    $end_date = parseBookingDate($rawEnd) ? $rawEnd : $today;
}

$report_title = trim($_GET['report_title'] ?? 'Appointment Report');
$report_title = substr(preg_replace('/[\r\n]+/', ' ', $report_title), 0, 120);

if (isset($_GET['export']) && $_GET['export'] === 'appointments') {
    $stmt = $db->prepare("
        SELECT a.appointment_number, a.appointment_date, a.appointment_time, a.status,
               p.last_name, p.first_name, s.service_name, u.full_name AS dentist_name, b.branch_name
        FROM appointments a
        JOIN patients p ON p.patient_id = a.patient_id
        JOIN services s ON s.service_id = a.service_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users u ON u.user_id = d.user_id
        LEFT JOIN branches b ON b.branch_id = a.branch_id
        WHERE a.appointment_date BETWEEN ? AND ?
        ORDER BY a.appointment_date, a.appointment_time
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $rows = $stmt->get_result();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9_-]/i', '_', $report_title) . '_appointments_' . $start_date . '_' . $end_date . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    writeCsvLetterhead($out, $report_title . ' — appointment register', $start_date . ' to ' . $end_date);
    fputcsv($out, ['Reference', 'Date', 'Time', 'Patient', 'Service', 'Dentist', 'Branch', 'Status']);
    while ($row = $rows->fetch_assoc()) {
        fputcsv($out, [
            $row['appointment_number'],
            formatDate($row['appointment_date']),
            formatTime($row['appointment_time']),
            trim($row['last_name'] . ', ' . $row['first_name']),
            $row['service_name'],
            $row['dentist_name'] ?: 'TBA',
            $row['branch_name'],
            ucfirst(str_replace('_', ' ', $row['status'])),
        ]);
    }
    fclose($out);
    exit;
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $db->prepare("SELECT appointment_date, COUNT(*) as total, 
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments WHERE appointment_date BETWEEN ? AND ?
        GROUP BY appointment_date ORDER BY appointment_date");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $rows = $stmt->get_result();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9_-]/i', '_', $report_title) . '_' . $start_date . '_' . $end_date . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    writeCsvLetterhead($out, $report_title, $start_date . ' to ' . $end_date);
    fputcsv($out, ['Date', 'Total Appointments', 'Completed', 'Cancelled']);
    while ($row = $rows->fetch_assoc()) {
        fputcsv($out, [formatDate($row['appointment_date']), $row['total'], $row['completed'], $row['cancelled']]);
    }
    fclose($out);
    exit;
}

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
    <?php $asset_base = '../'; require __DIR__ . '/../includes/head-assets.php'; ?>
</head>
<body class="print-report-page">
    <?php include 'navbar.php'; ?>

    <main class="denthub-main">
    <div class="container-fluid py-4">
        <div class="print-only">
            <?php echo printDocumentStart($report_title, 'Period: ' . $start_date . ' to ' . $end_date); ?>
        </div>
        <h1 class="denthub-page-title"><i class="bi bi-graph-up me-2"></i> Reports</h1>

        <!-- Generate Report: Period + Title + Export -->
        <div class="card mb-4 no-print denthub-card-rounded shadow-sm">
            <div class="reports-tab-header text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i> Generate Report</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="reportForm">
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Report type / period</label>
                            <div class="btn-group w-100" role="group">
                                <a href="?period=daily&report_title=<?php echo urlencode($report_title); ?>" class="btn btn-outline-primary <?php echo $period === 'daily' ? 'active' : ''; ?>">Daily</a>
                                <a href="?period=weekly&report_title=<?php echo urlencode($report_title); ?>" class="btn btn-outline-primary <?php echo $period === 'weekly' ? 'active' : ''; ?>">Weekly</a>
                                <a href="?period=monthly&report_title=<?php echo urlencode($report_title); ?>" class="btn btn-outline-primary <?php echo $period === 'monthly' ? 'active' : ''; ?>">Monthly</a>
                                <a href="?period=annually&report_title=<?php echo urlencode($report_title); ?>" class="btn btn-outline-primary <?php echo $period === 'annually' ? 'active' : ''; ?>">Annually</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Report title</label>
                            <input type="text" class="form-control" name="report_title" value="<?php echo htmlspecialchars($report_title); ?>" placeholder="e.g. January 2026 Summary">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary me-2">Apply dates</button>
                            <a href="?export=csv&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&report_title=<?php echo urlencode($report_title); ?>" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet"></i> Summary CSV</a>
                            <a href="?export=appointments&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&report_title=<?php echo urlencode($report_title); ?>" class="btn btn-outline-success"><i class="bi bi-table"></i> Appointment register CSV</a>
                            <button type="button" class="btn btn-secondary" onclick="window.print();"><i class="bi bi-printer"></i> Print / Save as PDF</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Statistics (add tab header to first card if desired) -->
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

        <!-- Daily Appointments Report (printable) -->
        <div class="card mb-4 printable denthub-card-rounded">
            <div class="reports-tab-header text-white">
                <h5 class="mb-0"><?php echo htmlspecialchars($report_title); ?> — Daily breakdown</h5>
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
        <div class="card denthub-card-rounded">
            <div class="reports-tab-header text-white">
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
        <div class="print-only">
            <?php echo printDocumentEnd(); ?>
        </div>
    </div>
    </main>
</body>
</html>

