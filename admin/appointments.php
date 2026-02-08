<?php
/**
 * Denthub Dental Clinic - Appointment Management
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$db = getDB();

// Auto-update overdue appointments to no_show for consistency
autoUpdateOverdueAppointments();

// Handle status update using centralized rules
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $status = sanitize($_POST['status']);

    if (updateAppointmentStatus($appointment_id, $status, null, $_SESSION['user_id'] ?? null)) {
        header('Location: appointments.php?updated=1');
    } else {
        header('Location: appointments.php?error=invalid_status');
    }
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'appointment_date';
$order = strtolower($_GET['order'] ?? 'desc');
if (!in_array($order, ['asc', 'desc'], true)) $order = 'desc';

$allowed_sort = ['appointment_number' => 'a.appointment_number', 'patient' => 'p.last_name', 'service' => 's.service_name', 'appointment_date' => 'a.appointment_date', 'appointment_time' => 'a.appointment_time', 'dentist' => 'u.full_name', 'status' => 'a.status'];
$sort_col = isset($allowed_sort[$sort]) ? $allowed_sort[$sort] : 'a.appointment_date';
$order_sql = $order === 'asc' ? 'ASC' : 'DESC';

// Build query
$where = [];
$params = [];
$types = '';

if ($status_filter) {
    $where[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($date_filter) {
    $where[] = "a.appointment_date = ?";
    $params[] = $date_filter;
    $types .= 's';
}

if ($search) {
    $where[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR a.appointment_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT a.*, p.first_name, p.last_name, p.phone, p.email, s.service_name, u.full_name as dentist_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN services s ON a.service_id = s.service_id
        LEFT JOIN dentists d ON a.dentist_id = d.dentist_id
        LEFT JOIN users u ON d.user_id = u.user_id
        $where_clause
        ORDER BY $sort_col $order_sql, a.appointment_id DESC
        LIMIT 100";

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$appointments = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Appointment Management</h2>
            <a href="add-appointment.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add New Appointment
            </a>
        </div>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Appointment status updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" id="filterForm">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Patient name or reference #" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="no_show" <?php echo $status_filter === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
                </form>
            </div>
        </div>

        <!-- Appointments Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <?php
                                $base = $_GET;
                                $link = function($col) use ($base, $sort, $order) {
                                    $base['sort'] = $col;
                                    $base['order'] = ($sort === $col && $order === 'asc') ? 'desc' : 'asc';
                                    return '?' . http_build_query($base);
                                };
                                ?>
                                <th><a href="<?php echo $link('appointment_number'); ?>" class="text-decoration-none">Reference #</a> <?php if ($sort === 'appointment_number') echo $order === 'asc' ? '↑' : '↓'; ?></th>
                                <th><a href="<?php echo $link('patient'); ?>" class="text-decoration-none">Patient</a> <?php if ($sort === 'patient') echo $order === 'asc' ? '↑' : '↓'; ?></th>
                                <th><a href="<?php echo $link('service'); ?>" class="text-decoration-none">Service</a> <?php if ($sort === 'service') echo $order === 'asc' ? '↑' : '↓'; ?></th>
                                <th><a href="<?php echo $link('appointment_date'); ?>" class="text-decoration-none">Date</a> <?php if ($sort === 'appointment_date') echo $order === 'asc' ? '↑' : '↓'; ?></th>
                                <th><a href="<?php echo $link('appointment_time'); ?>" class="text-decoration-none">Time</a> <?php if ($sort === 'appointment_time') echo $order === 'asc' ? '↑' : '↓'; ?></th>
                                <th><a href="<?php echo $link('dentist'); ?>" class="text-decoration-none">Dentist</a> <?php if ($sort === 'dentist') echo $order === 'asc' ? '↑' : '↓'; ?></th>
                                <th><a href="<?php echo $link('status'); ?>" class="text-decoration-none">Status</a> <?php if ($sort === 'status') echo $order === 'asc' ? '↑' : '↓'; ?></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($appointments->num_rows > 0): ?>
                                <?php while ($apt = $appointments->fetch_assoc()): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($apt['appointment_number']); ?></code></td>
                                        <td><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                                        <td><?php echo formatDate($apt['appointment_date']); ?></td>
                                        <td><?php echo formatTime($apt['appointment_time']); ?></td>
                                        <td><?php echo $apt['dentist_name'] ? htmlspecialchars($apt['dentist_name']) : 'TBA'; ?></td>
                                        <td>
                                            <?php 
                                            $statusOptions = getAvailableStatusOptions($apt['status'], $apt['appointment_date']);
                                            $isLocked = in_array($apt['status'], ['completed', 'cancelled', 'no_show']);
                                            ?>
                                            <?php if ($isLocked): ?>
                                                <span class="badge bg-<?php echo getStatusBadge($apt['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $apt['status'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <form method="POST" action="" class="d-inline" id="statusForm<?php echo $apt['appointment_id']; ?>">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['appointment_id']; ?>">
                                                    <select name="status" class="form-select form-select-sm" data-current="<?php echo htmlspecialchars($apt['status']); ?>" onchange="if(confirm('Are you sure you want to change this appointment status? The patient will be notified by email.')) { this.form.submit(); } else { this.value = this.getAttribute('data-current'); }">
                                                        <?php foreach ($statusOptions as $value => $label): ?>
                                                            <option value="<?php echo $value; ?>" <?php echo $apt['status'] === $value ? 'selected' : ''; ?>>
                                                                <?php echo $label; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="hidden" name="update_status" value="1">
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view-appointment.php?id=<?php echo $apt['appointment_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No appointments found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit filters for real-time updates
        (function() {
            const form = document.getElementById('filterForm');
            if (!form) return;

            const inputs = form.querySelectorAll('input, select');
            let timer = null;

            function triggerFilter() {
                if (timer) {
                    clearTimeout(timer);
                }
                timer = setTimeout(function() {
                    form.submit();
                }, 400); // simple debounce
            }

            inputs.forEach(function(el) {
                el.addEventListener('input', triggerFilter);
                el.addEventListener('change', triggerFilter);
            });
        })();
    </script>
</body>
</html>

