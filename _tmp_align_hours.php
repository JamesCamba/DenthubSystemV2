<?php
require __DIR__ . '/includes/config.php';

$endpoint = explode('.', DB_HOST)[0];
$dsn = sprintf(
    "pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;options='endpoint=%s'",
    DB_HOST,
    DB_PORT,
    DB_NAME,
    DB_SSLMODE,
    $endpoint
);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$statements = [
    "INSERT INTO time_slots (slot_time, is_active)
     SELECT gs::time, TRUE
     FROM generate_series(
         TIMESTAMP '2000-01-01 10:00:00',
         TIMESTAMP '2000-01-01 17:30:00',
         INTERVAL '30 minutes'
     ) AS gs
     ON CONFLICT (slot_time) DO UPDATE SET is_active = TRUE",
    "UPDATE time_slots
     SET is_active = FALSE
     WHERE slot_time < TIME '10:00:00'
        OR slot_time > TIME '17:30:00'",
    "INSERT INTO clinic_operating_hours (branch_id, open_time, close_time)
     SELECT b.branch_id, TIME '10:00:00', TIME '18:00:00'
     FROM branches b
     ON CONFLICT (branch_id) DO UPDATE
     SET open_time = EXCLUDED.open_time,
         close_time = EXCLUDED.close_time,
         updated_at = CURRENT_TIMESTAMP",
    "UPDATE dentist_schedules
     SET start_time = TIME '10:00:00',
         end_time = TIME '18:00:00',
         is_available = TRUE
     WHERE start_time = TIME '09:00:00'
       AND end_time = TIME '17:00:00'",
    "INSERT INTO dentist_schedules (dentist_id, day_of_week, start_time, end_time, is_available)
     SELECT d.dentist_id, dow.day_of_week, TIME '10:00:00', TIME '18:00:00', TRUE
     FROM dentists d
     CROSS JOIN (SELECT generate_series(0, 6) AS day_of_week) dow
     WHERE d.is_active = TRUE
       AND NOT EXISTS (
           SELECT 1 FROM dentist_schedules ds
           WHERE ds.dentist_id = d.dentist_id
             AND ds.day_of_week = dow.day_of_week
       )",
];

foreach ($statements as $i => $sql) {
    $n = $pdo->exec($sql);
    echo "stmt $i rows=$n\n";
}

echo "== hours ==\n";
foreach ($pdo->query("SELECT branch_id, open_time::text, close_time::text FROM clinic_operating_hours ORDER BY branch_id") as $row) {
    echo json_encode($row), "\n";
}
echo "== schedules ==\n";
foreach ($pdo->query("SELECT day_of_week, start_time::text, end_time::text, is_available FROM dentist_schedules ORDER BY dentist_id, day_of_week") as $row) {
    echo json_encode($row), "\n";
}
echo "== active slots ==\n";
foreach ($pdo->query("SELECT slot_time::text FROM time_slots WHERE is_active = TRUE ORDER BY slot_time") as $row) {
    echo $row['slot_time'], "\n";
}

$sql = "
WITH dates AS (
    SELECT d::date AS d FROM (VALUES ('2026-11-15'), ('2026-11-16'), ('2026-11-20')) v(d)
),
windows AS (
    SELECT
        dates.d,
        GREATEST(
            COALESCE(ms.start_time, ds.start_time, TIME '10:00:00'),
            TIME '10:00:00'
        ) AS start_time,
        LEAST(
            COALESCE(ms.end_time, ds.end_time, TIME '18:00:00'),
            TIME '18:00:00'
        ) AS end_time
    FROM dates
    JOIN dentists dent ON dent.is_active = TRUE
    LEFT JOIN doctor_monthly_schedules ms
      ON ms.doctor_id = dent.dentist_id AND ms.schedule_date = dates.d AND ms.branch_id = dent.branch_id
    LEFT JOIN dentist_schedules ds
      ON ds.dentist_id = dent.dentist_id
     AND ds.day_of_week = EXTRACT(DOW FROM dates.d)
     AND ds.is_available = TRUE
)
SELECT w.d::text AS date,
       MIN(ts.slot_time)::text AS first_slot,
       MAX(ts.slot_time)::text AS last_slot,
       COUNT(*) AS n
FROM windows w
JOIN time_slots ts
  ON ts.is_active = TRUE
 AND ts.slot_time >= w.start_time
 AND ts.slot_time < w.end_time
GROUP BY w.d
ORDER BY w.d
";
echo "== sample days ==\n";
foreach ($pdo->query($sql) as $row) {
    echo json_encode($row), "\n";
}
