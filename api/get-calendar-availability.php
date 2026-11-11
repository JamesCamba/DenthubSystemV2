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
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)appTodayImmutable()->format('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)appTodayImmutable()->format('n');

if ($branch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'branch_id is required']);
    exit;
}
if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
    echo json_encode(['success' => false, 'message' => 'Invalid month']);
    exit;
}

try {
    $dentist_id = null;
    $allowToday = true;
    if ($role === 'dentist') {
        requireRole('dentist');
        $db = getDB();
        $stmt = $db->prepare("SELECT d.dentist_id FROM dentists d WHERE d.user_id = ?");
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
        $allowToday = false;
    } else {
        requireStaff();
    }

    $payload = getCalendarAvailabilityDays($year, $month, $branch_id, $dentist_id, $allowToday);
    echo json_encode([
        'success' => true,
        'year' => $payload['year'],
        'month' => $payload['month'],
        'branch_id' => $branch_id,
        'min_date' => $payload['min_date'],
        'max_date' => $payload['max_date'],
        'days' => $payload['days'],
    ]);
} catch (Throwable $e) {
    error_log('get-calendar-availability error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
