<?php
/**
 * Denthub Dental Clinic - Dentist Profile Management
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireRole('dentist');

$db = getDB();
$user = getCurrentUser();
$error = '';
$success = '';

// Get dentist information
$stmt = $db->prepare("SELECT d.*, u.full_name, u.email, u.phone, b.branch_name 
                     FROM dentists d 
                     JOIN users u ON d.user_id = u.user_id 
                     LEFT JOIN branches b ON d.branch_id = b.branch_id
                     WHERE d.user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$dentist = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $specialization = sanitize($_POST['specialization'] ?? '');
    
    if (empty($full_name) || empty($email)) {
        $error = 'Full name and email are required.';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email address.';
    } else {
        // Check if email is already taken by another user
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Email is already in use by another user.';
        } else {
            // Update user info
            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
            $stmt->bind_param("sssi", $full_name, $email, $phone, $_SESSION['user_id']);
            if ($stmt->execute()) {
                if (($user['email'] ?? '') !== $email) logActivity('email_changed', '', $_SESSION['user_id']);
                if (($user['phone'] ?? '') !== $phone) logActivity('phone_changed', '', $_SESSION['user_id']);
                // Update dentist specialization
                $dentistStmt = $db->prepare("UPDATE dentists SET specialization = ? WHERE user_id = ?");
                $dentistStmt->bind_param("si", $specialization, $_SESSION['user_id']);
                $dentistStmt->execute();
                
                $_SESSION['full_name'] = $full_name;
                $user['full_name'] = $full_name;
                $user['email'] = $email;
                $user['phone'] = $phone;
                $dentist['specialization'] = $specialization;
                $success = 'Profile updated successfully.';
            } else {
                $error = 'Error updating profile. Please try again.';
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required.';
    } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
        $error = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } else {
        // Verify current password
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (password_verify($current_password, $result['password_hash'])) {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_password_hash, $_SESSION['user_id']);
            if ($stmt->execute()) {
                logActivity('password_changed', '', $_SESSION['user_id']);
                $success = 'Password changed successfully.';
            } else {
                $error = 'Error changing password. Please try again.';
            }
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

// Get service mastery
$masteryStmt = $db->prepare("SELECT s.service_name 
                            FROM dentist_service_mastery dsm 
                            JOIN services s ON dsm.service_id = s.service_id 
                            WHERE dsm.dentist_id = ? 
                            ORDER BY s.service_name");
$masteryStmt->bind_param("i", $dentist['dentist_id']);
$masteryStmt->execute();
$masteryServices = $masteryStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php require_once 'navbar.php'; ?>

    <main class="denthub-main" style="margin-left:0;">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Profile</h2>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Information -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header denthub-card-header">
                        <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="full_name" required 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required 
                                       value="<?php echo htmlspecialchars($user['email']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">License Number</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($dentist['license_number'] ?? ''); ?>" disabled>
                                <small class="text-muted">License number cannot be changed</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Specialization</label>
                                <input type="text" class="form-control" name="specialization" 
                                       placeholder="e.g., General Dentistry, Orthodontics"
                                       value="<?php echo htmlspecialchars($dentist['specialization'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Branch</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($dentist['branch_name'] ?? 'N/A'); ?>" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Service Mastery</label>
                                <div class="list-group">
                                    <?php if ($masteryServices->num_rows > 0): ?>
                                        <?php while ($service = $masteryServices->fetch_assoc()): ?>
                                            <div class="list-group-item"><?php echo htmlspecialchars($service['service_name']); ?></div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="list-group-item text-muted">No services assigned</div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">Service mastery is managed by administrator</small>
                            </div>
                            <input type="hidden" name="update_profile" value="1">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-key"></i> Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="new_password" required 
                                       minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            <input type="hidden" name="change_password" value="1">
                            <button type="submit" class="btn btn-warning">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
