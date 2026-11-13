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

// Current status totals (whole system, not limited to today)
$status_counts = ['pending' => 0, 'cancelled' => 0, 'no_show' => 0];
$res = $db->query("SELECT status, COUNT(*) AS count FROM appointments WHERE status IN ('pending', 'cancelled', 'no_show') GROUP BY status");
while ($row = $res->fetch_assoc()) {
    $status_counts[$row['status']] = (int)$row['count'];
}
$pending_appointments = $status_counts['pending'];
$cancelled_appointments = $status_counts['cancelled'];
$no_show_appointments = $status_counts['no_show'];

// Total patients
$stmt = $db->query("SELECT COUNT(*) as count FROM patients");
$total_patients = $stmt->fetch_assoc()['count'];

// Branches for filters/widgets
$branches = [];
$res = $db->query("SELECT branch_id, branch_name FROM branches WHERE is_active = TRUE ORDER BY branch_name");
while ($row = $res->fetch_assoc()) {
    $branches[] = $row;
}
$defaultBranchId = count($branches) ? (int)$branches[0]['branch_id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php echo denthubFaviconTags(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="denthub-main">
        <h1 class="denthub-page-title"><i class="bi bi-grid-1x2 me-2"></i> Dashboard</h1>

        <!-- Statistics Cards -->
        <div class="denthub-dash-grid">
            <div class="card denthub-stat-card primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <h6 class="card-subtitle mb-0 opacity-90">Today's Appointments</h6>
                            <h3 id="statToday"><?php echo (int)$today_appointments; ?></h3>
                            <small class="denthub-stat-meta">&nbsp;</small>
                        </div>
                        <i class="bi bi-calendar-check denthub-stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="card denthub-stat-card warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <h6 class="card-subtitle mb-0">Pending</h6>
                            <h3 id="statPending"><?php echo (int)$pending_appointments; ?></h3>
                            <small class="denthub-stat-meta">&nbsp;</small>
                        </div>
                        <i class="bi bi-clock-history denthub-stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="card denthub-stat-card danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <h6 class="card-subtitle mb-0 opacity-90">Cancelled</h6>
                            <h3 id="statCancelled"><?php echo (int)$cancelled_appointments; ?></h3>
                            <small class="denthub-stat-meta">&nbsp;</small>
                        </div>
                        <i class="bi bi-x-circle denthub-stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="card denthub-stat-card danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <h6 class="card-subtitle mb-0 opacity-90">No Shows</h6>
                            <h3 id="statNoShows"><?php echo (int)$no_show_appointments; ?></h3>
                            <small class="denthub-stat-meta">&nbsp;</small>
                        </div>
                        <i class="bi bi-person-x denthub-stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="card denthub-stat-card info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <h6 class="card-subtitle mb-0">Total Patients</h6>
                            <h3 id="statPatients"><?php echo (int)$total_patients; ?></h3>
                            <small class="denthub-stat-meta">&nbsp;</small>
                        </div>
                        <i class="bi bi-people denthub-stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="card denthub-stat-card success text-white" id="todaySlotsCard" style="cursor:pointer;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <h6 class="card-subtitle mb-0 opacity-90">Today's Available Slots</h6>
                            <h3 id="todaySlotsCount">--</h3>
                            <small class="denthub-stat-meta" id="todaySlotsBranchLabel">&nbsp;</small>
                        </div>
                        <i class="bi bi-clock denthub-stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="card denthub-stat-card success text-white" id="tomorrowSlotsCard" style="cursor:pointer;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <h6 class="card-subtitle mb-0 opacity-90">Tomorrow's Available Slots</h6>
                            <h3 id="tomorrowSlotsCount">--</h3>
                            <small class="denthub-stat-meta" id="tomorrowSlotsBranchLabel">&nbsp;</small>
                        </div>
                        <i class="bi bi-clock-history denthub-stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="card denthub-stat-card denthub-branch-widget">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div class="flex-grow-1">
                            <h6 class="card-subtitle mb-0">Branch for Slot Widgets</h6>
                            <select class="form-select mt-1" id="dashboardBranchSelect" aria-label="Branch for slot widgets">
                                <option value="0">All Active Branches</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?php echo (int)$b['branch_id']; ?>" <?php echo $defaultBranchId === (int)$b['branch_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['branch_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="denthub-stat-meta">&nbsp;</small>
                        </div>
                        <i class="bi bi-building denthub-stat-icon" style="color: var(--denthub-primary); opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="denthub-dash-charts">
            <div class="card denthub-dash-chart-card">
                <div class="denthub-card-header">
                    <span><i class="bi bi-pie-chart me-2"></i>Status Mix</span>
                </div>
                <div class="card-body">
                    <div class="denthub-chart-canvas">
                        <canvas id="statusMixChart" aria-label="Appointment status mix"></canvas>
                    </div>
                </div>
            </div>
            <div class="card denthub-dash-chart-card">
                <div class="denthub-card-header">
                    <span><i class="bi bi-bar-chart me-2"></i>Top Services</span>
                    <span class="denthub-chart-range" id="topServicesRange">Last 14 days</span>
                </div>
                <div class="card-body">
                    <div class="denthub-chart-canvas">
                        <canvas id="topServicesChart" aria-label="Top services in the last 14 days"></canvas>
                    </div>
                </div>
            </div>
            <div class="card denthub-dash-chart-card">
                <div class="denthub-card-header">
                    <span><i class="bi bi-graph-up me-2"></i>Appointments</span>
                    <span class="denthub-chart-range" id="appointmentsRange">Last 14 days</span>
                </div>
                <div class="card-body">
                    <div class="denthub-chart-canvas">
                        <canvas id="appointmentsChart" aria-label="Appointments comparison for the last 14 days"></canvas>
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
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        function loadSlotSummary() {
            const branchId = document.getElementById('dashboardBranchSelect').value || 0;
            fetch(`../api/get-slot-summary.php?role=admin&branch_id=${branchId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    let todayTotal = 0, tomorrowTotal = 0, label = '';
                    data.branches.forEach((b, idx) => {
                        todayTotal += b.today;
                        tomorrowTotal += b.tomorrow;
                        if (!label) label = b.branch_name || 'Branch ' + b.branch_id;
                    });
                    document.getElementById('todaySlotsCount').textContent = todayTotal;
                    document.getElementById('tomorrowSlotsCount').textContent = tomorrowTotal;
                    document.getElementById('todaySlotsBranchLabel').textContent = label ? label : 'All branches';
                    document.getElementById('tomorrowSlotsBranchLabel').textContent = label ? label : 'All branches';
                })
                .catch(() => {});
        }

        function loadTodayAppointments() {
            fetch('api-today-appointments.php')
                .then(r => r.text())
                .then(html => {
                    document.getElementById('todayAppointmentsBody').innerHTML = html;
                })
                .catch(() => {});
        }

        document.getElementById('dashboardBranchSelect').addEventListener('change', loadSlotSummary);
        document.getElementById('todaySlotsCard').addEventListener('click', function () {
            const branchId = document.getElementById('dashboardBranchSelect').value || 0;
            const url = branchId ? `calendar.php?branch_id=${branchId}` : 'calendar.php';
            window.location.href = url;
        });
        document.getElementById('tomorrowSlotsCard').addEventListener('click', function () {
            const branchId = document.getElementById('dashboardBranchSelect').value || 0;
            const url = branchId ? `calendar.php?branch_id=${branchId}` : 'calendar.php';
            window.location.href = url;
        });

        const statusColors = {
            pending: '#ffc107',
            confirmed: '#004AAD',
            cancelled: '#DC3545',
            no_show: '#fd7e14'
        };
        const chartFont = { family: "'Segoe UI', system-ui, sans-serif", size: 12 };
        let statusMixChart = null;
        let topServicesChart = null;
        let appointmentsChart = null;

        function chartBaseOptions(extra) {
            return Object.assign({
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, padding: 12, font: chartFont }
                    }
                }
            }, extra || {});
        }

        function renderDashboardCharts(data) {
            const range = (data.range && data.range.label) ? data.range.label : 'Last 14 days';
            document.getElementById('topServicesRange').textContent = range;
            document.getElementById('appointmentsRange').textContent = range;
            document.getElementById('statToday').textContent = data.today_appointments;
            document.getElementById('statPending').textContent = data.pending;
            document.getElementById('statCancelled').textContent = data.cancelled;
            document.getElementById('statNoShows').textContent = data.no_shows;
            document.getElementById('statPatients').textContent = data.total_patients;

            const mix = data.status_mix || {};
            const mixLabels = ['Cancelled', 'Pending', 'Confirmed', 'No Show'];
            const mixValues = [mix.cancelled || 0, mix.pending || 0, mix.confirmed || 0, mix.no_show || 0];
            const mixColors = [statusColors.cancelled, statusColors.pending, statusColors.confirmed, statusColors.no_show];
            if (!statusMixChart) {
                statusMixChart = new Chart(document.getElementById('statusMixChart'), {
                    type: 'pie',
                    data: {
                        labels: mixLabels,
                        datasets: [{ data: mixValues, backgroundColor: mixColors, borderWidth: 1, borderColor: '#fff' }]
                    },
                    options: chartBaseOptions()
                });
            } else {
                statusMixChart.data.datasets[0].data = mixValues;
                statusMixChart.update();
            }

            const services = data.top_services || [];
            const serviceLabels = services.length ? services.map(s => s.name) : ['No services'];
            const serviceValues = services.length ? services.map(s => s.count) : [0];
            if (!topServicesChart) {
                topServicesChart = new Chart(document.getElementById('topServicesChart'), {
                    type: 'bar',
                    data: {
                        labels: serviceLabels,
                        datasets: [{
                            label: 'Appointments',
                            data: serviceValues,
                            backgroundColor: '#004AAD',
                            borderRadius: 6,
                            maxBarThickness: 42
                        }]
                    },
                    options: chartBaseOptions({
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { font: chartFont, maxRotation: 40, minRotation: 0, autoSkip: false } },
                            y: { beginAtZero: true, ticks: { precision: 0, font: chartFont }, grace: '8%' }
                        }
                    })
                });
            } else {
                topServicesChart.data.labels = serviceLabels;
                topServicesChart.data.datasets[0].data = serviceValues;
                topServicesChart.update();
            }

            const days = data.appointments_by_day || { labels: [], series: {} };
            const series = days.series || {};
            const lineSets = [
                { key: 'confirmed', label: 'Confirmed', color: statusColors.confirmed },
                { key: 'pending', label: 'Pending', color: statusColors.pending },
                { key: 'cancelled', label: 'Cancelled', color: statusColors.cancelled },
                { key: 'no_show', label: 'No Show', color: statusColors.no_show }
            ].map(item => ({
                label: item.label,
                data: series[item.key] || [],
                borderColor: item.color,
                backgroundColor: item.color,
                tension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 4,
                borderWidth: 2,
                fill: false
            }));
            if (!appointmentsChart) {
                appointmentsChart = new Chart(document.getElementById('appointmentsChart'), {
                    type: 'line',
                    data: { labels: days.labels || [], datasets: lineSets },
                    options: chartBaseOptions({
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            x: { ticks: { font: chartFont, maxRotation: 0, autoSkip: true, maxTicksLimit: 7 } },
                            y: { beginAtZero: true, ticks: { precision: 0, font: chartFont }, grace: '8%' }
                        }
                    })
                });
            } else {
                appointmentsChart.data.labels = days.labels || [];
                lineSets.forEach((set, i) => {
                    appointmentsChart.data.datasets[i].data = set.data;
                });
                appointmentsChart.update();
            }
        }

        function loadDashboardStats() {
            fetch('api-dashboard-stats.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    renderDashboardCharts(data);
                })
                .catch(() => {});
        }

        loadSlotSummary();
        loadTodayAppointments();
        loadDashboardStats();
        setInterval(loadTodayAppointments, 30000);
        setInterval(function () {
            loadSlotSummary();
            loadDashboardStats();
        }, 60000);
    </script>
</body>
</html>

