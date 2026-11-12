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
                    // PostgreSQL: is_active is a boolean, use TRUE
                    $dentistStmt = $db->prepare("INSERT INTO dentists (user_id, license_number, specialization, branch_id, is_active) 
                                                  VALUES (?, ?, ?, ?, TRUE)");
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
                        // PostgreSQL: is_available is a boolean, use TRUE
                        $scheduleStmt = $db->prepare("INSERT INTO dentist_schedules (dentist_id, day_of_week, start_time, end_time, is_available) 
                                                      VALUES (?, ?, '09:00:00', '17:00:00', TRUE)");
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['ajax'] ?? '') === '1')) {
    header('Content-Type: application/json; charset=utf-8');
    if ($error !== '') {
        echo json_encode(['success' => false, 'message' => $error]);
    } else {
        echo json_encode(['success' => true, 'message' => $success !== '' ? $success : 'User account created successfully.']);
    }
    exit;
}

// Get branches (PostgreSQL boolean is_active)
$branches = $db->query("SELECT * FROM branches WHERE is_active = TRUE");
$embed = isset($_GET['embed']) && $_GET['embed'] === '1';
if ($embed) {
    header('Content-Type: text/html; charset=utf-8');
    include __DIR__ . '/partials/add-user-form.php';
    exit;
}
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

    <main class="denthub-main">
    <div class="container-fluid py-4">
        <h1 class="denthub-page-title">User Management</h1>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div></div>
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

        <div class="card denthub-card-rounded shadow-sm">
            <div class="denthub-card-header text-white">
                <h5 class="mb-0">Add New User</h5>
            </div>
            <div class="card-body">
                <?php $embed = false; include __DIR__ . '/partials/add-user-form.php'; ?>
            </div>
        </div>
    </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('role').addEventListener('change', function() {
            document.getElementById('dentistFields').style.display = (this.value === 'dentist') ? 'block' : 'none';
        });
        document.getElementById('role').dispatchEvent(new Event('change'));
    </script>
</body>
</html>
