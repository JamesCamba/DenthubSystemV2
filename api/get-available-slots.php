<?php
/**
 * API: Get Available Time Slots
 */
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!isPatientLoggedIn() && !isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$date = $_GET['date'] ?? '';
$dentist_id = !empty($_GET['dentist_id']) ? intval($_GET['dentist_id']) : null;
$service_id = !empty($_GET['service_id']) ? intval($_GET['service_id']) : null;
$branch_id = !empty($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
$exclude_appointment_id = !empty($_GET['exclude_appointment_id']) ? intval($_GET['exclude_appointment_id']) : null;
$allow_today = false;
if (isLoggedIn() && in_array($_SESSION['role'] ?? '', ['admin', 'staff', 'dentist'], true)) {
    $allow_today = true;
}

if (empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Date is required']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

if ($branch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Branch is required', 'slots' => []]);
    exit;
}

if ($dentist_id === null) {
    echo json_encode(['success' => false, 'message' => 'Dentist is required', 'slots' => []]);
    exit;
}

if (!isDateInBookingWindow($date, $allow_today)) {
    echo json_encode(['success' => false, 'message' => 'Date is outside the 3-month booking window', 'slots' => []]);
    exit;
}

if (isDateBlocked($date, $branch_id)) {
    echo json_encode(['success' => false, 'message' => 'Date is blocked', 'slots' => []]);
    exit;
}

$slots = getAvailableTimeSlots($date, $dentist_id, $service_id, $branch_id, $exclude_appointment_id);
$formatted_slots = [];

foreach ($slots as $slot) {
    $formatted_slots[] = [
        'slot_id' => $slot['slot_id'],
        'slot_time' => $slot['slot_time'],
        'display_time' => formatTime($slot['slot_time'])
    ];
}

echo json_encode([
    'success' => true,
    'slots' => $formatted_slots
]);
