<?php
/**
 * Denthub Dental Clinic - Clinic Schedule / Blocked Dates (Admin)
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireRole('admin');

$db = getDB();
$error = '';
$success = '';
$branch_id = $_SESSION['branch_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add a blocked date
    if (isset($_POST['add_block'])) {
        $block_date = $_POST['block_date'] ?? '';
        $reason = sanitize($_POST['reason'] ?? '');

        if (empty($block_date)) {
            $error = 'Please select a date to block.';
        } else {
            // Check if already exists
            $check = $db->prepare("SELECT block_id FROM blocked_dates WHERE block_date = ? AND branch_id = ?");
            $check->bind_param("si", $block_date, $branch_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'This date is already configured for this branch.';
            } else {
                $stmt = $db->prepare("INSERT INTO blocked_dates (block_date, reason, branch_id, is_active) 
                                      VALUES (?, ?, ?, TRUE)");
                $stmt->bind_param("ssi", $block_date, $reason, $branch_id);
                if ($stmt->execute()) {
                    $success = 'Date blocked successfully.';
                } else {
                    $error = 'Error blocking date. Please try again.';
                }
            }
        }
    }

    // Toggle active state
    if (isset($_POST['toggle_block'])) {
        $block_id  = intval($_POST['block_id'] ?? 0);
        // PostgreSQL: is_active is boolean – pass as text and cast in SQL
        $is_active = intval($_POST['is_active'] ?? 0) ? 'true' : 'false';

        if ($block_id > 0) {
            $stmt = $db->prepare("UPDATE blocked_dates SET is_active = CAST(? AS BOOLEAN) WHERE block_id = ? AND branch_id = ?");
            $stmt->bind_param("sii", $is_active, $block_id, $branch_id);
            $stmt->execute();
            $success = 'Schedule updated successfully.';
        }
    }
}

// Load blocked dates for this branch
$stmt = $db->prepare("SELECT * FROM blocked_dates WHERE branch_id = ? ORDER BY block_date ASC");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$blocked_dates = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Clinic Schedule / Closed Dates</h2>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calendar-x"></i> Block a Date</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Date to Block <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="block_date"
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reason (optional)</label>
                                <input type="text" class="form-control" name="reason"
                                       placeholder="Holiday, maintenance, etc.">
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="add_block" value="1" class="btn btn-primary">
                                    Block Date
                                </button>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Blocked dates will be unavailable for patients when booking appointments.
                            </small>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Blocked Dates (Branch <?php echo (int)$branch_id; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($blocked_dates->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $blocked_dates->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo formatDate($row['block_date']); ?></td>
                                                <td><?php echo htmlspecialchars($row['reason'] ?? ''); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['is_active'] ? 'danger' : 'secondary'; ?>">
                                                        <?php echo $row['is_active'] ? 'Active (Blocked)' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="block_id" value="<?php echo $row['block_id']; ?>">
                                                        <input type="hidden" name="is_active" value="<?php echo $row['is_active'] ? 0 : 1; ?>">
                                                        <button type="submit" name="toggle_block" value="1"
                                                                class="btn btn-sm btn-outline-<?php echo $row['is_active'] ? 'secondary' : 'success'; ?>">
                                                            <?php echo $row['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No blocked dates configured for this branch.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

