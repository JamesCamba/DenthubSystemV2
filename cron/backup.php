<?php
/**
 * Denthub Dental Clinic - Automated Database Backup
 * Run every 5 hours via Render Cron or external cron (e.g. cron-job.org)
 * URL: https://your-app.onrender.com/cron/backup.php?key=YOUR_BACKUP_CRON_KEY
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

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

    $backupName = 'backup_' . appNow() . '.sql';
    // Current clinic data only. database_backups is left out so older archives are not copied.
    $content = generateDatabaseBackupContent();
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
