<?php
/**
 * Denthub Dental Clinic - Patient Management
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$db = getDB();

$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

$where = '';
$params = [];
$types = '';
if ($search) {
    $where = " WHERE LOWER(first_name) LIKE LOWER(?) OR LOWER(last_name) LIKE LOWER(?) OR patient_number LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
    $types = 'sss';
}

$count_sql = "SELECT COUNT(*) as total FROM patients" . $where;
if ($types) {
    $stmt = $db->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total_rows = (int)$stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_rows = (int)$db->query($count_sql)->fetch_assoc()['total'];
}
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql = "SELECT patient_id, patient_number, first_name, last_name FROM patients" . $where . " ORDER BY patient_id DESC LIMIT $per_page OFFSET $offset";
if ($types) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $patients = $stmt->get_result();
} else {
    $patients = $db->query($sql);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="denthub-main">
    <div class="container-fluid py-4">
        <h1 class="denthub-page-title">Patient Management</h1>

        <!-- Search -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-10">
                            <input type="text" class="form-control" name="search" placeholder="Search by name or patient number (e.g. PAT000001)..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Patients Table -->
        <div class="card denthub-card-rounded">
            <div class="card-body">
                <div class="denthub-table-pagination">
                    <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                    <?php
                    $q = $_GET;
                    if ($total_pages > 1) {
                        if ($page > 1) { $q['page'] = $page - 1; echo '<a href="?' . http_build_query($q) . '" class="btn btn-sm btn-outline-primary">Previous</a>'; }
                        if ($page < $total_pages) { $q['page'] = $page + 1; echo '<a href="?' . http_build_query($q) . '" class="btn btn-sm btn-outline-primary">Next</a>'; }
                    }
                    ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($patients->num_rows > 0): ?>
                                <?php while ($patient = $patients->fetch_assoc()): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($patient['patient_number']); ?></code></td>
                                        <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                        <td>
                                            <a href="view-patient.php?id=<?php echo (int)$patient['patient_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-folder2-open"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No patients found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

