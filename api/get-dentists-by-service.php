<?php
/**
 * API: Get dentists by branch + service (assigned to branch and with service mastery)
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$service_id = intval($_GET['service_id'] ?? 0);
$branch_id = !empty($_GET['branch_id']) ? intval($_GET['branch_id']) : null;
$date = $_GET['date'] ?? '';

if ($service_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
    exit;
}

// Backwards-compatible: if no date is provided, fall back to simple service/branch filter.
if (empty($date)) {
    $dentists = getDentists(true, $service_id, $branch_id);
    $result = [];
    while ($dentist = $dentists->fetch_assoc()) {
        $result[] = [
            'dentist_id' => $dentist['dentist_id'],
            'full_name' => $dentist['full_name'],
            'email' => $dentist['email'],
            'phone' => $dentist['phone']
        ];
    }
    echo json_encode(['success' => true, 'dentists' => $result]);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
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
                u.full_name,
                u.email,
                u.phone,
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
            JOIN dentist_service_mastery dsm
              ON dsm.dentist_id = dent.dentist_id
            JOIN services s
              ON s.service_id = dsm.service_id
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
            WHERE s.service_id = ?
              AND dent.is_active = TRUE
              AND u.is_active = TRUE
              AND s.is_active = TRUE
        )
        SELECT DISTINCT
            w.dentist_id,
            w.full_name,
            w.email,
            w.phone
        FROM windows w
        JOIN time_slots ts
          ON ts.is_active = TRUE
         AND ts.slot_time >= w.start_time
         AND ts.slot_time <  w.end_time
        WHERE w.start_time < w.end_time
    ";

    $params = [$date, $service_id];
    $types  = "si";

    if ($branch_id !== null && $branch_id > 0) {
        $sql .= " AND w.branch_id = ? ";
        $params[] = $branch_id;
        $types   .= "i";
    }

    $sql .= "
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
        ORDER BY w.full_name
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $result = [];
    while ($row = $res->fetch_assoc()) {
        $result[] = [
            'dentist_id' => (int)$row['dentist_id'],
            'full_name' => $row['full_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
        ];
    }

    echo json_encode(['success' => true, 'dentists' => $result]);
} catch (Throwable $e) {
    error_log('get-dentists-by-service error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

