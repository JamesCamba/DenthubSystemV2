<?php
/**
 * API: Dentists available for a branch, service, and date.
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isPatientLoggedIn() && !isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$service_id = intval($_GET['service_id'] ?? 0);
$branch_id = !empty($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
$date = $_GET['date'] ?? '';

if ($service_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
    exit;
}

if ($date === '' || $branch_id <= 0 || !parseBookingDate($date)) {
    echo json_encode(['success' => false, 'message' => 'Date and branch are required', 'dentists' => []]);
    exit;
}

$allow_today = isLoggedIn() && in_array($_SESSION['role'] ?? '', ['admin', 'staff', 'dentist'], true);
if (!isDateInBookingWindow($date, $allow_today)) {
    echo json_encode(['success' => false, 'message' => 'Date is outside the 3-month booking window', 'dentists' => []]);
    exit;
}

try {
    $result = [];
    foreach (getDentistsWorkingAtBranchOnDate($branch_id, $date, null, $service_id) as $dent) {
        $slots = computeAvailableTimeSlots($date, (int)$dent['dentist_id'], $service_id, $branch_id);
        if (!$slots) {
            continue;
        }
        $result[] = [
            'dentist_id' => (int)$dent['dentist_id'],
            'full_name' => $dent['full_name'],
            'email' => $dent['email'],
            'phone' => $dent['phone'],
        ];
    }
    echo json_encode(['success' => true, 'dentists' => $result]);
} catch (Throwable $e) {
    error_log('get-dentists-by-service error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
