<?php
/**
 * API: Detailed slots for a given day, grouped by dentist.
 * Params: branch_id, date, role, optional dentist_id
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
$date = $_GET['date'] ?? '';
$role = $_GET['role'] ?? '';

if ($branch_id <= 0 || $date === '') {
    echo json_encode(['success' => false, 'message' => 'branch_id and date are required']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

try {
    $db = getDB();
    $dentist_id_filter = null;

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
        $dentist_id_filter = (int)$dent['dentist_id'];
    } elseif ($role === 'patient') {
        requirePatientLogin();
    } else {
        requireLogin();
    }

    if (isDateBlocked($date, $branch_id)) {
        echo json_encode([
            'success' => true,
            'date' => $date,
            'branch_id' => $branch_id,
            'blocked' => true,
            'reason' => getBlockReason($date, $branch_id),
            'dentists' => [],
        ]);
        exit;
    }

    $day_of_week = date('w', strtotime($date));

    $sql = "
        SELECT d.dentist_id,
               u.full_name AS dentist_name,
               ts.slot_time
        FROM time_slots ts
        JOIN dentist_schedules ds
          ON ds.day_of_week = ?
         AND ds.is_available = TRUE
         AND ds.start_time <= ts.slot_time
         AND ds.end_time   > ts.slot_time
        JOIN dentists d
          ON d.dentist_id = ds.dentist_id
        JOIN users u
          ON u.user_id = d.user_id
        WHERE ts.is_active = TRUE
          AND d.branch_id = ?
          AND d.is_active = TRUE
          AND u.is_active = TRUE
          AND NOT EXISTS (
                SELECT 1
                FROM appointments a
                WHERE a.appointment_date = ?
                  AND a.appointment_time = ts.slot_time
                  AND a.branch_id = d.branch_id
                  AND a.status IN ('pending', 'confirmed')
                  AND a.dentist_id = d.dentist_id
          )
    ";

    $params = [$day_of_week, $branch_id, $date];
    $types  = "iis";

    if ($dentist_id_filter !== null) {
        $sql .= " AND d.dentist_id = ?";
        $params[] = $dentist_id_filter;
        $types   .= "i";
    }

    $sql .= " ORDER BY u.full_name, ts.slot_time";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $byDentist = [];
    while ($row = $res->fetch_assoc()) {
        $did = (int)$row['dentist_id'];
        if (!isset($byDentist[$did])) {
            $byDentist[$did] = [
                'dentist_id' => $did,
                'name' => $row['dentist_name'],
                'slots' => [],
            ];
        }
        $time = $row['slot_time'];
        $display = formatTime($time);
        $byDentist[$did]['slots'][] = [
            'time' => $time,
            'display' => $display,
        ];
    }

    echo json_encode([
        'success' => true,
        'date' => $date,
        'branch_id' => $branch_id,
        'blocked' => false,
        'dentists' => array_values($byDentist),
    ]);
} catch (Throwable $e) {
    error_log('get-day-slots error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

