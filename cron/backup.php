<?php
/**
 * Denthub Dental Clinic - Automated Database Backup
 * Run every 5 hours via Render Cron or external cron (e.g. cron-job.org)
 * URL: https://your-app.onrender.com/cron/backup.php?key=YOUR_BACKUP_CRON_KEY
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

$key = $_GET['key'] ?? '';
$expectedKey = getenv('BACKUP_CRON_KEY') ?: '';

if (empty($expectedKey) || $key !== $expectedKey) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = getDB();
    
    // Ensure database_backups table exists
    $db->query("
        CREATE TABLE IF NOT EXISTS database_backups (
            backup_id SERIAL PRIMARY KEY,
            backup_name VARCHAR(255) NOT NULL,
            backup_size INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            content TEXT
        )
    ");

    $backupName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $content = '';

    // Try pg_dump first (available in Docker with postgresql-client)
    $host = DB_HOST;
    $port = DB_PORT;
    $dbname = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;

    $env = getenv('PGPASSWORD');
    putenv('PGPASSWORD=' . $pass);
    $cmd = sprintf(
        'pg_dump -h %s -p %d -U %s -d %s --no-owner --no-acl 2>/dev/null',
        escapeshellarg($host),
        $port,
        escapeshellarg($user),
        escapeshellarg($dbname)
    );
    $content = @shell_exec($cmd);
    if ($env !== false) {
        putenv('PGPASSWORD=' . $env);
    } else {
        putenv('PGPASSWORD');
    }

    // Fallback: PHP-based export if pg_dump fails
    if (empty($content) || strlen($content) < 100) {
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s', $host, $port, $dbname, DB_SSLMODE ?? 'require'),
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $content = "-- Denthub Backup (PHP fallback)\n-- " . date('Y-m-d H:i:s') . "\n\n";
        $tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $content .= "\n-- Table: $table\n";
            $rows = $pdo->query("SELECT * FROM \"$table\"")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $vals = array_map(function ($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote($v);
                }, array_values($row));
                $content .= 'INSERT INTO "' . $table . '" ("' . implode('","', $cols) . '") VALUES (' . implode(',', $vals) . ");\n";
            }
        }
    }

    $content = $content ?: '-- Empty backup';
    $size = strlen($content);

    $stmt = $db->prepare("INSERT INTO database_backups (backup_name, backup_size, content) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $backupName, $size, $content);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Backup created successfully',
        'backup_name' => $backupName,
        'size' => $size
    ]);
} catch (Exception $e) {
    error_log('Backup error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
