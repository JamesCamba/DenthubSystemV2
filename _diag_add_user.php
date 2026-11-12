<?php
require __DIR__ . '/includes/config.php';

$endpoint = explode('.', DB_HOST)[0];
$endpoint = preg_replace('/-pooler$/', '', $endpoint);
$dsn = sprintf(
    'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;options=endpoint=%s',
    DB_HOST,
    DB_PORT,
    DB_NAME,
    DB_SSLMODE,
    $endpoint
);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

echo "---CONSTRAINTS---\n";
foreach ($pdo->query("SELECT t.relname AS tbl, c.conname, pg_get_constraintdef(c.oid) AS def
    FROM pg_constraint c
    JOIN pg_class t ON t.oid = c.conrelid
    WHERE t.relname IN ('users','dentists','dentist_schedules','dentist_service_mastery')
    ORDER BY t.relname, c.conname") as $r) {
    echo $r['tbl'] . ' | ' . $r['conname'] . ' | ' . $r['def'] . PHP_EOL;
}

echo "---INDEXES---\n";
foreach ($pdo->query("SELECT tablename, indexname, indexdef FROM pg_indexes WHERE tablename IN ('users','dentists','dentist_schedules')") as $r) {
    echo $r['tablename'] . ' | ' . $r['indexname'] . ' | ' . $r['indexdef'] . PHP_EOL;
}

echo "---SEQ---\n";
print_r($pdo->query("SELECT (SELECT MAX(user_id) FROM users) AS max_user,
    (SELECT last_value FROM users_user_id_seq) AS seq_user,
    (SELECT is_called FROM users_user_id_seq) AS seq_called,
    (SELECT MAX(dentist_id) FROM dentists) AS max_dentist,
    (SELECT last_value FROM dentists_dentist_id_seq) AS seq_dentist,
    (SELECT COUNT(*) FROM users) AS user_count,
    (SELECT COUNT(*) FROM branches WHERE is_active = TRUE) AS branches")->fetch());

echo "---ROLES---\n";
foreach ($pdo->query('SELECT role, COUNT(*) AS n FROM users GROUP BY role') as $r) {
    echo $r['role'] . ' ' . $r['n'] . PHP_EOL;
}

echo "---TRIGGERS---\n";
foreach ($pdo->query("SELECT event_object_table, trigger_name, action_statement
    FROM information_schema.triggers
    WHERE event_object_table IN ('users','dentists')") as $r) {
    echo $r['event_object_table'] . ' | ' . $r['trigger_name'] . ' | ' . $r['action_statement'] . PHP_EOL;
}

echo "---COLUMNS users---\n";
foreach ($pdo->query("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = 'users' ORDER BY ordinal_position") as $r) {
    echo $r['column_name'] . ' ' . $r['data_type'] . ' null=' . $r['is_nullable'] . ' def=' . $r['column_default'] . PHP_EOL;
}

$probeUser = 'diag_user_' . bin2hex(random_bytes(4));
$probeEmail = $probeUser . '@gmail.com';
echo "---IDS---\n";
foreach ($pdo->query('SELECT dentist_id, user_id FROM dentists ORDER BY dentist_id') as $r) {
    echo 'dentist ' . $r['dentist_id'] . ' user ' . $r['user_id'] . PHP_EOL;
}
foreach ($pdo->query('SELECT user_id, username, role FROM users ORDER BY user_id') as $r) {
    echo 'user ' . $r['user_id'] . ' ' . $r['username'] . ' ' . $r['role'] . PHP_EOL;
}
echo "---ALL SEQ---\n";
foreach ($pdo->query("SELECT sequencename, last_value FROM pg_sequences WHERE schemaname='public' ORDER BY sequencename") as $r) {
    echo $r['sequencename'] . ' ' . $r['last_value'] . PHP_EOL;
}

require __DIR__ . '/includes/database.php';
echo "---WRAPPER---\n";
try {
    $wrap = new MySQLiCompatibleConnection($pdo);
    $probeUser = 'wrap_' . bin2hex(random_bytes(3));
    $probeEmail = $probeUser . '@gmail.com';
    $stmt = $wrap->prepare('SELECT user_id FROM users WHERE username = ? OR email = ?');
    $stmt->bind_param('ss', $probeUser, $probeEmail);
    $stmt->execute();
    $res = $stmt->get_result();
    echo 'wrapper dup rows ' . $res->num_rows . PHP_EOL;

    $hash = password_hash('temp1234', PASSWORD_DEFAULT);
    $phone = null;
    $phoneHash = null;
    $role = 'admin';
    $full = 'Wrap User';
    $branchId = 1;
    $pdo->beginTransaction();
    $ins = $wrap->prepare('INSERT INTO users (username, email, password_hash, full_name, role, branch_id, phone, phone_hash, is_active, must_change_password)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, TRUE)
        RETURNING user_id, created_at');
    $ins->bind_param('sssssiss', $probeUser, $probeEmail, $hash, $full, $role, $branchId, $phone, $phoneHash);
    $ok = $ins->execute();
    echo 'wrapper execute ' . var_export($ok, true) . PHP_EOL;
    $row = $ins->get_result()->fetch_assoc();
    echo 'wrapper user_id ' . ($row['user_id'] ?? 'none') . PHP_EOL;
    $pdo->rollBack();
    echo "wrapper rolled back\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'WRAPPER ERR: ' . $e->getMessage() . PHP_EOL;
}

echo "---PROBE---\n";
try {
    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$probeUser, $probeEmail]);
    echo 'dup rows: ' . count($stmt->fetchAll()) . PHP_EOL;

    $branch = $pdo->query('SELECT branch_id FROM branches WHERE is_active = TRUE ORDER BY branch_id LIMIT 1')->fetch();
    $branchId = (int)($branch['branch_id'] ?? 0);
    echo 'branch: ' . $branchId . PHP_EOL;
    $hash = password_hash('temp1234', PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, full_name, role, branch_id, phone, phone_hash, is_active, must_change_password)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, TRUE)
        RETURNING user_id, created_at');
    $ins->execute([$probeUser, $probeEmail, $hash, 'Diag User', 'admin', $branchId, null, null]);
    $row = $ins->fetch();
    echo 'inserted user_id=' . ($row['user_id'] ?? 'none') . PHP_EOL;

    $insD = $pdo->prepare('INSERT INTO dentists (user_id, license_number, specialization, branch_id, is_active)
        VALUES (?, ?, ?, ?, TRUE) RETURNING dentist_id');
    $insD->execute([(int)$row['user_id'], '', 'General', $branchId]);
    $d = $insD->fetch();
    echo 'inserted dentist_id=' . ($d['dentist_id'] ?? 'none') . PHP_EOL;

    $pdo->rollBack();
    echo "rolled back\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
}
