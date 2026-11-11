<?php
/**
 * Denthub Dental Clinic - Dentist Dashboard
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/export.php';

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
$today = appTodayImmutable()->format('Y-m-d');
$charts = dashboardTrendData($dentist_id);

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
$monthStart = appTodayImmutable()->modify('first day of this month')->format('Y-m-d');
$monthEnd = appTodayImmutable()->modify('last day of this month')->format('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments 
                     WHERE dentist_id = ? AND status = 'completed' 
                     AND appointment_date BETWEEN ? AND ?");
$stmt->bind_param("iss", $dentist_id, $monthStart, $monthEnd);
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
    <?php $asset_base = '../'; $include_charts = true; require __DIR__ . '/../includes/head-assets.php'; ?>
</head>
<body>
<?php require_once 'navbar.php'; ?>

    <main class="denthub-main">
    <div class="container-fluid py-4">
        <h2 class="denthub-page-title mb-4"><i class="bi bi-grid-1x2 me-2"></i> Welcome, Dr. <?php echo htmlspecialchars($user['full_name']); ?></h2>

        <!-- Branch selector -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card denthub-card-rounded shadow-sm">
                    <div class="denthub-card-header text-white">
                        <h5 class="mb-0"><i class="bi bi-building me-2"></i>My Active Branch</h5>
                    </div>
                    <div class="card-body">
                        <form id="dentistBranchForm">
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <select class="form-select" id="dentistBranchSelect" name="branch_id">
                                        <?php
                                        $branchesRes = $db->query("SELECT branch_id, branch_name FROM branches WHERE is_active = TRUE ORDER BY branch_name");
                                        while ($b = $branchesRes->fetch_assoc()):
                                            $bid = (int)$b['branch_id'];
                                            $selected = ($bid === (int)$dentist['branch_id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $bid; ?>" <?php echo $selected; ?>>
                                                <?php echo htmlspecialchars($b['branch_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-4 text-end">
                                    <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                This sets your default branch. Per-day branch can be configured in Schedule Manager.
                            </small>
                        </form>
                    </div>
                </div>
            </div>
        </div>

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

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card denthub-stat-card success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">Today's Available Slots</h6>
                                <h3 class="mb-0" id="todaySlotsCountDentist">--</h3>
                            </div>
                            <i class="bi bi-clock-history" style="font-size: 48px; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card denthub-stat-card success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">Tomorrow's Available Slots</h6>
                                <h3 class="mb-0" id="tomorrowSlotsCountDentist">--</h3>
                            </div>
                            <i class="bi bi-clock" style="font-size: 48px; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card chart-card shadow-sm">
                    <div class="denthub-card-header">My appointments — last 14 days</div>
                    <div class="card-body"><canvas id="trendChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card chart-card shadow-sm">
                    <div class="denthub-card-header">Status mix</div>
                    <div class="card-body"><canvas id="statusChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Today's Appointments -->
        <div class="card">
            <div class="card-header denthub-card-header denthub-header-green">
                <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Today's Appointments</h5>
            </div>
            <div class="card-body">
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
                            <tbody id="todayAppointmentsBodyDentist">
                                <tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
            </div>
        </div>
    </div>
    </main>

    <script>
        document.getElementById('dentistBranchForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const branchId = document.getElementById('dentistBranchSelect').value;
            fetch('update-branch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'branch_id=' + encodeURIComponent(branchId)
            })
            .then(r => r.json())
            .then(data => {
                // Silent failure/success; page naturally uses updated branch on next load.
            })
            .catch(() => {});
        });
        function loadSlotSummaryDentist() {
            fetch('../api/get-slot-summary.php?role=dentist')
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.branches.length) return;
                    const b = data.branches[0];
                    document.getElementById('todaySlotsCountDentist').textContent = b.today;
                    document.getElementById('tomorrowSlotsCountDentist').textContent = b.tomorrow;
                })
                .catch(() => {});
        }

        function loadTodayAppointmentsDentist() {
            fetch('api-today-appointments.php')
                .then(r => r.text())
                .then(html => {
                    document.getElementById('todayAppointmentsBodyDentist').innerHTML = html;
                })
                .catch(() => {});
        }

        const trendLabels = <?php echo json_encode($charts['trend_labels']); ?>;
        const trendValues = <?php echo json_encode($charts['trend_values']); ?>;
        const statusLabels = <?php echo json_encode($charts['status_labels']); ?>;
        const statusValues = <?php echo json_encode($charts['status_values']); ?>;
        if (window.Denthub && Denthub.applyChartDefaults) Denthub.applyChartDefaults();
        if (window.Chart) {
            new Chart(document.getElementById('trendChart'), {
                type: 'bar',
                data: { labels: trendLabels, datasets: [{ label: 'Appointments', data: trendValues, backgroundColor: '#004AAD' }] },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: { labels: statusLabels, datasets: [{ data: statusValues, backgroundColor: ['#f59e0b', '#0ea5e9', '#198754', '#DC3545', '#64748b'] }] },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        }
        loadSlotSummaryDentist();
        loadTodayAppointmentsDentist();
        setInterval(loadTodayAppointmentsDentist, 30000);
    </script>
</body>
</html>
