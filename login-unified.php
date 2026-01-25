<?php
/**
 * Denthub Dental Clinic - Unified Login System
 * Automatically detects user type and redirects accordingly
 */
// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isPatientLoggedIn()) {
    header('Location: patient/dashboard.php');
    exit;
}

if (isLoggedIn()) {
    // Redirect based on role
    $role = $_SESSION['role'];
    if ($role === 'admin' || $role === 'staff') {
        header('Location: admin/dashboard.php');
    } elseif ($role === 'dentist') {
        header('Location: dentist/dashboard.php');
    } else {
        header('Location: admin/dashboard.php');
    }
    exit;
}

$error = '';
$user_type = $_GET['type'] ?? 'auto'; // auto, patient, staff

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_username = sanitize($_POST['email_or_username'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_type = $_POST['login_type'] ?? 'auto';

    if (empty($email_or_username) || empty($password)) {
        $error = 'Please enter email/username and password.';
    } else {
        // Try patient login first
        if ($login_type === 'auto' || $login_type === 'patient') {
            if (patientLogin($email_or_username, $password)) {
                header('Location: patient/dashboard.php');
                exit;
            }
        }
        
        // Try staff/dentist/admin login
        if ($login_type === 'auto' || $login_type === 'staff') {
            if (login($email_or_username, $password)) {
                $role = $_SESSION['role'];
                if ($role === 'admin' || $role === 'staff') {
                    header('Location: admin/dashboard.php');
                } elseif ($role === 'dentist') {
                    header('Location: dentist/dashboard.php');
                } else {
                    header('Location: admin/dashboard.php');
                }
                exit;
            }
        }
        
        $error = 'Invalid email/username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-box-arrow-in-right text-primary" style="font-size: 48px;"></i>
                            <h2 class="mt-3">Login</h2>
                            <p class="text-muted">Access your account</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="login_type" value="<?php echo htmlspecialchars($user_type); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Email or Username</label>
                                <input type="text" class="form-control" name="email_or_username" required autofocus 
                                       placeholder="Enter your email or username">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                        <i class="bi bi-eye" id="password-icon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3 text-end">
                                <a href="forgot-password.php" class="text-muted small">Forgot Password?</a>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Login</button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p>Don't have an account? <a href="register.php">Register here</a></p>
                            <hr class="my-3">
                            <div class="btn-group" role="group">
                                <a href="?type=patient" class="btn btn-sm btn-outline-secondary <?php echo $user_type === 'patient' ? 'active' : ''; ?>">Patient Login</a>
                                <a href="?type=staff" class="btn btn-sm btn-outline-secondary <?php echo $user_type === 'staff' ? 'active' : ''; ?>">Staff/Doctor Login</a>
                            </div>
                            <hr class="my-3">
                            <a href="index.php" class="text-muted">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>
