<?php
/**
 * Denthub Dental Clinic - Laboratory Cases Management
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$db = getDB();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $case_id = intval($_POST['case_id']);
    $status = sanitize($_POST['status']);
    
    $stmt = $db->prepare("UPDATE lab_cases SET status = ? WHERE case_id = ?");
    $stmt->bind_param("si", $status, $case_id);
    $stmt->execute();
    
    header('Location: lab-cases.php?updated=1');
    exit;
}

// Get filter
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT lc.*, p.first_name, p.last_name, p.phone, u.full_name as dentist_name
        FROM lab_cases lc
        JOIN patients p ON lc.patient_id = p.patient_id
        LEFT JOIN dentists d ON lc.dentist_id = d.dentist_id
        LEFT JOIN users u ON d.user_id = u.user_id";

if ($status_filter) {
    $sql .= " WHERE lc.status = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $status_filter);
} else {
    $stmt = $db->prepare($sql);
}

$stmt->execute();
$lab_cases = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Cases - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Laboratory Cases</h2>
            <a href="add-lab-case.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add New Case
            </a>
        </div>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Lab case status updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="ready_for_pickup" <?php echo $status_filter === 'ready_for_pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lab Cases Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Case #</th>
                                <th>Patient</th>
                                <th>Case Type</th>
                                <th>Dentist</th>
                                <th>Date Received</th>
                                <th>Expected Completion</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($lab_cases->num_rows > 0): ?>
                                <?php while ($case = $lab_cases->fetch_assoc()): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($case['case_number']); ?></code></td>
                                        <td><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($case['case_type']); ?></td>
                                        <td><?php echo $case['dentist_name'] ? htmlspecialchars($case['dentist_name']) : 'N/A'; ?></td>
                                        <td><?php echo $case['date_received'] ? formatDate($case['date_received']) : '-'; ?></td>
                                        <td><?php echo $case['expected_completion_date'] ? formatDate($case['expected_completion_date']) : '-'; ?></td>
                                        <td>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="case_id" value="<?php echo $case['case_id']; ?>">
                                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    <option value="pending" <?php echo $case['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="in_progress" <?php echo $case['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                    <option value="ready_for_pickup" <?php echo $case['status'] === 'ready_for_pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                                    <option value="completed" <?php echo $case['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $case['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                        </td>
                                        <td>
                                            <a href="view-lab-case.php?id=<?php echo $case['case_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No lab cases found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

