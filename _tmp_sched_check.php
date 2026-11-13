<?php
require __DIR__ . '/includes/database.php';
$db = getDB();
$queries = [
    'hours' => 'SELECT branch_id, open_time::text, close_time::text FROM clinic_operating_hours ORDER BY branch_id',
    'schedules' => 'SELECT day_of_week, start_time::text, end_time::text, is_available, COUNT(*) AS n FROM dentist_schedules GROUP BY 1,2,3,4 ORDER BY 1',
    'slots' => 'SELECT slot_time::text, is_active FROM time_slots ORDER BY slot_time',
    'dentists' => 'SELECT dentist_id FROM dentists ORDER BY dentist_id',
];
foreach ($queries as $name => $sql) {
    echo "== $name ==\n";
    $r = $db->query($sql);
    while ($row = $r->fetch_assoc()) {
        echo json_encode($row), "\n";
    }
}
