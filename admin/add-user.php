<?php
/**
 * Denthub Dental Clinic - Add User (Admin Only)
 * Specifically for creating dentist accounts with service mastery
 */
ob_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/mailer.php';

requireRole('admin');

$db = getDB();
$error = '';
$success = '';
$createdUser = null;
$isAjax = $_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['ajax'] ?? '') === '1');

// Get services for mastery selection and branches for the form / new-row payload.
$services = getServices();
$branches = $db->query("SELECT * FROM branches WHERE is_active = TRUE");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = trim($_POST['role'] ?? 'admin');
    $phone = trim($_POST['phone'] ?? '');
    $branch_id = intval($_POST['branch_id'] ?? 0);
    $license_number = trim($_POST['license_number'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $service_mastery = $_POST['service_mastery'] ?? [];
    if (!in_array($role, ['admin', 'dentist'], true)) {
        $role = 'admin';
    }

    // Validation
    if ($username === '' || $email === '' || $full_name === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email address.';
    } elseif ($phone !== '' && !preg_match('/^09[0-9]{9}$/', $phone)) {
        $error = 'Phone must be 11 digits starting with 09.';
    } else {
        try {
            if ($branch_id <= 0) {
                $branchRow = $db->query("SELECT branch_id FROM branches WHERE is_active = TRUE ORDER BY branch_id LIMIT 1")->fetch_assoc();
                $branch_id = (int)($branchRow['branch_id'] ?? 0);
            }
            if ($branch_id <= 0) {
                $error = 'No active branch is available.';
            } else {
                $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = 'Username or email already exists.';
                } else {
                    $temp_password = bin2hex(random_bytes(4));
                    $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
                    $phone_value = $phone !== '' ? $phone : null;
                    $phone_hash = $phone_value ? hash('sha256', $phone_value) : null;

                    // RETURNING avoids a second LASTVAL() query, which fails on the pooled connection.
                    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role, branch_id, phone, phone_hash, is_active, must_change_password)
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, TRUE)
                                          RETURNING user_id, created_at");
                    $stmt->bind_param("sssssiss", $username, $email, $password_hash, $full_name, $role, $branch_id, $phone_value, $phone_hash);

                    if ($stmt->execute()) {
                        $created = $stmt->get_result()->fetch_assoc() ?: [];
                        $user_id = (int)($created['user_id'] ?? 0);

                        if ($user_id <= 0) {
                            $error = 'Error creating user account. Please try again.';
                        } elseif ($role === 'dentist') {
                            $dentistStmt = $db->prepare("INSERT INTO dentists (user_id, license_number, specialization, branch_id, is_active)
                                                          VALUES (?, ?, ?, ?, TRUE)
                                                          RETURNING dentist_id");
                            $dentistStmt->bind_param("issi", $user_id, $license_number, $specialization, $branch_id);

                            if ($dentistStmt->execute()) {
                                $dentistRow = $dentistStmt->get_result()->fetch_assoc();
                                $dentist_id = (int)($dentistRow['dentist_id'] ?? 0);

                                if (!empty($service_mastery) && $dentist_id > 0) {
                                    $masteryStmt = $db->prepare("INSERT INTO dentist_service_mastery (dentist_id, service_id) VALUES (?, ?)");
                                    foreach ($service_mastery as $service_id) {
                                        $service_id = intval($service_id);
                                        if ($service_id > 0) {
                                            $masteryStmt->bind_param("ii", $dentist_id, $service_id);
                                            $masteryStmt->execute();
                                        }
                                    }
                                }

                                if ($dentist_id > 0) {
                                    $scheduleStmt = $db->prepare("INSERT INTO dentist_schedules (dentist_id, day_of_week, start_time, end_time, is_available)
                                                                  VALUES (?, ?, '09:00:00', '17:00:00', TRUE)");
                                    for ($day = 1; $day <= 5; $day++) {
                                        $scheduleStmt->bind_param("ii", $dentist_id, $day);
                                        $scheduleStmt->execute();
                                    }
                                }

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

                        if ($success && $user_id > 0) {
                            $branchName = 'N/A';
                            if ($branches) {
                                $branches->data_seek(0);
                                while ($branch = $branches->fetch_assoc()) {
                                    if ((int)$branch['branch_id'] === (int)$branch_id) {
                                        $branchName = $branch['branch_name'];
                                        break;
                                    }
                                }
                            }
                            $createdUser = [
                                'user_id' => $user_id,
                                'full_name' => $full_name,
                                'email' => $email,
                                'email_masked' => maskEmail($email),
                                'role' => $role,
                                'branch_name' => $branchName,
                                'is_active' => true,
                                'created_at' => formatDate($created['created_at'] ?? date('Y-m-d H:i:s')),
                            ];
                            $_POST = [];
                        }
                    } else {
                        $error = 'Error creating user account. Please try again.';
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('add-user failed: ' . $e->getMessage());
            $message = $e->getMessage();
            if (stripos($message, 'duplicate key') !== false || stripos($message, 'unique') !== false) {
                $error = 'Username or email already exists.';
            } elseif (stripos($message, 'branch_id') !== false) {
                $error = 'Please choose a valid branch.';
            } else {
                $error = 'Could not create the user. Please try again.';
                if (getenv('APP_DEBUG') === '1' || getenv('APP_DEBUG') === 'true') {
                    $error .= ' ' . $e->getMessage();
                }
            }
        }
    }
}

if ($isAjax) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    $jsonFlags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    if ($error !== '') {
        echo json_encode(['success' => false, 'message' => $error], $jsonFlags);
    } else {
        echo json_encode([
            'success' => true,
            'message' => $success !== '' ? $success : 'User account created successfully.',
            'user' => $createdUser,
        ], $jsonFlags);
    }
    exit;
}

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
