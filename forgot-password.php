<?php
/**
 * Denthub Dental Clinic - Forgot Password with OTP
 */
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/mailer.php';

session_start();

// Simple rate limiting for password reset attempts
if (!isset($_SESSION['reset_attempts'])) {
    $_SESSION['reset_attempts'] = 0;
}
if (!isset($_SESSION['reset_block_until'])) {
    $_SESSION['reset_block_until'] = 0;
}

$error = '';
$success = '';
$step = $_GET['step'] ?? 'request'; // request, verify, reset

// Handle resend code request
if ($step === 'verify' && isset($_POST['resend_code'])) {
    $email = sanitize($_POST['email'] ?? '');
    
    if (time() < $_SESSION['reset_block_until']) {
        $error = 'Too many attempts. Please try again in 1 hour.';
    } elseif (empty($email)) {
        $error = 'Email address is required.';
    } else {
        $_SESSION['reset_attempts']++;
        if ($_SESSION['reset_attempts'] > 5) {
            $_SESSION['reset_block_until'] = time() + 3600;
            $error = 'Too many attempts. Please try again in 1 hour.';
        } else {
        $db = getDB();
        
        // Check if email exists in patient_accounts
        $stmt = $db->prepare("SELECT account_id FROM patient_accounts WHERE email = ? AND is_verified = TRUE");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = 'Email not found or account not verified.';
        } else {
            // Generate new verification code
            $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Invalidate old codes for this email
            $invalidateStmt = $db->prepare("UPDATE email_verification_codes SET is_used = TRUE WHERE email = ? AND purpose = 'password_reset'");
            $invalidateStmt->bind_param("s", $email);
            $invalidateStmt->execute();
            
            // Save new verification code
            $codeStmt = $db->prepare("INSERT INTO email_verification_codes (email, verification_code, purpose, expires_at) 
                                      VALUES (?, ?, 'password_reset', ?)");
            $codeStmt->bind_param("sss", $email, $verification_code, $expires_at);
            
            if ($codeStmt->execute()) {
                // Get patient name for email
                $patientStmt = $db->prepare("SELECT p.first_name, p.last_name FROM patients p 
                                           JOIN patient_accounts pa ON p.patient_id = pa.patient_id 
                                           WHERE pa.email = ?");
                $patientStmt->bind_param("s", $email);
                $patientStmt->execute();
                $patientResult = $patientStmt->get_result();
                $patient = $patientResult->fetch_assoc();
                $patient_name = ($patient ? $patient['first_name'] . ' ' . $patient['last_name'] : 'Valued Patient');
                
                // Send verification email
                $mailer = getMailer();
                
                if ($mailer->sendVerificationCode($email, $verification_code, $patient_name, 'password_reset')) {
                    $success = 'Verification code has been resent to your email.';
                } else {
                    $error = 'Failed to resend verification email. Please try again.';
                }
            } else {
                $error = 'Error generating verification code. Please try again.';
            }
        }
        }
    }
}

// Handle password reset request
if ($step === 'request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    if (time() < $_SESSION['reset_block_until']) {
        $error = 'Too many attempts. Please try again in 1 hour.';
    } elseif (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email address.';
    } else {
        $_SESSION['reset_attempts']++;
        if ($_SESSION['reset_attempts'] > 5) {
            $_SESSION['reset_block_until'] = time() + 3600;
            $error = 'Too many attempts. Please try again in 1 hour.';
        } else {
            $db = getDB();
            
            // Check if email exists in patient_accounts (verified accounts only)
            $stmt = $db->prepare("SELECT account_id FROM patient_accounts WHERE email = ? AND is_verified = TRUE");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = 'Email not found or account not verified.';
            } else {
                // Generate 6-digit verification code
                $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                // Invalidate any old codes for this email
                $invalidateStmt = $db->prepare("UPDATE email_verification_codes SET is_used = TRUE WHERE email = ? AND purpose = 'password_reset'");
                $invalidateStmt->bind_param("s", $email);
                $invalidateStmt->execute();
                
                // Save verification code
                $codeStmt = $db->prepare("INSERT INTO email_verification_codes (email, verification_code, purpose, expires_at) 
                                          VALUES (?, ?, 'password_reset', ?)");
                $codeStmt->bind_param("sss", $email, $verification_code, $expires_at);
                
                if (!$codeStmt->execute()) {
                    $error = 'Error generating verification code. Please try again.';
                } else {
                    // Get patient name for email
                    $patientStmt = $db->prepare("SELECT p.first_name, p.last_name FROM patients p 
                                               JOIN patient_accounts pa ON p.patient_id = pa.patient_id 
                                               WHERE pa.email = ?");
                    $patientStmt->bind_param("s", $email);
                    $patientStmt->execute();
                    $patientResult = $patientStmt->get_result();
                    $patient = $patientResult->fetch_assoc();
                    $patient_name = ($patient ? $patient['first_name'] . ' ' . $patient['last_name'] : 'Valued Patient');
                    
                    // Send verification email
                    $mailer = getMailer();
                    
                    if ($mailer->sendVerificationCode($email, $verification_code, $patient_name, 'password_reset')) {
                        // Store email in session for verification step
                        $_SESSION['password_reset_email'] = $email;
                        header('Location: forgot-password.php?step=verify&email=' . urlencode($email));
                        exit;
                    } else {
                        // Email sending failed - delete verification code
                        $deleteCodeStmt = $db->prepare("DELETE FROM email_verification_codes WHERE email = ? AND verification_code = ?");
                        $deleteCodeStmt->bind_param("ss", $email, $verification_code);
                        $deleteCodeStmt->execute();
                        $error = 'Failed to send verification email. Please try again.';
                    }
                }
            }
        }
    }
}

// Handle verification code submission
if ($step === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['resend_code'])) {
    $email = sanitize($_POST['email'] ?? '');
    $code = sanitize($_POST['verification_code'] ?? '');
    
    if (time() < $_SESSION['reset_block_until']) {
        $error = 'Too many attempts. Please try again in 1 hour.';
    } elseif (empty($email) || empty($code)) {
        $error = 'Please enter the verification code.';
    } else {
        $_SESSION['reset_attempts']++;
        if ($_SESSION['reset_attempts'] > 5) {
            $_SESSION['reset_block_until'] = time() + 3600;
            $error = 'Too many attempts. Please try again in 1 hour.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM email_verification_codes 
                                  WHERE email = ? AND verification_code = ? 
                                  AND is_used = FALSE AND expires_at > NOW() 
                                  AND purpose = 'password_reset'
                                  ORDER BY created_at DESC LIMIT 1");
            $stmt->bind_param("ss", $email, $code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $verification = $result->fetch_assoc();
                
                // Mark code as used
                $updateStmt = $db->prepare("UPDATE email_verification_codes SET is_used = TRUE WHERE code_id = ?");
                $updateStmt->bind_param("i", $verification['code_id']);
                $updateStmt->execute();
                
                // Store verified email in session for password reset step
                $_SESSION['password_reset_verified'] = $email;
                header('Location: forgot-password.php?step=reset&email=' . urlencode($email));
                exit;
            } else {
                $error = 'Invalid or expired verification code. Please try again.';
            }
        }
    }
}

// Handle password reset
if ($step === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Verify session
    if (!isset($_SESSION['password_reset_verified']) || $_SESSION['password_reset_verified'] !== $email) {
        $error = 'Session expired. Please start over.';
        $step = 'request';
    } elseif (empty($password) || empty($confirm_password)) {
        $error = 'Please enter and confirm your new password.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        
        // Get patient_id for logging
        $pidStmt = $db->prepare("SELECT patient_id FROM patient_accounts WHERE email = ? AND is_verified = TRUE");
        $pidStmt->bind_param("s", $email);
        $pidStmt->execute();
        $pidRow = $pidStmt->get_result()->fetch_assoc();
        $patient_id_for_log = $pidRow ? (int)$pidRow['patient_id'] : null;

        // Update password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE patient_accounts SET password_hash = ? WHERE email = ? AND is_verified = TRUE");
        $stmt->bind_param("ss", $password_hash, $email);
        
        if ($stmt->execute()) {
            logActivity('password_changed', 'Patient password reset via forgot-password', null, $patient_id_for_log);
            // Clear session
            unset($_SESSION['password_reset_verified']);
            unset($_SESSION['password_reset_email']);
            
            $success = 'Password reset successfully! You can now <a href="login.php">login</a> with your new password.';
            $step = 'complete';
        } else {
            $error = 'Error updating password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-key text-primary" style="font-size: 48px;"></i>
                            <h2 class="mt-3">Reset Password</h2>
                            <p class="text-muted">Enter your email to receive a verification code</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <?php if ($step === 'verify'): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-envelope-check"></i> A verification code has been sent to <strong><?php echo htmlspecialchars(maskEmail($_GET['email'] ?? '')); ?></strong>
                            </div>
                            <form method="POST" action="" id="verifyForm">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                                <div class="mb-3">
                                    <label class="form-label">Verification Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control text-center" name="verification_code" 
                                           maxlength="6" pattern="[0-9]{6}" required autofocus
                                           placeholder="000000" style="font-size: 24px; letter-spacing: 8px; font-family: monospace;">
                                    <small class="text-muted">Enter the 6-digit code sent to your email</small>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">Verify Code</button>
                                    <button type="submit" name="resend_code" class="btn btn-outline-secondary" onclick="event.preventDefault(); document.getElementById('resendForm').submit();">
                                        <i class="bi bi-arrow-clockwise"></i> Resend Code
                                    </button>
                                </div>
                            </form>
                            <form method="POST" action="" id="resendForm" style="display:none;">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                                <input type="hidden" name="resend_code" value="1">
                            </form>
                        <?php elseif ($step === 'reset'): ?>
                            <?php if (!isset($_SESSION['password_reset_verified']) || $_SESSION['password_reset_verified'] !== ($_GET['email'] ?? '')): ?>
                                <div class="alert alert-danger">Session expired. Please start over.</div>
                                <div class="d-grid">
                                    <a href="forgot-password.php" class="btn btn-primary">Start Over</a>
                                </div>
                            <?php else: ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                                    <div class="mb-3">
                                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="password" id="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                                <i class="bi bi-eye" id="password-icon"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                <i class="bi bi-eye" id="confirm_password-icon"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        <?php elseif ($step === 'complete'): ?>
                            <div class="text-center">
                                <i class="bi bi-check-circle text-success" style="font-size: 64px;"></i>
                                <h3 class="mt-3 text-success">Password Reset Complete!</h3>
                                <p class="text-muted">Your password has been successfully reset. You can now login with your new password.</p>
                                <a href="login.php" class="btn btn-primary btn-lg">Go to Login</a>
                            </div>
                        <?php else: ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required autofocus 
                                       placeholder="Enter your registered email">
                                <small class="text-muted">We'll send a verification code to this email</small>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Send Verification Code</button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <p>Remember your password? <a href="login.php">Login here</a></p>
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
