<?php
/**
 * Denthub Dental Clinic - Add User (Admin Only)
 * Specifically for creating dentist accounts with service mastery
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/mailer.php';

requireRole('admin');

$db = getDB();
$error = '';
$success = '';

// Get services for mastery selection
$services = getServices();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $full_name = sanitize($_POST['full_name'] ?? '');
$role = sanitize($_POST['role'] ?? 'admin');
    $phone = sanitize($_POST['phone'] ?? '');
    $branch_id = intval($_POST['branch_id'] ?? 1);
    $license_number = sanitize($_POST['license_number'] ?? '');
    $specialization = sanitize($_POST['specialization'] ?? '');
    $service_mastery = $_POST['service_mastery'] ?? [];
    
    // Validation
    if (empty($username) || empty($email) || empty($full_name)) {
        $error = 'Please fill in all required fields.';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email address.';
    } else {
        // Check if username or email already exists
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Username or email already exists.';
        } else {
            // Generate temporary password
            $temp_password = bin2hex(random_bytes(4)); // 8 character password
            $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
            $phone_hash = $phone ? hash('sha256', $phone) : null;
            
            // Create user account with must_change_password flag and phone_hash
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role, branch_id, phone, phone_hash, is_active, must_change_password) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, TRUE)");
            $stmt->bind_param("sssssiss", $username, $email, $password_hash, $full_name, $role, $branch_id, $phone, $phone_hash);
            
            if ($stmt->execute()) {
                $user_id = $db->insert_id;
                
                // If dentist, create dentist record
                if ($role === 'dentist') {
                    $dentistStmt = $db->prepare("INSERT INTO dentists (user_id, license_number, specialization, branch_id, is_active) 
                                                  VALUES (?, ?, ?, ?, 1)");
                    $dentistStmt->bind_param("issi", $user_id, $license_number, $specialization, $branch_id);
                    
                    if ($dentistStmt->execute()) {
                        $dentist_id = $db->insert_id;
                        
                        // Add service mastery
                        if (!empty($service_mastery)) {
                            $masteryStmt = $db->prepare("INSERT INTO dentist_service_mastery (dentist_id, service_id) VALUES (?, ?)");
                            foreach ($service_mastery as $service_id) {
                                $service_id = intval($service_id);
                                if ($service_id > 0) {
                                    $masteryStmt->bind_param("ii", $dentist_id, $service_id);
                                    $masteryStmt->execute();
                                }
                            }
                        }
                        
                        // Add default schedule (Monday to Friday, 9 AM to 5 PM)
                        $scheduleStmt = $db->prepare("INSERT INTO dentist_schedules (dentist_id, day_of_week, start_time, end_time, is_available) 
                                                      VALUES (?, ?, '09:00:00', '17:00:00', 1)");
                        for ($day = 1; $day <= 5; $day++) { // Monday to Friday
                            $scheduleStmt->bind_param("ii", $dentist_id, $day);
                            $scheduleStmt->execute();
                        }
                        
                        // Send email to dentist
                        $mailer = getMailer();
                        if ($mailer->sendDentistAccountEmail($email, $full_name, $username, $temp_password, $email)) {
                            $success = "Dentist account created successfully! Credentials have been sent to {$email}";
                        } else {
                            $success = "Dentist account created successfully! Username: {$username}, Temporary Password: {$temp_password} (Email sending failed)";
                        }
                    } else {
                        $error = 'Error creating dentist record.';
                    }
                } else {
                    $success = "User account created successfully! Username: {$username}, Temporary Password: {$temp_password}";
                }
                
                // Clear form on success
                if ($success) {
                    $_POST = [];
                }
            } else {
                $error = 'Error creating user account. Please try again.';
            }
        }
    }
}

// Get branches (PostgreSQL boolean is_active)
$branches = $db->query("SELECT * FROM branches WHERE is_active = TRUE");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Add New User</h2>
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
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" required 
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="role" required>
                                <option value="admin" <?php echo (($_POST['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="dentist" <?php echo (($_POST['role'] ?? '') === 'dentist') ? 'selected' : ''; ?>>Dentist</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select class="form-select" name="branch_id">
                            <?php while ($branch = $branches->fetch_assoc()): ?>
                                <option value="<?php echo $branch['branch_id']; ?>" 
                                        <?php echo (($_POST['branch_id'] ?? 1) == $branch['branch_id']) ? 'selected' : ''; ?>>
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
                                       value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Specialization</label>
                                <input type="text" class="form-control" name="specialization" 
                                       placeholder="e.g., General Dentistry, Orthodontics"
                                       value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Service Mastery <span class="text-muted">(Select services this dentist can perform)</span></label>
                            <div class="row">
                                <?php 
                                $services->data_seek(0);
                                while ($service = $services->fetch_assoc()): 
                                ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="service_mastery[]" 
                                                   value="<?php echo $service['service_id']; ?>"
                                                   id="service_<?php echo $service['service_id']; ?>"
                                                   <?php echo (in_array($service['service_id'], $_POST['service_mastery'] ?? [])) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="service_<?php echo $service['service_id']; ?>">
                                                <?php echo htmlspecialchars($service['service_name']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <small class="text-muted">A default schedule (Monday-Friday, 9 AM - 5 PM) will be created automatically.</small>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> A temporary password will be generated and sent to the user's email. They should change it on first login.
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create User</button>
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
