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
$ip_address = getClientIpAddress();
$ip_identifier = getIpLoginIdentifier($ip_address);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha_token = $_POST['g-recaptcha-response'] ?? '';
    $account_identifier = $identifier !== '' ? getAccountLoginIdentifier($identifier) : '';
    $lock_ids = array_values(array_filter([$login_identifier, $account_identifier, $ip_identifier]));
    $lock_until = getCombinedLoginLockUntil($lock_ids, $attempt_type);

    if (isIpLoginBlocked($ip_address) || $lock_until > time()) {
        $until = max($lock_until, strtotime((string)($GLOBALS['ip_lock_until'] ?? '')) ?: $lock_until);
        $error = 'Too many failed attempts. Please try again in ' . formatLockRemaining($lock_until > time() ? $lock_until : time() + 30) . '.';
    } elseif (!verifyCsrf()) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } elseif (empty($identifier) || empty($password)) {
        $error = 'Please enter email/username and password.';
    } else {
        if (loginNeedsCaptcha($login_identifier, $attempt_type) || loginNeedsCaptcha($account_identifier, $attempt_type)) {
            $has_captcha_key = defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== '';
            if ($has_captcha_key) {
                if ($captcha_token === '') {
                    $error = 'Please complete the verification below.';
                    $show_captcha = true;
                    recordCaptchaFailureAndMaybeLock($login_identifier, $attempt_type);
                } elseif (!verifyRecaptchaV2($captcha_token)) {
                    $error = 'Verification failed. Please try again.';
                    $show_captcha = true;
                    recordCaptchaFailureAndMaybeLock($login_identifier, $attempt_type);
                } else {
                    recordLoginCaptchaPassed($login_identifier, $attempt_type, true);
                    clearCaptchaFailures($login_identifier, $attempt_type);
                }
            } else {
                recordLoginCaptchaPassed($login_identifier, $attempt_type, false);
            }
        }

        if ($error === '') {
            if (patientLogin($identifier, $password)) {
                clearLoginAttemptsBundle($login_identifier, $account_identifier, $ip_address, $attempt_type);
                header('Location: patient/dashboard.php');
                exit;
            }
            if (login($identifier, $password)) {
                clearLoginAttemptsBundle($login_identifier, $account_identifier, $ip_address, $attempt_type);
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
            $lockInfo = recordFailedLoginBundle($login_identifier, $account_identifier, $ip_address, $attempt_type);
            $just_locked = !empty($lockInfo['locked']);
            if ($just_locked) {
                $error = 'Too many failed attempts. Please try again in ' . formatLockRemaining(time() + (int)$lockInfo['lock_seconds']) . '.';
            } else {
                $error = 'Invalid email/username or password.';
                logActivity('login_failed', 'Attempt: ' . substr($identifier, 0, 100), null, null, $identifier, null, 'guest');
                if (loginNeedsCaptcha($login_identifier, $attempt_type) || loginNeedsCaptcha($account_identifier, $attempt_type)) {
                    $show_captcha = true;
                }
            }
        }
    }
} else {
    $lock_until = getCombinedLoginLockUntil([$login_identifier, $ip_identifier], $attempt_type);
    if ($lock_until > time()) {
        $error = 'Too many failed attempts. Please try again in ' . formatLockRemaining($lock_until) . '.';
    }
    $show_captcha = loginNeedsCaptcha($login_identifier, $attempt_type);
}

$form_locked_until = isset($lock_until) ? (int)$lock_until : getCombinedLoginLockUntil([$login_identifier, $ip_identifier], $attempt_type);
$is_form_locked = $form_locked_until > time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <?php $asset_base = ''; require __DIR__ . '/includes/head-assets.php'; ?>
</head>
<body style="background-color: var(--denthub-bg);">
    <?php $nav_active = ''; require_once 'includes/nav-public.php'; ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card denthub-auth-card shadow">
                    <div class="card-header denthub-auth-card-header">
                        <div class="text-center">
                            <img src="assets/images/logobluetransparent.png" alt="<?php echo APP_NAME; ?>" style="max-height: 64px; width: auto;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <i class="bi bi-tooth d-block" style="font-size: 48px; display: none;"></i>
                            <h2 class="mt-2 mb-0">Login</h2>
                            <p class="mb-0 small opacity-90">Login to access your account</p>
                        </div>
                    </div>
                    <div class="card-body p-5">

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($show_captcha) && defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== ''): ?>
                            <div class="alert alert-info small mb-3">
                                Please complete the verification below to continue.
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="loginForm" data-lock-until="<?php echo $is_form_locked ? (int)$form_locked_until : 0; ?>">
                            <?php echo csrfField(); ?>
                            <div class="mb-3">
                                <label class="form-label">Email or Username</label>
                                <input type="text" class="form-control" name="email" required autofocus placeholder="Email or username" <?php echo $is_form_locked ? 'disabled' : ''; ?>>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="password" required <?php echo $is_form_locked ? 'disabled' : ''; ?>>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')" <?php echo $is_form_locked ? 'disabled' : ''; ?>>
                                        <i class="bi bi-eye" id="password-icon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3 text-end">
                                <a href="forgot-password.php" class="text-muted small">Forgot Password?</a>
                            </div>
                            <?php if (!empty($show_captcha) && defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== ''): ?>
                                <div class="mb-3">
                                    <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>"></div>
                                </div>
                            <?php endif; ?>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg" id="loginSubmit" <?php echo $is_form_locked ? 'disabled' : ''; ?>>Login</button>
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
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');
            if (!field || !icon) return;
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
        (function () {
            const form = document.getElementById('loginForm');
            if (!form) return;
            const until = parseInt(form.getAttribute('data-lock-until') || '0', 10);
            if (!until || until * 1000 <= Date.now()) return;
            const btn = document.getElementById('loginSubmit');
            const tick = function () {
                const remaining = until - Math.floor(Date.now() / 1000);
                if (remaining <= 0) {
                    window.location.reload();
                    return;
                }
                if (btn) btn.textContent = 'Locked (' + remaining + 's)';
            };
            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>

