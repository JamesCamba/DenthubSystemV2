<?php
/**
 * Partial HTML for today's appointments table body (dentist dashboard).
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireRole('dentist');

header('Content-Type: text/html; charset=utf-8');

try {
    $db = getDB();
    $today = date('Y-m-d');

    // Find dentist id for current user
    $stmt = $db->prepare("SELECT dentist_id FROM dentists WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $dent = $stmt->get_result()->fetch_assoc();
    if (!$dent) {
        echo '<tr><td colspan="5" class="text-center text-muted py-3">No appointments scheduled for today.</td></tr>';
        exit;
    }
    $dentist_id = (int)$dent['dentist_id'];

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
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo '<tr><td colspan="5" class="text-center text-muted py-3">No appointments scheduled for today.</td></tr>';
        exit;
    }

    while ($apt = $res->fetch_assoc()) {
        ?>
        <tr>
            <td><?php echo formatTime($apt['appointment_time']); ?></td>
            <td><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></td>
            <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
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
    error_log('api-today-appointments dentist error: ' . $e->getMessage());
    echo '<tr><td colspan="5" class="text-center text-muted py-3">Unable to load appointments.</td></tr>';
}

