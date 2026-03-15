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
    } else {
        // admin or unspecified -> treat as admin
        requireLogin();
    }

    $start = sprintf('%04d-%02d-01', $year, $month);
    $days_in_month = (int)date('t', strtotime($start));
    $end = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);

    // Use a set-based query to compute daily availability with the hierarchical schedule model.
    $sql = "
        WITH days AS (
            SELECT generate_series(?::date, ?::date, interval '1 day')::date AS d
        ),
        active_dentists AS (
            SELECT d.dentist_id
            FROM dentists d
            JOIN users u ON u.user_id = d.user_id
            WHERE d.is_active = TRUE
              AND u.is_active = TRUE
    ";

    $params = [$start, $end];
    $types  = "ss";

    if ($dentist_id !== null) {
        $sql .= " AND d.dentist_id = ? ";
        $params[] = $dentist_id;
        $types   .= "i";
    }

    $sql .= "
        ),
        coh AS (
            SELECT open_time, close_time
            FROM clinic_operating_hours
            WHERE branch_id = ?
        ),
        windows AS (
            SELECT
                ad.dentist_id,
                dd.d AS date,
                COALESCE(ms.start_time,
                         ds.start_time,
                         GREATEST(coh.open_time, TIME '10:00:00')) AS start_time,
                COALESCE(ms.end_time,
                         ds.end_time,
                         LEAST(coh.close_time, TIME '18:00:00')) AS end_time
            FROM active_dentists ad
            CROSS JOIN days dd
            CROSS JOIN coh
            LEFT JOIN doctor_monthly_schedules ms
              ON ms.doctor_id = ad.dentist_id
             AND ms.schedule_date = dd.d
             AND ms.branch_id = ?
            LEFT JOIN dentist_schedules ds
              ON ds.dentist_id = ad.dentist_id
             AND ds.day_of_week = EXTRACT(DOW FROM dd.d)
             AND ds.is_available = TRUE
        ),
        available AS (
            SELECT
                w.date,
                COUNT(*) AS slots_count
            FROM windows w
            JOIN time_slots ts
              ON ts.is_active = TRUE
             AND ts.slot_time >= w.start_time
             AND ts.slot_time <  w.end_time
            WHERE w.start_time < w.end_time
              AND NOT EXISTS (
                    SELECT 1
                    FROM appointments a
                    WHERE a.appointment_date = w.date
                      AND a.appointment_time = ts.slot_time
                      AND a.branch_id = ?
                      AND a.status IN ('pending', 'confirmed')
                      AND a.dentist_id = w.dentist_id
                )
            GROUP BY w.date
        ),
        blocks AS (
            SELECT block_date, reason
            FROM blocked_dates
            WHERE branch_id = ?
              AND is_active = TRUE
              AND block_date BETWEEN ?::date AND ?::date
        )
        SELECT
            d.d AS date,
            COALESCE(a.slots_count, 0) AS slots,
            (b.block_date IS NOT NULL) AS blocked,
            b.reason
        FROM days d
        LEFT JOIN available a ON a.date = d.d
        LEFT JOIN blocks b ON b.block_date = d.d
        ORDER BY d.d
    ";

    $params[] = $branch_id; // coh
    $params[] = $branch_id; // ms.branch_id
    $params[] = $branch_id; // appointments.branch_id
    $params[] = $branch_id; // blocks.branch_id
    $params[] = $start;
    $params[] = $end;
    $types   .= "iiiiss";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $days = [];
    while ($row = $res->fetch_assoc()) {
        $days[] = [
            'date' => $row['date'],
            'slots' => (int)$row['slots'],
            'blocked' => (bool)$row['blocked'],
            'reason' => $row['reason'] ?? '',
        ];
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

