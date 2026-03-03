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
            <div class="col-md-3">
                <div class="card denthub-stat-card success text-white" id="todaySlotsCard" style="cursor:pointer;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-90">Today's Available Slots</h6>
                                <h3 class="mb-0" id="todaySlotsCount">--</h3>
                                <small class="opacity-75" id="todaySlotsBranchLabel"></small>
                            </div>
                            <i class="bi bi-clock-history" style="font-size: 48px; opacity: 0.4;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mt-3 mt-md-0">
                <div class="card denthub-stat-card success text-white" id="tomorrowSlotsCard" style="cursor:pointer;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-90">Tomorrow's Available Slots</h6>
                                <h3 class="mb-0" id="tomorrowSlotsCount">--</h3>
                                <small class="opacity-75" id="tomorrowSlotsBranchLabel"></small>
                            </div>
                            <i class="bi bi-clock" style="font-size: 48px; opacity: 0.4;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">Branch for Slot Widgets</label>
                <select class="form-select" id="dashboardBranchSelect">
                    <option value="0">All Active Branches</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo (int)$b['branch_id']; ?>" <?php echo $defaultBranchId === (int)$b['branch_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['branch_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                            <tbody id="todayAppointmentsBody">
                                <tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>
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

        loadSlotSummary();
        loadTodayAppointments();
        setInterval(loadTodayAppointments, 30000);
    </script>
</body>
</html>

