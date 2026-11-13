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
        $stmt = $db->prepare("SELECT d.dentist_id FROM dentists d WHERE d.user_id = ?");
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

    // Resolve clinic operating hours once (with PHP fallback defaults).
    [$open_time, $close_time] = getClinicOperatingHours($branch_id);

    // Build slots per dentist using the hierarchical schedule logic.
    $sql = "
        WITH active_dentists AS (
            SELECT d.dentist_id, u.full_name
            FROM dentists d
            JOIN users u ON u.user_id = d.user_id
            WHERE d.is_active = TRUE
              AND u.is_active = TRUE
              AND d.branch_id = ?
    ";

    $params = [$branch_id];
    $types  = "i";

    if ($dentist_id_filter !== null) {
        $sql .= " AND d.dentist_id = ? ";
        $params[] = $dentist_id_filter;
        $types   .= "i";
    }

    $sql .= "
        ),
        windows AS (
            SELECT
                ad.dentist_id,
                ad.full_name,
                COALESCE(ms.start_time,
                         ds.start_time,
                         GREATEST(?::time, TIME '10:00:00')) AS start_time,
                COALESCE(ms.end_time,
                         ds.end_time,
                         LEAST(?::time, TIME '18:00:00')) AS end_time
            FROM active_dentists ad
            LEFT JOIN doctor_monthly_schedules ms
              ON ms.doctor_id = ad.dentist_id
             AND ms.schedule_date = ?
             AND ms.branch_id = ?
            LEFT JOIN dentist_schedules ds
              ON ds.dentist_id = ad.dentist_id
             AND ds.day_of_week = EXTRACT(DOW FROM ?::date)
             AND ds.is_available = TRUE
        )
        SELECT
            w.dentist_id,
            w.full_name AS dentist_name,
            ts.slot_time
        FROM windows w
        JOIN time_slots ts
          ON ts.is_active = TRUE
         AND ts.slot_time >= w.start_time
         AND ts.slot_time <  w.end_time
        WHERE w.start_time < w.end_time
          AND NOT EXISTS (
                SELECT 1
                FROM appointments a
                WHERE a.appointment_date = ?
                  AND a.appointment_time = ts.slot_time
                  AND a.branch_id = ?
                  AND a.status IN ('pending', 'confirmed')
                  AND a.dentist_id = w.dentist_id
          )
        ORDER BY w.full_name, ts.slot_time
    ";

    $params[] = $open_time;   // for GREATEST(open_time, '10:00')
    $params[] = $close_time;  // for LEAST(close_time, '18:00')
    $params[] = $date;
    $params[] = $branch_id;
    $params[] = $date;
    $params[] = $date;
    $params[] = $branch_id;
    $types   .= "sssissi";

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

