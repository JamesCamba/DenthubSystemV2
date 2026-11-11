<?php
/**
 * API: Save or update a single doctor's monthly schedule entry for a specific date.
 * Method: POST
 * Params: date (Y-m-d), branch_id, start_time (HH:MM), end_time (HH:MM)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

requireRole('dentist');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$date = $_POST['date'] ?? '';
$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
$start_time = $_POST['start_time'] ?? '';
$end_time = $_POST['end_time'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date']);
    exit;
}

$today = appTodayImmutable()->format('Y-m-d');
if ($date < $today) {
    echo json_encode(['success' => false, 'message' => 'Cannot set schedule for past dates']);
    exit;
}

if ($branch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Branch is required']);
    exit;
}

// Normalize times to HH:MM:SS
function normalize_hhmm($t) {
    $t = trim($t);
    if ($t === '') return '';
    if (!preg_match('/^\d{2}:\d{2}/', $t)) {
        return '';
    }
    if (strlen($t) === 5) {
        $t .= ':00';
    }
    return substr($t, 0, 8);
}

$start_time_norm = normalize_hhmm($start_time);
$end_time_norm = normalize_hhmm($end_time);

if (!$start_time_norm || !$end_time_norm) {
    echo json_encode(['success' => false, 'message' => 'Start and end time are required']);
    exit;
}

if ($start_time_norm >= $end_time_norm) {
    echo json_encode(['success' => false, 'message' => 'Start time must be before end time']);
    exit;
}

$db = getDB();

// Get dentist id
$stmt = $db->prepare("SELECT dentist_id FROM dentists WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$dent = $stmt->get_result()->fetch_assoc();
if (!$dent) {
    echo json_encode(['success' => false, 'message' => 'Dentist not found']);
    exit;
}
$dentist_id = (int)$dent['dentist_id'];

// Enforce clinic operating hours
[$open_time, $close_time] = getClinicOperatingHours($branch_id);

if ($start_time_norm < $open_time || $end_time_norm > $close_time) {
    echo json_encode([
        'success' => false,
        'message' => 'Schedule must be within clinic operating hours (' . substr($open_time, 0, 5) . ' - ' . substr($close_time, 0, 5) . ')'
    ]);
    exit;
}

// Upsert monthly schedule (unique on doctor_id + schedule_date)
$stmt = $db->prepare("
    INSERT INTO doctor_monthly_schedules (doctor_id, schedule_date, branch_id, start_time, end_time)
    VALUES (?, ?, ?, ?, ?)
    ON CONFLICT (doctor_id, schedule_date) DO UPDATE
    SET branch_id = EXCLUDED.branch_id,
        start_time = EXCLUDED.start_time,
        end_time = EXCLUDED.end_time,
        updated_at = NOW()
");
$stmt->bind_param("isiss", $dentist_id, $date, $branch_id, $start_time_norm, $end_time_norm);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to save schedule']);
    exit;
}

echo json_encode(['success' => true]);

