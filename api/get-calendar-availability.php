<?php
/**
 * API: Calendar availability by day for a month.
 * Params: branch_id, role=admin|dentist|patient, year, month
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
$role = $_GET['role'] ?? '';
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

if ($branch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'branch_id is required']);
    exit;
}

try {
    $db = getDB();

    $dentist_id = null;
    if ($role === 'dentist') {
        requireRole('dentist');
        $stmt = $db->prepare("SELECT d.dentist_id, d.branch_id FROM dentists d WHERE d.user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $dent = $stmt->get_result()->fetch_assoc();
        if (!$dent) {
            echo json_encode(['success' => false, 'message' => 'Dentist not found']);
            exit;
        }
        $dentist_id = (int)$dent['dentist_id'];
    } elseif ($role === 'patient') {
        requirePatientLogin();
    } else {
        // admin or unspecified -> treat as admin
        requireLogin();
    }

    $days = [];
    $start = sprintf('%04d-%02d-01', $year, $month);
    $start_ts = strtotime($start);
    $days_in_month = (int)date('t', $start_ts);

    for ($d = 1; $d <= $days_in_month; $d++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $blocked = isDateBlocked($date, $branch_id);
        $slots = $blocked ? 0 : getAvailableSlotCountForDateAndBranch($date, $branch_id, $dentist_id);
        $item = [
            'date' => $date,
            'slots' => $slots,
            'blocked' => $blocked,
        ];
        if ($blocked) {
            $item['reason'] = getBlockReason($date, $branch_id);
        }
        $days[] = $item;
    }

    echo json_encode([
        'success' => true,
        'year' => $year,
        'month' => $month,
        'branch_id' => $branch_id,
        'days' => $days,
    ]);
} catch (Throwable $e) {
    error_log('get-calendar-availability error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

