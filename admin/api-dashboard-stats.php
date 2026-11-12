<?php
/**
 * JSON stats and chart series for the admin dashboard.
 * The 14-day window is always the 14 days ending today (inclusive).
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    $today = new DateTime('today');
    $start = (clone $today)->modify('-13 days');
    $todayStr = $today->format('Y-m-d');
    $startStr = $start->format('Y-m-d');

    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM appointments WHERE appointment_date = ? AND status IN ('pending', 'confirmed')");
    $stmt->bind_param('s', $todayStr);
    $stmt->execute();
    $todayAppointments = (int)$stmt->get_result()->fetch_assoc()['count'];

    $statusCounts = [
        'pending' => 0,
        'confirmed' => 0,
        'cancelled' => 0,
        'no_show' => 0,
    ];
    $res = $db->query("SELECT status, COUNT(*) AS count FROM appointments WHERE status IN ('pending', 'confirmed', 'cancelled', 'no_show') GROUP BY status");
    while ($row = $res->fetch_assoc()) {
        $statusCounts[$row['status']] = (int)$row['count'];
    }

    $patients = (int)$db->query("SELECT COUNT(*) AS count FROM patients")->fetch_assoc()['count'];

    $serviceRows = [];
    $stmt = $db->prepare("SELECT s.service_name, COUNT(*) AS count
                          FROM appointments a
                          JOIN services s ON a.service_id = s.service_id
                          WHERE a.appointment_date BETWEEN ? AND ?
                          GROUP BY s.service_name
                          ORDER BY count DESC, s.service_name ASC
                          LIMIT 8");
    $stmt->bind_param('ss', $startStr, $todayStr);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $serviceRows[] = [
            'name' => $row['service_name'],
            'count' => (int)$row['count'],
        ];
    }

    $byDay = [];
    $stmt = $db->prepare("SELECT TO_CHAR(appointment_date, 'YYYY-MM-DD') AS day, status, COUNT(*) AS count
                          FROM appointments
                          WHERE appointment_date BETWEEN ? AND ?
                            AND status IN ('pending', 'confirmed', 'cancelled', 'no_show')
                          GROUP BY appointment_date, status
                          ORDER BY appointment_date");
    $stmt->bind_param('ss', $startStr, $todayStr);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $byDay[$row['day']][$row['status']] = (int)$row['count'];
    }

    $labels = [];
    $series = [
        'pending' => [],
        'confirmed' => [],
        'cancelled' => [],
        'no_show' => [],
        'total' => [],
    ];
    $cursor = clone $start;
    while ($cursor <= $today) {
        $key = $cursor->format('Y-m-d');
        $labels[] = $cursor->format('M j');
        $day = $byDay[$key] ?? [];
        $pending = (int)($day['pending'] ?? 0);
        $confirmed = (int)($day['confirmed'] ?? 0);
        $cancelled = (int)($day['cancelled'] ?? 0);
        $noShow = (int)($day['no_show'] ?? 0);
        $series['pending'][] = $pending;
        $series['confirmed'][] = $confirmed;
        $series['cancelled'][] = $cancelled;
        $series['no_show'][] = $noShow;
        $series['total'][] = $pending + $confirmed + $cancelled + $noShow;
        $cursor->modify('+1 day');
    }

    echo json_encode([
        'success' => true,
        'today_appointments' => $todayAppointments,
        'pending' => $statusCounts['pending'],
        'cancelled' => $statusCounts['cancelled'],
        'no_shows' => $statusCounts['no_show'],
        'confirmed' => $statusCounts['confirmed'],
        'total_patients' => $patients,
        'range' => [
            'start' => $startStr,
            'end' => $todayStr,
            'label' => $start->format('M j') . ' – ' . $today->format('M j'),
        ],
        'status_mix' => $statusCounts,
        'top_services' => $serviceRows,
        'appointments_by_day' => [
            'labels' => $labels,
            'series' => $series,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load dashboard stats.']);
}
