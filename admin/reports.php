<?php
/**
 * Denthub Dental Clinic - Reports (daily/weekly/monthly/annually, CSV / Excel / PDF)
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/report_export.php';

requireRole('admin');

$db = getDB();

$period = $_GET['period'] ?? '';
if (!in_array($period, ['daily', 'weekly', 'monthly', 'annually'], true)) {
    $period = '';
}

$today = date('Y-m-d');
if ($period === 'daily') {
    $start_date = $end_date = $today;
} elseif ($period === 'weekly') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = $today;
} elseif ($period === 'monthly') {
    $start_date = date('Y-m-01');
    $end_date = $today;
} elseif ($period === 'annually') {
    $start_date = date('Y-01-01');
    $end_date = $today;
} else {
    $start_date = report_valid_date($_GET['start_date'] ?? '', date('Y-m-01'));
    $end_date = report_valid_date($_GET['end_date'] ?? '', $today);
}

if ($start_date > $end_date) {
    [$start_date, $end_date] = [$end_date, $start_date];
}

$report_title = trim((string) ($_GET['report_title'] ?? 'Appointment Report'));
$report_title = trim(strip_tags($report_title));
if ($report_title === '') {
    $report_title = 'Appointment Report';
}
if (strlen($report_title) > 120) {
    $report_title = substr($report_title, 0, 120);
}

$statusCount = "COALESCE(SUM(CASE WHEN LOWER(TRIM(status)) = '%s' THEN 1 ELSE 0 END), 0)::int";
$statusSql = sprintf(
    $statusCount . ' AS completed, ' . $statusCount . ' AS pending, ' . $statusCount . ' AS cancelled, ' . $statusCount . ' AS confirmed, ' . $statusCount . ' AS no_show',
    'completed',
    'pending',
    'cancelled',
    'confirmed',
    'no_show'
);

$totalsStmt = $db->prepare("SELECT COUNT(*)::int AS total_appointments, $statusSql
    FROM appointments
    WHERE appointment_date BETWEEN ? AND ?");
$totalsStmt->bind_param('ss', $start_date, $end_date);
$totalsStmt->execute();
$totalsRow = $totalsStmt->get_result()->fetch_assoc() ?: [];

$totals = [
    'total' => report_count($totalsRow['total_appointments'] ?? 0),
    'completed' => report_count($totalsRow['completed'] ?? 0),
    'pending' => report_count($totalsRow['pending'] ?? 0),
    'cancelled' => report_count($totalsRow['cancelled'] ?? 0),
    'confirmed' => report_count($totalsRow['confirmed'] ?? 0),
    'no_show' => report_count($totalsRow['no_show'] ?? 0),
];

$dailyStmt = $db->prepare("SELECT appointment_date::text AS appointment_date,
        COUNT(*)::int AS total,
        $statusSql
    FROM appointments
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY appointment_date
    ORDER BY appointment_date ASC");
$dailyStmt->bind_param('ss', $start_date, $end_date);
$dailyStmt->execute();
$dailyResult = $dailyStmt->get_result();

$daily = [];
while ($row = $dailyResult->fetch_assoc()) {
    $daily[] = [
        'date' => formatDate($row['appointment_date']),
        'total' => report_count($row['total'] ?? 0),
        'completed' => report_count($row['completed'] ?? 0),
        'pending' => report_count($row['pending'] ?? 0),
        'cancelled' => report_count($row['cancelled'] ?? 0),
        'confirmed' => report_count($row['confirmed'] ?? 0),
        'no_show' => report_count($row['no_show'] ?? 0),
    ];
}

$serviceStmt = $db->prepare("SELECT s.service_name, COUNT(a.appointment_id)::int AS count
    FROM services s
    INNER JOIN appointments a ON s.service_id = a.service_id
    WHERE a.appointment_date BETWEEN ? AND ?
    GROUP BY s.service_id, s.service_name
    ORDER BY count DESC, s.service_name ASC");
$serviceStmt->bind_param('ss', $start_date, $end_date);
$serviceStmt->execute();
$serviceResult = $serviceStmt->get_result();

$services = [];
while ($row = $serviceResult->fetch_assoc()) {
    $services[] = [
        'name' => (string) ($row['service_name'] ?? 'Service'),
        'count' => report_count($row['count'] ?? 0),
    ];
}

$period_label = report_period_label($period, formatDate($start_date), formatDate($end_date), $start_date);
$report = [
    'clinic' => APP_NAME,
    'title' => $report_title,
    'period_label' => $period_label,
    'start_display' => formatDate($start_date),
    'end_display' => formatDate($end_date),
    'start' => $start_date,
    'end' => $end_date,
    'generated' => date('F d, Y g:i A'),
    'totals' => $totals,
    'daily' => $daily,
    'services' => $services,
];

$export = $_GET['export'] ?? '';
if ($export === 'csv') {
    report_send_csv($report);
}
if ($export === 'xlsx') {
    report_send_xlsx($report);
}
if ($export === 'pdf') {
    report_send_pdf($report);
}

$exportQuery = http_build_query([
    'period' => $period,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'report_title' => $report_title,
]);

function report_valid_date($value, string $fallback): string
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $fallback;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        return $fallback;
    }
    return $value;
}
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

    <main class="denthub-main">
    <div class="container-fluid py-4">
        <h1 class="denthub-page-title"><i class="bi bi-graph-up me-2"></i> Reports</h1>
        <p class="text-muted mb-4"><?php echo htmlspecialchars($period_label); ?></p>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card denthub-stat-card primary h-100">
                    <div class="card-body">
                        <h6 class="mb-2">Total Appointments</h6>
                        <h3 class="report-stat-value mb-0"><?php echo number_format($totals['total']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card denthub-stat-card success h-100">
                    <div class="card-body">
                        <h6 class="mb-2">Completed</h6>
                        <h3 class="report-stat-value mb-0"><?php echo number_format($totals['completed']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card denthub-stat-card warning h-100">
                    <div class="card-body">
                        <h6 class="mb-2">Pending</h6>
                        <h3 class="report-stat-value mb-0"><?php echo number_format($totals['pending']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card denthub-stat-card danger h-100">
                    <div class="card-body">
                        <h6 class="mb-2">Cancelled</h6>
                        <h3 class="report-stat-value mb-0"><?php echo number_format($totals['cancelled']); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($totals['confirmed'] > 0 || $totals['no_show'] > 0): ?>
            <p class="text-muted small mb-4">
                Also in this period:
                <?php if ($totals['confirmed'] > 0): ?>Confirmed <?php echo number_format($totals['confirmed']); ?><?php endif; ?>
                <?php if ($totals['confirmed'] > 0 && $totals['no_show'] > 0): ?> · <?php endif; ?>
                <?php if ($totals['no_show'] > 0): ?>No-show <?php echo number_format($totals['no_show']); ?><?php endif; ?>
            </p>
        <?php endif; ?>

        <div class="card mb-4 no-print denthub-card-rounded shadow-sm">
            <div class="reports-tab-header text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i> Generate Report</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="reportForm">
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Report type / period</label>
                            <div class="btn-group w-100 flex-wrap" role="group">
                                <a href="?period=daily&amp;report_title=<?php echo urlencode($report_title); ?>" class="btn btn-outline-primary <?php echo $period === 'daily' ? 'active' : ''; ?>">Daily</a>
                                <a href="?period=weekly&amp;report_title=<?php echo urlencode($report_title); ?>" class="btn btn-outline-primary <?php echo $period === 'weekly' ? 'active' : ''; ?>">Weekly</a>
                                <a href="?period=monthly&amp;report_title=<?php echo urlencode($report_title); ?>" class="btn btn-outline-primary <?php echo $period === 'monthly' ? 'active' : ''; ?>">Monthly</a>
                                <a href="?period=annually&amp;report_title=<?php echo urlencode($report_title); ?>" class="btn btn-outline-primary <?php echo $period === 'annually' ? 'active' : ''; ?>">Annually</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Report title</label>
                            <input type="text" class="form-control" name="report_title" value="<?php echo htmlspecialchars($report_title); ?>" placeholder="e.g. January 2026 Summary" maxlength="120">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">Apply dates</button>
                            <a href="?export=xlsx&amp;<?php echo htmlspecialchars($exportQuery); ?>" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
                            <a href="?export=csv&amp;<?php echo htmlspecialchars($exportQuery); ?>" class="btn btn-outline-success"><i class="bi bi-filetype-csv"></i> Export CSV</a>
                            <a href="?export=pdf&amp;<?php echo htmlspecialchars($exportQuery); ?>" class="btn btn-secondary"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4 denthub-card-rounded">
            <div class="reports-tab-header text-white">
                <h5 class="mb-0"><?php echo htmlspecialchars($report_title); ?> — Daily breakdown</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total Appointments</th>
                                <th>Completed</th>
                                <th>Pending</th>
                                <th>Cancelled</th>
                                <th>Confirmed</th>
                                <th>No-show</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($daily): ?>
                                <?php foreach ($daily as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                                        <td><?php echo number_format($row['total']); ?></td>
                                        <td><span class="badge bg-success"><?php echo number_format($row['completed']); ?></span></td>
                                        <td><span class="badge bg-warning text-dark"><?php echo number_format($row['pending']); ?></span></td>
                                        <td><span class="badge bg-danger"><?php echo number_format($row['cancelled']); ?></span></td>
                                        <td><span class="badge bg-info text-dark"><?php echo number_format($row['confirmed']); ?></span></td>
                                        <td><span class="badge bg-dark"><?php echo number_format($row['no_show']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No data available for the selected date range.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card denthub-card-rounded">
            <div class="reports-tab-header text-white">
                <h5 class="mb-0">Service Statistics</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Number of Appointments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($services): ?>
                                <?php foreach ($services as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo number_format($row['count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
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
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
