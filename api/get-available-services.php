<?php
/**
 * API: Get services that have any availability for a given date and branch.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isPatientLoggedIn() && !isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$date = $_GET['date'] ?? '';
$branch_id = !empty($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

if (empty($date) || !parseBookingDate($date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing date']);
    exit;
}
if ($branch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid branch']);
    exit;
}

$allow_today = isLoggedIn() && in_array($_SESSION['role'] ?? '', ['admin', 'staff', 'dentist'], true);
if (!isDateInBookingWindow($date, $allow_today)) {
    echo json_encode(['success' => false, 'message' => 'Date is outside the 3-month booking window', 'services' => []]);
    exit;
}

try {
    $services = [];
    $result = getServices(true);
    while ($row = $result->fetch_assoc()) {
        $serviceId = (int)$row['service_id'];
        $slots = 0;
        foreach (getDentistsWorkingAtBranchOnDate($branch_id, $date, null, $serviceId) as $dent) {
            $slots += count(computeAvailableTimeSlots($date, (int)$dent['dentist_id'], $serviceId, $branch_id));
        }
        $services[] = [
            'service_id' => $serviceId,
            'service_name' => $row['service_name'],
            'slots' => $slots,
            'has_slots' => $slots > 0,
        ];
    }

    echo json_encode([
        'success' => true,
        'date' => $date,
        'branch_id' => $branch_id,
        'services' => $services,
    ]);
} catch (Throwable $e) {
    error_log('get-available-services error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
