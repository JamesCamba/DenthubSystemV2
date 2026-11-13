<?php
/**
 * API: Get services that have any availability for a given date and branch.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$branch_id = !empty($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing date']);
    exit;
}
if ($branch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid branch']);
    exit;
}

try {
    $db = getDB();

    $sql = "
        WITH day AS (
            SELECT ?::date AS d
        ),
        windows AS (
            SELECT
                dent.dentist_id,
                dent.branch_id,
                GREATEST(
                    COALESCE(coh.open_time, TIME '10:00:00'),
                    COALESCE(ms.start_time, ds.start_time, TIME '10:00:00')
                ) AS start_time,
                LEAST(
                    COALESCE(coh.close_time, TIME '18:00:00'),
                    COALESCE(ms.end_time, ds.end_time, TIME '18:00:00')
                ) AS end_time
            FROM dentists dent
            JOIN users u
              ON u.user_id = dent.user_id
            JOIN day ON TRUE
            LEFT JOIN clinic_operating_hours coh
              ON coh.branch_id = dent.branch_id
            LEFT JOIN doctor_monthly_schedules ms
              ON ms.doctor_id = dent.dentist_id
             AND ms.schedule_date = day.d
             AND ms.branch_id = dent.branch_id
            LEFT JOIN dentist_schedules ds
              ON ds.dentist_id = dent.dentist_id
             AND ds.day_of_week = EXTRACT(DOW FROM day.d)
             AND ds.is_available = TRUE
            WHERE dent.branch_id = ?
              AND dent.is_active = TRUE
              AND u.is_active = TRUE
        ),
        svc_avail AS (
            SELECT
                s.service_id,
                s.service_name,
                COUNT(*) AS slots_count
            FROM windows w
            JOIN dentist_service_mastery dsm
              ON dsm.dentist_id = w.dentist_id
            JOIN services s
              ON s.service_id = dsm.service_id
            JOIN time_slots ts
              ON ts.is_active = TRUE
             AND ts.slot_time >= w.start_time
             AND ts.slot_time <  w.end_time
            WHERE s.is_active = TRUE
              AND w.start_time < w.end_time
              AND NOT EXISTS (
                    SELECT 1
                    FROM blocked_dates bd
                    WHERE bd.block_date = (SELECT d FROM day)
                      AND bd.branch_id = w.branch_id
                      AND bd.is_active = TRUE
                )
              AND NOT EXISTS (
                    SELECT 1
                    FROM appointments a
                    WHERE a.appointment_date = (SELECT d FROM day)
                      AND a.appointment_time = ts.slot_time
                      AND a.branch_id = w.branch_id
                      AND a.status IN ('pending','confirmed')
                      AND a.dentist_id = w.dentist_id
                )
            GROUP BY s.service_id, s.service_name
        )
        SELECT
            s.service_id,
            s.service_name,
            COALESCE(sa.slots_count, 0) AS slots_count
        FROM services s
        LEFT JOIN svc_avail sa ON sa.service_id = s.service_id
        WHERE s.is_active = TRUE
        ORDER BY s.service_name;
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("si", $date, $branch_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $services = [];
    while ($row = $res->fetch_assoc()) {
        $services[] = [
            'service_id' => (int)$row['service_id'],
            'service_name' => $row['service_name'],
            'slots' => (int)$row['slots_count'],
            'has_slots' => ((int)$row['slots_count'] > 0),
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

