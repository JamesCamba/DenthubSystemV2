<?php
/**
 * Denthub Dental Clinic - Database Backups
 * View, export, and import backups
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireRole('admin');

$db = getDB();
$error = '';
$success = '';

// Ensure table exists
$db->query("
    CREATE TABLE IF NOT EXISTS database_backups (
        backup_id SERIAL PRIMARY KEY,
        backup_name VARCHAR(255) NOT NULL,
        backup_size INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        content TEXT
    )
");

// Download backup
if (isset($_GET['download']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT backup_name, content FROM database_backups WHERE backup_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . htmlspecialchars($row['backup_name']) . '"');
        echo $row['content'];
        exit;
    }
}

// Create backup now
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    $backupName = 'backup_' . appNow() . '.sql';
    $content = '';
    $host = DB_HOST;
    $port = DB_PORT;
    $dbname = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;

    try {
        putenv('PGPASSWORD=' . $pass);
        $cmd = sprintf(
            'pg_dump -h %s -p %d -U %s -d %s --no-owner --no-acl 2>/dev/null',
            escapeshellarg($host),
            $port,
            escapeshellarg($user),
            escapeshellarg($dbname)
        );
        $content = @shell_exec($cmd);
        putenv('PGPASSWORD');

        if (empty($content) || strlen($content) < 100) {
            $pdo = new PDO(
                sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s', $host, $port, $dbname, DB_SSLMODE ?? 'require'),
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $content = "-- Denthub Backup (PHP fallback)\n-- " . date('Y-m-d H:i:s', strtotime('now')) . " (" . (defined('TIMEZONE') ? TIMEZONE : 'Asia/Manila') . ")\n\n";
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

        if (!empty($content)) {
            $size = strlen($content);
            $stmt = $db->prepare("INSERT INTO database_backups (backup_name, backup_size, content) VALUES (?, ?, ?)");
            $stmt->bind_param("sis", $backupName, $size, $content);
            if ($stmt->execute()) {
                header('Location: backups.php?created=1');
                exit;
            }
        }
        $error = 'Backup failed. Ensure PostgreSQL client is available or check logs.';
    } catch (Exception $e) {
        error_log('Backup error: ' . $e->getMessage());
        $error = 'Backup failed: ' . $e->getMessage();
    }
}

// Import backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_backup'])) {
    $confirm = $_POST['import_confirm'] ?? '';
    if ($confirm !== 'IMPORT') {
        $error = 'You must type IMPORT to confirm.';
    } elseif (!empty($_FILES['backup_file']['tmp_name']) && is_uploaded_file($_FILES['backup_file']['tmp_name'])) {
        $sql = file_get_contents($_FILES['backup_file']['tmp_name']);
        if (empty($sql)) {
            $error = 'File is empty or invalid.';
        } else {
            try {
                $pdo = new PDO(
                    sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s', DB_HOST, DB_PORT, DB_NAME, DB_SSLMODE ?? 'require'),
                    DB_USER,
                    DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $pdo->beginTransaction();
                $statements = array_filter(array_map('trim', preg_split('/;(\s*\n|\r\n)/', $sql)));
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if (!empty($stmt) && !preg_match('/^--/', $stmt) && strlen($stmt) > 2) {
                        $pdo->exec($stmt . ';');
                    }
                }
                $pdo->commit();
                $success = 'Backup imported successfully.';
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Import failed: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Please select a backup file to import.';
    }
}

// Delete backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    $id = intval($_POST['backup_id']);
    $stmt = $db->prepare("DELETE FROM database_backups WHERE backup_id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = 'Backup deleted.';
    }
}

$backupsResult = $db->query("SELECT backup_id, backup_name, backup_size, created_at FROM database_backups ORDER BY created_at DESC");
$backupsList = [];
while ($row = $backupsResult->fetch_assoc()) {
    $backupsList[] = $row;
}
$cronUrl = rtrim(APP_URL, '/') . '/cron/backup.php';
$hasCronKey = !empty(getenv('BACKUP_CRON_KEY'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backups - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="denthub-main">
        <h1 class="denthub-page-title"><i class="bi bi-database me-2"></i> Database Backups</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($success || isset($_GET['created'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($success ?: 'Backup created successfully.'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="denthub-card-header">
                        <i class="bi bi-plus-circle me-2"></i> Create Backup
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="create_backup" value="1">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Create Backup Now</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="denthub-card-header">
                        <i class="bi bi-clock-history me-2"></i> Auto Backup (Every 5 Hours)
                    </div>
                    <div class="card-body">
                        <?php if ($hasCronKey): ?>
                            <p class="small text-muted">Add this URL to your cron service (e.g. cron-job.org) to run every 5 hours:</p>
                            <code class="d-block p-2 bg-light rounded small"><?php echo htmlspecialchars($cronUrl); ?>?key=<?php echo htmlspecialchars(getenv('BACKUP_CRON_KEY')); ?></code>
                            <p class="small text-muted mt-2">Set <code>BACKUP_CRON_KEY</code> in your environment variables.</p>
                        <?php else: ?>
                            <p class="text-warning">Set <code>BACKUP_CRON_KEY</code> in your environment variables to enable auto backup.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="denthub-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul me-2"></i> Backup List</span>
                <span class="badge bg-light text-dark"><?php echo count($backupsList); ?> backups</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 denthub-backup-list">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Date & Time</th>
                                <th>Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backupsList as $row): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($row['backup_name']); ?></code></td>
                                <td><?php echo formatDateTimeUtcToApp($row['created_at']); ?></td>
                                <td><?php echo number_format($row['backup_size'] / 1024, 1); ?> KB</td>
                                <td>
                                    <a href="?download=1&id=<?php echo $row['backup_id']; ?>" class="btn btn-sm btn-success"><i class="bi bi-download"></i> Download</a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this backup?');">
                                        <input type="hidden" name="delete_backup" value="1">
                                        <input type="hidden" name="backup_id" value="<?php echo $row['backup_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($backupsList)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No backups yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="denthub-card-header">
                <i class="bi bi-upload me-2"></i> Import Backup
            </div>
            <div class="card-body">
                <p class="text-danger"><strong>Warning:</strong> Importing will execute SQL against your database. Use with caution. Ensure the backup is from a compatible Denthub installation.</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="import_backup" value="1">
                    <div class="mb-3">
                        <label class="form-label">Backup File (.sql)</label>
                        <input type="file" class="form-control" name="backup_file" accept=".sql" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type <code>IMPORT</code> to confirm</label>
                        <input type="text" class="form-control" name="import_confirm" placeholder="IMPORT" required style="max-width: 200px;">
                    </div>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-upload"></i> Import Backup</button>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
