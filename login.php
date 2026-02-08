<?php
/**
 * Denthub Dental Clinic - Patient Login
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
$login_identifier = getLoginClientIdentifier();
$attempt_type = 'unified';
$show_captcha = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha_token = $_POST['g-recaptcha-response'] ?? $_POST['recaptcha_token'] ?? '';

    if (isLoginLocked($login_identifier, $attempt_type)) {
        $mins = ceil((getLoginLockedUntil($login_identifier, $attempt_type) - time()) / 60);
        $error = 'Too many failed attempts. Please try again in ' . $mins . ' minute(s).';
    } elseif (empty($identifier) || empty($password)) {
        $error = 'Please enter email/username and password.';
    } else {
        if (loginNeedsCaptcha($login_identifier, $attempt_type)) {
            $has_captcha_key = defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== '';
            if ($has_captcha_key) {
                if ($captcha_token === '') {
                    $error = 'Please complete the verification below.';
                    $show_captcha = true;
                } elseif (!verifyRecaptchaV3($captcha_token, 'login')) {
                    $error = 'Verification failed. Please try again.';
                    $show_captcha = true;
                } else {
                    recordLoginCaptchaPassed($login_identifier, $attempt_type, true);
                }
            } else {
                recordLoginCaptchaPassed($login_identifier, $attempt_type, false);
            }
        }

        if ($error === '') {
            if (patientLogin($identifier, $password)) {
                clearLoginAttempts($login_identifier, $attempt_type);
                header('Location: patient/dashboard.php');
                exit;
            }
            if (login($identifier, $password)) {
                clearLoginAttempts($login_identifier, $attempt_type);
                if (!empty($_SESSION['must_change_password'])) {
                    header('Location: admin/force-password-change.php');
                    exit;
                }
                $role = $_SESSION['role'] ?? '';
                if ($role === 'admin' || $role === 'staff') {
                    header('Location: admin/dashboard.php');
                } elseif ($role === 'dentist') {
                    header('Location: dentist/dashboard.php');
                } else {
                    header('Location: admin/dashboard.php');
                }
                exit;
            }
            $just_locked = recordFailedLoginAttempt($login_identifier, $attempt_type);
            if ($just_locked) {
                $mins = defined('LOGIN_LOCKOUT_MINUTES') ? LOGIN_LOCKOUT_MINUTES : 15;
                $error = 'Too many failed attempts. This device is temporarily locked for ' . $mins . ' minutes.';
            } else {
                $error = 'Invalid email/username or password.';
                logActivity('login_failed', 'Attempt: ' . substr($identifier, 0, 100), null, null, $identifier, null, 'guest');
                if (loginNeedsCaptcha($login_identifier, $attempt_type)) {
                    $show_captcha = true;
                }
            }
        }
    }
} else {
    $show_captcha = loginNeedsCaptcha($login_identifier, $attempt_type);
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
<body style="background-color: var(--denthub-bg);">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card denthub-auth-card shadow">
                    <div class="card-header denthub-auth-card-header">
                        <div class="text-center">
                            <i class="bi bi-tooth d-block" style="font-size: 48px;"></i>
                            <h2 class="mt-2 mb-0">Login</h2>
                            <p class="mb-0 small opacity-90">Login to access your account</p>
                        </div>
                    </div>
                    <div class="card-body p-5">

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($show_captcha) && defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== ''): ?>
                            <div class="alert alert-info small">Please complete the verification before signing in.</div>
                        <?php endif; ?>

                        <form method="POST" action="" id="loginForm">
                            <input type="hidden" name="recaptcha_token" id="recaptcha_token" value="">
                            <div class="mb-3">
                                <label class="form-label">Email or Username</label>
                                <input type="text" class="form-control" name="email" required autofocus placeholder="Email or username">
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
                            <p class="mb-1">Don't have an account? <a href="register.php">Register here</a></p>
                            <a href="index.php" class="text-primary">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== ''): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>"></script>
    <?php endif; ?>
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
        <?php if (!empty($show_captcha) && defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== ''): ?>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            var tok = document.getElementById('recaptcha_token');
            if (tok && tok.value === '' && typeof grecaptcha !== 'undefined') {
                e.preventDefault();
                grecaptcha.ready(function() {
                    grecaptcha.execute('<?php echo addslashes(RECAPTCHA_SITE_KEY); ?>', { action: 'login' }).then(function(token) {
                        document.getElementById('recaptcha_token').value = token;
                        document.getElementById('loginForm').submit();
                    });
                });
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>

