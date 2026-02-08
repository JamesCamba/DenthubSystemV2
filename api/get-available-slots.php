<?php
/**
 * API: Get Available Time Slots
 */
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/functions.php';

$date = $_GET['date'] ?? '';
$dentist_id = !empty($_GET['dentist_id']) ? intval($_GET['dentist_id']) : null;
$branch_id = !empty($_GET['branch_id']) ? intval($_GET['branch_id']) : 1;
$exclude_appointment_id = !empty($_GET['exclude_appointment_id']) ? intval($_GET['exclude_appointment_id']) : null;

if (empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Date is required']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Check if date is in the past
if (strtotime($date) < strtotime('today')) {
    echo json_encode(['success' => false, 'message' => 'Cannot book appointments in the past']);
    exit;
}

// Check if date is blocked for this branch
if (isDateBlocked($date, $branch_id)) {
    echo json_encode(['success' => false, 'message' => 'Date is blocked', 'slots' => []]);
    exit;
}

$slots = getAvailableTimeSlots($date, $dentist_id, null, $branch_id, $exclude_appointment_id);
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

