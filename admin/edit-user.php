<?php
/**
 * Denthub Dental Clinic - Edit User (Admin Only)
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireRole('admin');

$db = getDB();
$error = '';
$success = '';

$user_id = intval($_GET['id'] ?? 0);
if ($user_id <= 0) {
    header('Location: users.php');
    exit;
}

// Fetch user
$stmt = $db->prepare("SELECT u.*, d.dentist_id, d.license_number, d.specialization
                      FROM users u
                      LEFT JOIN dentists d ON u.user_id = d.user_id
                      WHERE u.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: users.php');
    exit;
}

$dentist_id = $user['dentist_id'] ?? null;

// Fetch branches
$branches = $db->query("SELECT * FROM branches WHERE is_active = TRUE");

// Fetch services for mastery selection
$services = getServices();

// Current service mastery (for dentists)
$current_mastery = [];
if ($dentist_id) {
    $mStmt = $db->prepare("SELECT service_id FROM dentist_service_mastery WHERE dentist_id = ?");
    $mStmt->bind_param("i", $dentist_id);
    $mStmt->execute();
    $mRes = $mStmt->get_result();
    while ($row = $mRes->fetch_assoc()) {
        $current_mastery[] = (int)$row['service_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $full_name = sanitize($_POST['full_name'] ?? '');
    $role = sanitize($_POST['role'] ?? $user['role']);
    $phone = sanitize($_POST['phone'] ?? '');
    $branch_id = intval($_POST['branch_id'] ?? $user['branch_id']);
    // PostgreSQL: is_active is boolean – store as text and cast in SQL
    $is_active = isset($_POST['is_active']) ? 'true' : 'false';

    $license_number = sanitize($_POST['license_number'] ?? '');
    $specialization = sanitize($_POST['specialization'] ?? '');
    $service_mastery = $_POST['service_mastery'] ?? [];

    if (empty($username) || empty($email) || empty($full_name)) {
        $error = 'Please fill in all required fields.';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email address.';
    } else {
        // Check uniqueness of username/email for other users
        $checkStmt = $db->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id <> ?");
        $checkStmt->bind_param("ssi", $username, $email, $user_id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $error = 'Username or email already exists for another user.';
        } else {
            // Update user (PostgreSQL boolean cast for is_active)
            $updStmt = $db->prepare("UPDATE users 
                                     SET username = ?, email = ?, full_name = ?, role = ?, branch_id = ?, phone = ?, is_active = CAST(? AS BOOLEAN) 
                                     WHERE user_id = ?");
            $updStmt->bind_param("ssssissi", $username, $email, $full_name, $role, $branch_id, $phone, $is_active, $user_id);

            if ($updStmt->execute()) {
                // Handle dentist-specific data
                if ($role === 'dentist') {
                    // Ensure dentist record exists
                    if (!$dentist_id) {
                        $dStmt = $db->prepare("INSERT INTO dentists (user_id, license_number, specialization, branch_id, is_active) 
                                               VALUES (?, ?, ?, ?, TRUE)");
                        $dStmt->bind_param("issi", $user_id, $license_number, $specialization, $branch_id);
                        if ($dStmt->execute()) {
                            $dentist_id = $db->insert_id;
                        }
                    } else {
                        $dStmt = $db->prepare("UPDATE dentists 
                                               SET license_number = ?, specialization = ?, branch_id = ?, is_active = TRUE 
                                               WHERE dentist_id = ?");
                        $dStmt->bind_param("ssii", $license_number, $specialization, $branch_id, $dentist_id);
                        $dStmt->execute();
                    }

                    // Update service mastery
                    if ($dentist_id) {
                        $delStmt = $db->prepare("DELETE FROM dentist_service_mastery WHERE dentist_id = ?");
                        $delStmt->bind_param("i", $dentist_id);
                        $delStmt->execute();

                        if (!empty($service_mastery)) {
                            $mIns = $db->prepare("INSERT INTO dentist_service_mastery (dentist_id, service_id) VALUES (?, ?)");
                            foreach ($service_mastery as $sid) {
                                $sid = (int)$sid;
                                if ($sid > 0) {
                                    $mIns->bind_param("ii", $dentist_id, $sid);
                                    $mIns->execute();
                                }
                            }
                        }
                    }
                } else {
                    // If role is not dentist, optionally deactivate dentist record
                    if ($dentist_id) {
                        $dStmt = $db->prepare("UPDATE dentists SET is_active = FALSE WHERE dentist_id = ?");
                        $dStmt->bind_param("i", $dentist_id);
                        $dStmt->execute();
                    }
                }

                $success = 'User updated successfully.';

                // Refresh user data
                header('Location: edit-user.php?id=' . $user_id . '&updated=1');
                exit;
            } else {
                $error = 'Error updating user. Please try again.';
            }
        }
    }
}

// Reload latest data when redirected with updated flag
if (isset($_GET['updated'])) {
    $success = 'User updated successfully.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Edit User</h2>
            <a href="users.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" required
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? $user['username']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? $user['email']); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" required
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? $user['full_name']); ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="role" required>
                                <option value="admin" <?php echo (($user['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="dentist" <?php echo (($user['role'] ?? '') === 'dentist') ? 'selected' : ''; ?>>Dentist</option>
                                <option value="staff" <?php echo (($user['role'] ?? '') === 'staff') ? 'selected' : ''; ?>>Staff</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? $user['phone']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Active</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                       <?php echo (($user['is_active'] ?? true) ? 'checked' : ''); ?>>
                                <label class="form-check-label" for="is_active">
                                    Account is active
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select class="form-select" name="branch_id">
                            <?php
                            $branches->data_seek(0);
                            while ($branch = $branches->fetch_assoc()):
                            ?>
                                <option value="<?php echo $branch['branch_id']; ?>"
                                    <?php
                                    $selectedBranch = $_POST['branch_id'] ?? $user['branch_id'];
                                    echo ($selectedBranch == $branch['branch_id']) ? 'selected' : '';
                                    ?>>
                                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Dentist-specific fields -->
                    <div id="dentistFields" style="display: none;">
                        <hr>
                        <h5>Dentist Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">License Number</label>
                                <input type="text" class="form-control" name="license_number"
                                       value="<?php echo htmlspecialchars($_POST['license_number'] ?? ($user['license_number'] ?? '')); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Specialization</label>
                                <input type="text" class="form-control" name="specialization"
                                       placeholder="e.g., General Dentistry, Orthodontics"
                                       value="<?php echo htmlspecialchars($_POST['specialization'] ?? ($user['specialization'] ?? '')); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Service Mastery <span class="text-muted">(Select services this dentist can perform)</span></label>
                            <div class="row">
                                <?php
                                $services->data_seek(0);
                                $selected_mastery = $_POST['service_mastery'] ?? $current_mastery;
                                while ($service = $services->fetch_assoc()):
                                ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="service_mastery[]"
                                                   value="<?php echo $service['service_id']; ?>"
                                                   id="service_<?php echo $service['service_id']; ?>"
                                                   <?php echo in_array($service['service_id'], $selected_mastery) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="service_<?php echo $service['service_id']; ?>">
                                                <?php echo htmlspecialchars($service['service_name']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide dentist fields based on role selection
        document.getElementById('role').addEventListener('change', function() {
            const dentistFields = document.getElementById('dentistFields');
            if (this.value === 'dentist') {
                dentistFields.style.display = 'block';
            } else {
                dentistFields.style.display = 'none';
            }
        });
        // Trigger on page load
        document.getElementById('role').dispatchEvent(new Event('change'));
    </script>
</body>
</html>

