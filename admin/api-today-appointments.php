<?php
/**
 * Partial HTML for today's appointments table body (admin dashboard).
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

header('Content-Type: text/html; charset=utf-8');

try {
    $db = getDB();
    $today = date('Y-m-d');

    $stmt = $db->prepare("SELECT a.*, p.first_name, p.last_name, p.phone, s.service_name, u.full_name as dentist_name
                          FROM appointments a
                          JOIN patients p ON a.patient_id = p.patient_id
                          JOIN services s ON a.service_id = s.service_id
                          LEFT JOIN dentists d ON a.dentist_id = d.dentist_id
                          LEFT JOIN users u ON d.user_id = u.user_id
                          WHERE a.appointment_date = ? AND a.status IN ('pending', 'confirmed')
                          ORDER BY a.appointment_time ASC
                          LIMIT 10");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo '<tr><td colspan="6" class="text-center text-muted py-3">No appointments scheduled for today.</td></tr>';
        exit;
    }

    while ($apt = $res->fetch_assoc()) {
        ?>
        <tr>
            <td><?php echo formatTime($apt['appointment_time']); ?></td>
            <td><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></td>
            <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
            <td><?php echo $apt['dentist_name'] ? htmlspecialchars($apt['dentist_name']) : 'TBA'; ?></td>
            <td>
                <span class="badge bg-<?php echo getStatusBadge($apt['status']); ?>">
                    <?php echo ucfirst($apt['status']); ?>
                </span>
            </td>
            <td>
                <a href="view-appointment.php?id=<?php echo $apt['appointment_id']; ?>" class="btn btn-sm btn-outline-primary">
                    View
                </a>
            </td>
        </tr>
        <?php
    }
} catch (Throwable $e) {
    error_log('api-today-appointments admin error: ' . $e->getMessage());
    echo '<tr><td colspan="6" class="text-center text-muted py-3">Unable to load appointments.</td></tr>';
}

