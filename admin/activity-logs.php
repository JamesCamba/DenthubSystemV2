<?php
/**
 * Denthub Dental Clinic - Admin Activity Logs
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();
if (!hasRole('admin')) {
    header('Location: dashboard.php');
    exit;
}

$db_error = false;
$error_message = '';
$logs = [];
$page = 1;
$total_pages = 1;
$action_types = ['login_success', 'login_failed', 'password_changed', 'email_changed', 'phone_changed', 'appointment_status_changed'];
sort($action_types);

$filter_action = isset($_GET['action']) ? trim($_GET['action']) : '';
$filter_date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

try {
    $db = getDB();
    $sql = "SELECT log_id, action_type, actor_type, actor_id, username, full_name, role, details, ip_address, user_agent, created_at FROM activity_log WHERE 1=1";
    $params = [];
    $types = '';

    if ($filter_action !== '') {
        $sql .= " AND action_type = ?";
        $params[] = $filter_action;
        $types .= 's';
    }
    if ($filter_date_from !== '') {
        $sql .= " AND created_at >= ?";
        $params[] = $filter_date_from . ' 00:00:00';
        $types .= 's';
    }
    if ($filter_date_to !== '') {
        $sql .= " AND created_at <= ?";
        $params[] = $filter_date_to . ' 23:59:59';
        $types .= 's';
    }
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    $count_sql = preg_replace('/SELECT .+ FROM/', 'SELECT COUNT(*) as total FROM', $sql);
    $count_sql = preg_replace('/ORDER BY .+/', '', $count_sql);
    $count_stmt = $db->prepare($count_sql);
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_rows = (int)$count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = max(1, (int)ceil($total_rows / $per_page));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;

    $sql .= " ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";

    $stmt = $db->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $logsResult = $stmt->get_result();
    while ($row = $logsResult->fetch_assoc()) {
        $logs[] = $row;
    }

    $actionStmt = $db->query("SELECT DISTINCT action_type FROM activity_log ORDER BY action_type");
    if ($actionStmt) {
        $action_types = [];
        while ($row = $actionStmt->fetch_assoc()) {
            $action_types[] = $row['action_type'];
        }
        $known_actions = ['login_success', 'login_failed', 'password_changed', 'email_changed', 'phone_changed', 'appointment_status_changed'];
        foreach ($known_actions as $a) {
            if (!in_array($a, $action_types, true)) {
                $action_types[] = $a;
            }
        }
        sort($action_types);
    }
} catch (Throwable $e) {
    $db_error = true;
    $error_message = 'Activity logs are temporarily unavailable. Please try again later or contact your administrator.';
    error_log('Activity logs error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php echo denthubFaviconTags(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="denthub-main">
    <div class="container-fluid py-4">
        <h1 class="denthub-page-title"><i class="bi bi-journal-text me-2"></i> Activity Logs</h1>
        <div class="d-flex justify-content-end mb-4">
            <a href="dashboard.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if ($db_error): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                <p class="mb-0 mt-2 small text-muted">If you have just deployed, ensure the migration script <code>database/migration_activity_log_and_branch.sql</code> has been run on this database.</p>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3" id="logsFilterForm">
                    <div class="col-md-2">
                        <label class="form-label">Action</label>
                        <select name="action" class="form-select">
                            <option value="">All</option>
                            <?php foreach ($action_types as $at): ?>
                                <option value="<?php echo htmlspecialchars($at); ?>" <?php echo $filter_action === $at ? 'selected' : ''; ?>><?php echo htmlspecialchars($at); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card denthub-card-rounded">
            <div class="card-body">
                <div class="denthub-table-pagination" id="logsPagination">
                    <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                </div>
                <div class="table-scroll-wrapper">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Username</th>
                            <th>Full name</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody id="logsBody">
                        <?php foreach ($logs as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(formatDateTimeUtcToApp($row['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($row['username'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['role'] ?? $row['actor_type']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['action_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['details'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['ip_address'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($logs)): ?>
                    <p class="text-muted mb-0 p-3">No log entries found.</p>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function buildLogsQuery(page) {
            const form = document.getElementById('logsFilterForm');
            const params = new URLSearchParams(new FormData(form));
            if (page) params.set('page', page);
            return params.toString();
        }

        function loadLogs(page) {
            const qs = buildLogsQuery(page);
            fetch('api-activity-logs.php?' + qs)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    document.getElementById('logsBody').innerHTML = data.tbody;
                    const pag = document.getElementById('logsPagination');
                    let html = '<span class="page-info">Page ' + data.page + ' of ' + data.total_pages + '</span>';
                    if (data.page > 1) {
                        html += ' <button type="button" class="btn btn-sm btn-outline-primary ms-2" data-page="' + (data.page - 1) + '" id="logsPrevBtn">Previous</button>';
                    }
                    if (data.page < data.total_pages) {
                        html += ' <button type="button" class="btn btn-sm btn-outline-primary ms-2" data-page="' + (data.page + 1) + '" id="logsNextBtn">Next</button>';
                    }
                    pag.innerHTML = html;
                    const prev = document.getElementById('logsPrevBtn');
                    const next = document.getElementById('logsNextBtn');
                    if (prev) prev.addEventListener('click', () => loadLogs(prev.getAttribute('data-page')));
                    if (next) next.addEventListener('click', () => loadLogs(next.getAttribute('data-page')));
                })
                .catch(() => {});
        }

        document.getElementById('logsFilterForm').addEventListener('submit', function (e) {
            e.preventDefault();
            loadLogs(1);
        });

        // Periodic refresh
        setInterval(function () {
            loadLogs();
        }, 30000);
    </script>
</body>
</html>
