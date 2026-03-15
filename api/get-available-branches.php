<?php
/**
 * API: Get branches that have any available slots for a given date.
 * Uses PostgreSQL to aggregate availability per branch.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';

if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing date']);
    exit;
}

// Past dates and blocked dates are handled at branch-level below.

try {
    $db = getDB();

    $sql = "
        WITH day AS (
            SELECT ?::date AS d
        ),
        active_branches AS (
            SELECT b.branch_id, b.branch_name, b.address
            FROM branches b
            WHERE b.is_active = TRUE
        ),
        blocked AS (
            SELECT branch_id
            FROM blocked_dates bd
            JOIN day ON bd.block_date = day.d
            WHERE bd.is_active = TRUE
        ),
        available AS (
            SELECT
                ab.branch_id,
                COUNT(*) AS slots_count
            FROM active_branches ab
            JOIN day ON TRUE
            JOIN time_slots ts
              ON ts.is_active = TRUE
            JOIN dentist_schedules ds
              ON ds.day_of_week = EXTRACT(DOW FROM day.d)
             AND ds.is_available = TRUE
             AND ds.start_time <= ts.slot_time
             AND ds.end_time   > ts.slot_time
            JOIN dentists dent
              ON dent.dentist_id = ds.dentist_id
            JOIN users u
              ON u.user_id = dent.user_id
            WHERE dent.branch_id = ab.branch_id
              AND dent.is_active = TRUE
              AND u.is_active = TRUE
              AND NOT EXISTS (
                    SELECT 1
                    FROM blocked_dates bd2
                    WHERE bd2.block_date = day.d
                      AND bd2.branch_id = ab.branch_id
                      AND bd2.is_active = TRUE
                )
              AND NOT EXISTS (
                    SELECT 1
                    FROM appointments a
                    WHERE a.appointment_date = day.d
                      AND a.appointment_time = ts.slot_time
                      AND a.branch_id = ab.branch_id
                      AND a.status IN ('pending','confirmed')
                      AND a.dentist_id = dent.dentist_id
                )
            GROUP BY ab.branch_id
        )
        SELECT
            ab.branch_id,
            ab.branch_name,
            ab.address,
            COALESCE(av.slots_count, 0) AS slots_count
        FROM active_branches ab
        LEFT JOIN available av ON av.branch_id = ab.branch_id
        ORDER BY ab.branch_name;
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $res = $stmt->get_result();

    $branches = [];
    while ($row = $res->fetch_assoc()) {
        $branches[] = [
            'branch_id' => (int)$row['branch_id'],
            'branch_name' => $row['branch_name'],
            'address' => $row['address'],
            'slots' => (int)$row['slots_count'],
            'has_slots' => ((int)$row['slots_count'] > 0),
        ];
    }

    echo json_encode([
        'success' => true,
        'date' => $date,
        'branches' => $branches,
    ]);
} catch (Throwable $e) {
    error_log('get-available-branches error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

