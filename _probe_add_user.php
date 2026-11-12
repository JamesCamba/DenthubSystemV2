<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/database.php';

echo 'php ' . PHP_VERSION . PHP_EOL;
$db = getDB();
$u = 'brandnew_' . bin2hex(random_bytes(3));
$e = $u . '@gmail.com';
$stmt = $db->prepare('SELECT user_id FROM users WHERE username = ? OR email = ?');
$stmt->bind_param('ss', $u, $e);
$stmt->execute();
$res = $stmt->get_result();
echo 'prop=' . var_export($res->num_rows, true) . ' method=' . $res->num_rows() . PHP_EOL;

$ref = new ReflectionClass('MySQLiCompatibleConnection');
$pdoProp = $ref->getProperty('pdo');
$pdoProp->setAccessible(true);
$dbref = new ReflectionProperty('Database', 'connection');
$dbref->setAccessible(true);
$pdo = $pdoProp->getValue($dbref->getValue(Database::getInstance()));

foreach ($pdo->query("SELECT 'sched' AS t, MAX(schedule_id)::text AS m FROM dentist_schedules
    UNION ALL SELECT 'mastery', MAX(mastery_id)::text FROM dentist_service_mastery
    UNION ALL SELECT 'dentists', MAX(dentist_id)::text FROM dentists
    UNION ALL SELECT 'users', MAX(user_id)::text FROM users") as $r) {
    echo $r['t'] . ' max=' . $r['m'] . PHP_EOL;
}
echo "SEQS\n";
foreach ($pdo->query("SELECT sequencename, last_value FROM pg_sequences WHERE sequencename IN ('dentist_schedules_schedule_id_seq','dentist_service_mastery_mastery_id_seq','dentists_dentist_id_seq','users_user_id_seq')") as $r) {
    echo $r['sequencename'] . ' ' . $r['last_value'] . PHP_EOL;
}
echo "TRIGGER\n";
echo $pdo->query("SELECT pg_get_functiondef(p.oid) FROM pg_proc p WHERE p.proname = 'update_updated_at_column'")->fetchColumn();
echo "\nTRIGGER WHEN\n";
foreach ($pdo->query("SELECT tgname, pg_get_triggerdef(oid) FROM pg_trigger WHERE tgrelid = 'users'::regclass AND NOT tgisinternal") as $r) {
    echo $r['tgname'] . ' | ' . $r['pg_get_triggerdef'] . PHP_EOL;
}

echo "BLANK USERS\n";
foreach ($pdo->query("SELECT user_id, length(username) AS ul, length(email) AS el, username, email FROM users WHERE username = '' OR email = '' OR username IS NULL OR email IS NULL") as $r) {
    echo json_encode($r) . PHP_EOL;
}

echo "FULL ADMIN INSERT\n";
try {
    $hash = password_hash('temp1234', PASSWORD_DEFAULT);
    $phone = null;
    $phoneHash = null;
    $role = 'admin';
    $full = 'Probe Admin';
    $branchId = 1;
    $ins = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role, branch_id, phone, phone_hash, is_active, must_change_password)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, TRUE)
        RETURNING user_id, created_at");
    $ins->bind_param('sssssiss', $u, $e, $hash, $full, $role, $branchId, $phone, $phoneHash);
    $ok = $ins->execute();
    $row = $ins->get_result()->fetch_assoc();
    echo 'inserted ' . ($row['user_id'] ?? 'none') . ' ok=' . var_export($ok, true) . PHP_EOL;
    $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([(int)$row['user_id']]);
    echo "deleted probe user\n";
} catch (Throwable $ex) {
    echo 'ERR ' . $ex->getMessage() . PHP_EOL;
}
