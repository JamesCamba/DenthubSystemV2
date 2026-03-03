<?php
/**
 * API: Activity logs partial HTML (tbody + simple pagination info).
 * Mirrors filters from activity-logs.php.
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();
if (!hasRole('admin')) {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');

$filter_action = isset($_GET['action']) ? trim($_GET['action']) : '';
$filter_date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));

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

    ob_start();
    while ($row = $logsResult->fetch_assoc()) {
        ?>
        <tr>
            <td><?php echo htmlspecialchars(formatDateTimeUtcToApp($row['created_at'])); ?></td>
            <td><?php echo htmlspecialchars($row['username'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($row['full_name'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($row['role'] ?? $row['actor_type']); ?></td>
            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['action_type']); ?></span></td>
            <td><?php echo htmlspecialchars($row['details'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($row['ip_address'] ?? '-'); ?></td>
        </tr>
        <?php
    }
    $tbody_html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'tbody' => $tbody_html,
        'page' => $page,
        'total_pages' => $total_pages,
    ]);
} catch (Throwable $e) {
    error_log('api-activity-logs error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to load logs']);
}

