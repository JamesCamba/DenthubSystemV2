<?php
/**
 * Denthub Dental Clinic - Admin Dashboard
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/export.php';

requireStaff();

$db = getDB();
$user = getCurrentUser();
$today = appTodayImmutable()->format('Y-m-d');

$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND status IN ('pending', 'confirmed')");
$stmt->bind_param("s", $today);
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'");
$pending_appointments = $stmt->fetch_assoc()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM patients");
$total_patients = $stmt->fetch_assoc()['count'];

$branches = [];
$res = $db->query("SELECT branch_id, branch_name FROM branches WHERE is_active = TRUE ORDER BY branch_name");
while ($row = $res->fetch_assoc()) {
    $branches[] = $row;
}
$defaultBranchId = count($branches) ? (int)$branches[0]['branch_id'] : 0;
$charts = dashboardTrendData();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <?php $asset_base = '../'; $include_charts = true; require __DIR__ . '/../includes/head-assets.php'; ?>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="denthub-main">
        <h1 class="denthub-page-title"><i class="bi bi-grid-1x2 me-2"></i> Dashboard</h1>

        <div class="row g-4 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card denthub-stat-card primary text-white">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 opacity-90">Today's Appointments</h6>
                        <h3 class="mb-0"><?php echo (int)$today_appointments; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card denthub-stat-card warning">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 opacity-90">Pending</h6>
                        <h3 class="mb-0"><?php echo (int)$pending_appointments; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card denthub-stat-card info text-white">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 opacity-90">Total Patients</h6>
                        <h3 class="mb-0"><?php echo (int)$total_patients; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card denthub-stat-card success text-white" id="todaySlotsCard" style="cursor:pointer;">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 opacity-90">Today's Open Slots</h6>
                        <h3 class="mb-0" id="todaySlotsCount">--</h3>
                        <small class="opacity-75" id="todaySlotsBranchLabel"></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">Branch for slot widgets</label>
                <select class="form-select" id="dashboardBranchSelect">
                    <option value="0">All active branches</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo (int)$b['branch_id']; ?>">
                            <?php echo htmlspecialchars($b['branch_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tomorrow's open slots</label>
                <div class="card denthub-stat-card success text-white" id="tomorrowSlotsCard" style="cursor:pointer;">
                    <div class="card-body py-2">
                        <h3 class="mb-0" id="tomorrowSlotsCount">--</h3>
                        <small class="opacity-75" id="tomorrowSlotsBranchLabel"></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card chart-card shadow-sm">
                    <div class="denthub-card-header">Appointments — last 14 days</div>
                    <div class="card-body">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card chart-card shadow-sm">
                    <div class="denthub-card-header">Status mix — last 14 days</div>
                    <div class="card-body">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card chart-card shadow-sm">
                    <div class="denthub-card-header">Top services — last 14 days</div>
                    <div class="card-body">
                        <canvas id="serviceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="denthub-card-header denthub-header-green">
                <i class="bi bi-calendar-check me-2"></i> Today's Appointments
            </div>
            <div class="card-body">
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
                        <tbody id="todayAppointmentsBody">
                            <tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        const trendLabels = <?php echo json_encode($charts['trend_labels']); ?>;
        const trendValues = <?php echo json_encode($charts['trend_values']); ?>;
        const statusLabels = <?php echo json_encode($charts['status_labels']); ?>;
        const statusValues = <?php echo json_encode($charts['status_values']); ?>;
        const serviceLabels = <?php echo json_encode($charts['service_labels'] ?? []); ?>;
        const serviceValues = <?php echo json_encode($charts['service_values'] ?? []); ?>;
        if (window.Denthub && Denthub.applyChartDefaults) Denthub.applyChartDefaults();
        const primary = (window.Denthub && Denthub.chartColors) ? Denthub.chartColors().primary : '#004AAD';

        if (window.Chart) {
            new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'Appointments',
                        data: trendValues,
                        borderColor: primary,
                        backgroundColor: 'rgba(0,74,173,0.15)',
                        fill: true,
                        tension: 0.35
                    }]
                },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{ data: statusValues, backgroundColor: ['#f59e0b', '#0ea5e9', '#198754', '#DC3545', '#64748b'] }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
            if (document.getElementById('serviceChart')) {
                new Chart(document.getElementById('serviceChart'), {
                    type: 'bar',
                    data: {
                        labels: serviceLabels,
                        datasets: [{ label: 'Appointments', data: serviceValues, backgroundColor: primary }]
                    },
                    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
                });
            }
        }

        function loadSlotSummary() {
            const branchId = document.getElementById('dashboardBranchSelect').value || 0;
            fetch(`../api/get-slot-summary.php?role=admin&branch_id=${branchId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    let todayTotal = 0, tomorrowTotal = 0, label = '';
                    data.branches.forEach((b) => {
                        todayTotal += b.today;
                        tomorrowTotal += b.tomorrow;
                        if (!label) label = b.branch_name || 'Branch ' + b.branch_id;
                    });
                    const all = String(branchId) === '0';
                    document.getElementById('todaySlotsCount').textContent = todayTotal;
                    document.getElementById('tomorrowSlotsCount').textContent = tomorrowTotal;
                    document.getElementById('todaySlotsBranchLabel').textContent = all ? 'All branches' : label;
                    document.getElementById('tomorrowSlotsBranchLabel').textContent = all ? 'All branches' : label;
                })
                .catch(() => {});
        }

        function loadTodayAppointments() {
            fetch('api-today-appointments.php')
                .then(r => r.text())
                .then(html => { document.getElementById('todayAppointmentsBody').innerHTML = html; })
                .catch(() => {});
        }

        document.getElementById('dashboardBranchSelect').addEventListener('change', loadSlotSummary);
        document.getElementById('todaySlotsCard').addEventListener('click', function () {
            const branchId = document.getElementById('dashboardBranchSelect').value || 0;
            window.location.href = branchId ? `calendar.php?branch_id=${branchId}` : 'calendar.php';
        });
        document.getElementById('tomorrowSlotsCard').addEventListener('click', function () {
            const branchId = document.getElementById('dashboardBranchSelect').value || 0;
            window.location.href = branchId ? `calendar.php?branch_id=${branchId}` : 'calendar.php';
        });

        loadSlotSummary();
        loadTodayAppointments();
        setInterval(loadTodayAppointments, 30000);
    </script>
</body>
</html>
