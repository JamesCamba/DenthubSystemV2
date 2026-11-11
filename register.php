<?php
/**
 * Denthub Dental Clinic - Patient Registration with Email Verification
 */
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/mailer.php';

// Simple rate limiting to prevent OTP/registration brute force
if (!isset($_SESSION['register_attempts'])) {
    $_SESSION['register_attempts'] = 0;
}
if (!isset($_SESSION['register_block_until'])) {
    $_SESSION['register_block_until'] = 0;
}
if (!isset($_SESSION['register_resend_count'])) {
    $_SESSION['register_resend_count'] = 0;
}
if (!isset($_SESSION['register_next_resend_at'])) {
    $_SESSION['register_next_resend_at'] = 0;
}

$error = '';
$success = '';
$errors = [];
$step = $_GET['step'] ?? 'register'; // register, verify

$reg_ip = getClientIpAddress();
if ($step === 'register' && isIpRegistrationBlocked($reg_ip)) {
    $error = 'Too many registrations from this network. Please try again later.';
}

// Handle resend code request
if ($step === 'verify' && isset($_POST['resend_code'])) {
    $email = sanitize($_POST['email'] ?? '');
    $now = time();

    if ($now < $_SESSION['register_block_until']) {
        $error = 'Too many attempts. Please try again in 1 hour.';
    } elseif ($now < ($_SESSION['register_next_resend_at'] ?? 0)) {
        $waitSecs = $_SESSION['register_next_resend_at'] - $now;
        $waitMins = max(1, ceil($waitSecs / 60));
        $error = 'Please wait ' . $waitMins . ' minute(s) before requesting another code.';
    } elseif (empty($email)) {
        $error = 'Email address is required.';
    } else {
        $_SESSION['register_attempts']++;
        if ($_SESSION['register_attempts'] > 5) {
            $_SESSION['register_block_until'] = time() + 3600; // 1 hour
            $error = 'Too many attempts. Please try again in 1 hour.';
        } else {
            // Check if there's pending registration data in session
            if (isset($_SESSION['pending_registration']) && $_SESSION['pending_registration']['email'] === $email) {
                $db = getDB();
                
                // Generate new verification code
                $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Invalidate old codes for this email
                $invalidateStmt = $db->prepare("UPDATE email_verification_codes SET is_used = TRUE WHERE email = ? AND purpose = 'registration'");
                $invalidateStmt->bind_param("s", $email);
                $invalidateStmt->execute();
                
                // Save new verification code
                $codeStmt = $db->prepare("INSERT INTO email_verification_codes (email, verification_code, purpose, expires_at) 
                                          VALUES (?, ?, 'registration', ?)");
                $codeStmt->bind_param("sss", $email, $verification_code, $expires_at);
                
                if ($codeStmt->execute()) {
                    // Send verification email
                    $mailer = getMailer();
                    $patient_name = $_SESSION['pending_registration']['first_name'] . ' ' . $_SESSION['pending_registration']['last_name'];
                    
                    if ($mailer->sendVerificationCode($email, $verification_code, $patient_name)) {
                        $success = 'Verification code has been resent to your email.';
                        // Cooldown sequence: 5 mins, then 15, then 30 for subsequent resends
                        $_SESSION['register_resend_count'] = (int)$_SESSION['register_resend_count'] + 1;
                        $cooldownMinutes = 5;
                        if ($_SESSION['register_resend_count'] >= 2 && $_SESSION['register_resend_count'] < 3) {
                            $cooldownMinutes = 15;
                        } elseif ($_SESSION['register_resend_count'] >= 3) {
                            $cooldownMinutes = 30;
                        }
                        $_SESSION['register_next_resend_at'] = $now + ($cooldownMinutes * 60);
                    } else {
                        $error = 'Failed to resend verification email. Please try again.';
                    }
                } else {
                    $error = 'Error generating verification code. Please try again.';
                }
            } else {
                $error = 'No pending registration found. Please register again.';
            }
        }
    }
}

// Handle verification code submission
if ($step === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['resend_code'])) {
    $email = sanitize($_POST['email'] ?? '');
    $code  = sanitize($_POST['verification_code'] ?? '');

    if (time() < $_SESSION['register_block_until']) {
        $error = 'Too many attempts. Please try again in 1 hour.';
    } elseif (empty($email) || empty($code)) {
        $error = 'Please enter the verification code.';
    } else {
        $_SESSION['register_attempts']++;

        if ($_SESSION['register_attempts'] > 5) {
            $_SESSION['register_block_until'] = time() + 3600;
            $error = 'Too many attempts. Please try again in 1 hour.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM email_verification_codes 
                                  WHERE email = ? AND verification_code = ? 
                                  AND is_used = FALSE AND expires_at > NOW() 
                                  AND purpose = 'registration'
                                  ORDER BY created_at DESC LIMIT 1");
            $stmt->bind_param("ss", $email, $code);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $verification = $result->fetch_assoc();

                // Check if we have pending registration data
                if (!isset($_SESSION['pending_registration']) || $_SESSION['pending_registration']['email'] !== $email) {
                    $error = 'Registration session expired. Please register again.';
                } else {
                    $reg_data = $_SESSION['pending_registration'];

                    // Mark code as used (PostgreSQL boolean)
                    $updateStmt = $db->prepare("UPDATE email_verification_codes SET is_used = TRUE WHERE code_id = ?");
                    $updateStmt->bind_param("i", $verification['code_id']);
                    $updateStmt->execute();

                    // NOW create patient record (only after verification)
                    $patient_number = generatePatientNumber();
                    $phone_hash     = $reg_data['phone'] ? hash('sha256', $reg_data['phone']) : null;
                    $first_meta     = $reg_data['first_name_metaphone'] ?? null;
                    $last_meta      = $reg_data['last_name_metaphone'] ?? null;

                    $stmt = $db->prepare("INSERT INTO patients (patient_number, first_name, last_name, middle_name, email, phone, birthdate, gender, address, phone_hash, first_name_metaphone, last_name_metaphone, suffix) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param(
                        "sssssssssssss",
                        $patient_number,
                        $reg_data['first_name'],
                        $reg_data['last_name'],
                        $reg_data['middle_name'],
                        $reg_data['email'],
                        $reg_data['phone'],
                        $reg_data['birthdate'],
                        $reg_data['gender'],
                        $reg_data['address'],
                        $phone_hash,
                        $first_meta,
                        $last_meta,
                        $reg_data['suffix'] ?? null
                    );

                    if ($stmt->execute()) {
                        $patient_id = $db->insert_id;

                        // Create patient account (verified)
                        $password_hash      = password_hash($reg_data['password'], PASSWORD_DEFAULT);
                        $verification_token = bin2hex(random_bytes(32));

                        $accountStmt = $db->prepare("INSERT INTO patient_accounts (patient_id, email, password_hash, verification_token, is_verified) 
                                                      VALUES (?, ?, ?, ?, TRUE)");
                        $accountStmt->bind_param("isss", $patient_id, $reg_data['email'], $password_hash, $verification_token);

                        if ($accountStmt->execute()) {
                            // Clear pending registration data
                            unset($_SESSION['pending_registration']);

                            $success = 'Email verified successfully! You can now <a href="login.php">login</a>.';
                            $step    = 'complete';
                        } else {
                            // Rollback: delete patient record if account creation fails
                            $deleteStmt = $db->prepare("DELETE FROM patients WHERE patient_id = ?");
                            $deleteStmt->bind_param("i", $patient_id);
                            $deleteStmt->execute();
                            $error = 'Error creating account. Please try again.';
                        }
                    } else {
                        $error = 'Error creating patient record. Please try again.';
                    }
                }
            } else {
                $error = 'Invalid or expired verification code. Please try again.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'register') {
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $middle_name = sanitize($_POST['middle_name'] ?? '');
    $suffix = sanitize($_POST['suffix'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $birthdate = $_POST['birthdate'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = sanitize($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $captcha_token = $_POST['g-recaptcha-response'] ?? '';
    $accept_terms = isset($_POST['accept_terms']) ? (bool)$_POST['accept_terms'] : false;

    // Validation (collect field-specific errors)
    if (empty($first_name)) {
        $errors['first_name'] = 'First name is required.';
    }
    if (empty($last_name)) {
        $errors['last_name'] = 'Last name is required.';
    }
    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    } elseif (!validateAllowedEmailDomain($email)) {
        $errors['email'] = 'Please use a valid Gmail or Yahoo email address.';
    }
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required.';
    } elseif (!validatePhone($phone)) {
        $errors['phone'] = 'Invalid phone number.';
    }
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (!validatePasswordStrength($password)) {
        $errors['password'] = 'Password must be 8–12 chars with at least 1 uppercase letter, 1 number, and 1 special character.';
    }
    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    if (!empty($birthdate)) {
        $birth_ts = strtotime($birthdate);
        if ($birth_ts === false) {
            $errors['birthdate'] = 'Invalid birthdate.';
        } else {
            $today = new DateTimeImmutable('today');
            $dob = new DateTimeImmutable($birthdate);
            $age = (int)$dob->diff($today)->y;
            if ($age < 18 || $age > 90) {
                $errors['birthdate'] = 'You must be between 18 and 90 years old.';
            }
        }
    }
    if (defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== '' && $captcha_token === '') {
        $errors['captcha'] = 'Please complete the verification below.';
    } elseif (defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== '' && !verifyRecaptchaV2($captcha_token)) {
        $errors['captcha'] = 'Verification failed. Please try again.';
    }
    if (!$accept_terms) {
        $errors['accept_terms'] = 'You must read and agree to the Terms & Agreement before creating an account.';
    }
    if (isIpRegistrationBlocked($reg_ip)) {
        $error = 'Too many registrations from this network. Please try again later.';
    }

    if (empty($errors) && empty($error)) {
        if (time() < $_SESSION['register_block_until']) {
            $error = 'Too many attempts. Please try again in 1 hour.';
        } else {
            $_SESSION['register_attempts']++;
            if ($_SESSION['register_attempts'] > 5) {
                $_SESSION['register_block_until'] = time() + 3600;
                $error = 'Too many attempts. Please try again in 1 hour.';
            } else {
                $db = getDB();
                
                // Check if email already exists in patient_accounts (verified accounts)
                $stmt = $db->prepare("SELECT account_id FROM patient_accounts WHERE email = ? AND is_verified = TRUE");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $errors['email'] = 'Email already registered. Please login instead.';
                } else {
                    // Duplicate prevention using Metaphone 3 and birthdate window
                    $metaKeys = getMetaphone3Keys($first_name, $last_name);
                    if (!empty($birthdate)) {
                        $dupStmt = $db->prepare("
                            SELECT patient_id, first_name, last_name, birthdate
                            FROM patients
                            WHERE first_name_metaphone = ?
                              AND last_name_metaphone = ?
                              AND birthdate IS NOT NULL
                              AND ?::date BETWEEN (birthdate - INTERVAL '30 days') AND (birthdate + INTERVAL '30 days')
                            LIMIT 1
                        ");
                        $dupStmt->bind_param("sss", $metaKeys['first'], $metaKeys['last'], $birthdate);
                        $dupStmt->execute();
                        $dup = $dupStmt->get_result()->fetch_assoc();
                        if ($dup) {
                            $dupName = $dup['first_name'] . ' ' . $dup['last_name'];
                            $dupDob  = $dup['birthdate'];
                            $errors['birthdate'] = 'Possible duplicate found: ' . htmlspecialchars($dupName) . ' (born ' . htmlspecialchars($dupDob) . '). Please contact the clinic before creating a new account.';
                        }
                    }

                    // Only proceed if no duplicate was found
                    if (empty($errors) && empty($error)) {
                        // Store registration data in session (NOT in database yet), include metaphone keys
                    $_SESSION['pending_registration'] = [
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'middle_name' => $middle_name,
                        'suffix' => $suffix,
                        'email' => $email,
                        'phone' => $phone,
                        'birthdate' => $birthdate ?: null,
                        'gender' => $gender ?: null,
                        'address' => $address ?: null,
                        'password' => $password,
                        'first_name_metaphone' => $metaKeys['first'],
                        'last_name_metaphone' => $metaKeys['last'],
                    ];
                    
                    // Generate 6-digit verification code
                    $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    
                    // Invalidate any old codes for this email
                    $invalidateStmt = $db->prepare("UPDATE email_verification_codes SET is_used = TRUE WHERE email = ? AND purpose = 'registration'");
                    $invalidateStmt->bind_param("s", $email);
                    $invalidateStmt->execute();
                    
                    // Save verification code
                    $codeStmt = $db->prepare("INSERT INTO email_verification_codes (email, verification_code, purpose, expires_at) 
                                              VALUES (?, ?, 'registration', ?)");
                    $codeStmt->bind_param("sss", $email, $verification_code, $expires_at);
                    
                    if (!$codeStmt->execute()) {
                        $error = 'Error generating verification code. Please try again.';
                    } else {
                        // Send verification email
                        $mailer = getMailer();
                        $patient_name = $first_name . ' ' . $last_name;
                        
                        if ($mailer->sendVerificationCode($email, $verification_code, $patient_name, 'registration')) {
                            // Count successful "start registration" events per IP (OTP sent).
                            if (!recordRegistrationForIp($reg_ip)) {
                                // Roll back code/session if IP just got blocked at threshold
                                $deleteCodeStmt = $db->prepare("DELETE FROM email_verification_codes WHERE email = ? AND verification_code = ?");
                                $deleteCodeStmt->bind_param("ss", $email, $verification_code);
                                $deleteCodeStmt->execute();
                                unset($_SESSION['pending_registration']);
                                $error = 'Too many registrations from this network. Please try again later.';
                            } else {
                            // Email sent successfully - Redirect to verification step
                            header('Location: register.php?step=verify&email=' . urlencode($email));
                            exit;
                            }
                        } else {
                            // Email sending failed - delete verification code
                            $deleteCodeStmt = $db->prepare("DELETE FROM email_verification_codes WHERE email = ? AND verification_code = ?");
                            $deleteCodeStmt->bind_param("ss", $email, $verification_code);
                            $deleteCodeStmt->execute();
                            unset($_SESSION['pending_registration']);
                            $error = 'Failed to send verification email. Please check your email address and try again.';
                        }
                    }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <?php $asset_base = ''; require __DIR__ . '/includes/head-assets.php'; ?>
</head>
<body>
    <?php $nav_active = ''; require_once 'includes/nav-public.php'; ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="denthub-auth-wrap shadow">
                    <div class="text-center mb-4">
                        <img src="assets/images/logobluetransparent.png" alt="<?php echo APP_NAME; ?>" style="max-height: 64px; width: auto;" onerror="this.style.display='none'">
                        <h2 class="mt-3 text-primary fw-bold">Create Account</h2>
                        <p class="text-muted">Register to book appointments online</p>
                    </div>
                    <div class="card shadow-sm border-0 bg-white">
                    <div class="card-body p-5">

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo e($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo e($success); ?></div>
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
                                    <?php if (!empty($error)): ?>
                                        <div class="text-danger small mt-1"><?php echo e($error); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">Verify Email</button>
                                    <button type="submit" name="resend_code" class="btn btn-outline-secondary" onclick="event.preventDefault(); document.getElementById('resendForm').submit();">
                                        <i class="bi bi-arrow-clockwise"></i> Resend Code
                                    </button>
                                </div>
                            </form>
                            <form method="POST" action="" id="resendForm" style="display:none;">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                                <input type="hidden" name="resend_code" value="1">
                            </form>
                        <?php elseif ($step === 'complete'): ?>
                            <div class="text-center">
                                <i class="bi bi-check-circle text-success" style="font-size: 64px;"></i>
                                <h3 class="mt-3 text-success">Registration Complete!</h3>
                                <p class="text-muted">Your email has been verified. You can now login to your account.</p>
                                <a href="login.php" class="btn btn-primary btn-lg">Go to Login</a>
                            </div>
                        <?php else: ?>
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="first_name" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                                    <?php if (!empty($errors['first_name'])): ?>
                                        <div class="text-danger small mt-1"><?php echo e($errors['first_name']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="last_name" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                                    <?php if (!empty($errors['last_name'])): ?>
                                        <div class="text-danger small mt-1"><?php echo e($errors['last_name']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Suffix</label>
                                    <input type="text" class="form-control" name="suffix" placeholder="Jr, Sr, II" value="<?php echo htmlspecialchars($_POST['suffix'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                <?php if (!empty($errors['email'])): ?>
                                    <div class="text-danger small mt-1"><?php echo e($errors['email']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="phone" required maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                <small class="text-muted">Philippines mobile: 11 digits (e.g. 09161234567)</small>
                                <?php if (!empty($errors['phone'])): ?>
                                    <div class="text-danger small mt-1"><?php echo e($errors['phone']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Birthdate</label>
                                    <?php
                                        $today = new DateTime('today');
                                        $maxDob = $today->modify('-18 years')->format('Y-m-d');
                                        $minDob = (new DateTime('today'))->modify('-90 years')->format('Y-m-d');
                                    ?>
                                    <input type="date" class="form-control" name="birthdate" 
                                           min="<?php echo $minDob; ?>" 
                                           max="<?php echo $maxDob; ?>" 
                                           value="<?php echo htmlspecialchars($_POST['birthdate'] ?? ''); ?>">
                                    <?php if (!empty($errors['birthdate'])): ?>
                                        <div class="text-danger small mt-1"><?php echo e($errors['birthdate']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender">
                                        <option value="">Select...</option>
                                        <option value="Male" <?php echo (($_POST['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (($_POST['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo (($_POST['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2"><?php echo e($_POST['address'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" maxlength="12">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                        <i class="bi bi-eye" id="password-icon"></i>
                                    </button>
                                </div>
                                <small class="text-muted">8–12 characters, with at least 1 uppercase letter, 1 number, and 1 special character.</small>
                                <?php if (!empty($errors['password'])): ?>
                                    <div class="text-danger small mt-1"><?php echo e($errors['password']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                        <i class="bi bi-eye" id="confirm_password-icon"></i>
                                    </button>
                                </div>
                                <?php if (!empty($errors['confirm_password'])): ?>
                                    <div class="text-danger small mt-1"><?php echo e($errors['confirm_password']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accept_terms" id="accept_terms" value="1" <?php echo !empty($_POST['accept_terms']) ? 'checked' : ''; ?> required>
                                    <label class="form-check-label small" for="accept_terms">
                                        I have read and agree to the
                                        <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="modal" data-bs-target="#termsModal">
                                            Terms &amp; Agreement
                                        </button>
                                        for using <?php echo APP_NAME; ?>.
                                    </label>
                                </div>
                                <?php if (!empty($errors['accept_terms'])): ?>
                                    <div class="text-danger small mt-1"><?php echo e($errors['accept_terms']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if (defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== ''): ?>
                            <div class="mb-3">
                                <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>"></div>
                                <?php if (!empty($errors['captcha'])): ?>
                                    <div class="text-danger small mt-1"><?php echo e($errors['captcha']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Register</button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                            <a href="index.php" class="text-muted">Back to Home</a>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms & Agreement Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">Terms &amp; Agreement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Please read these terms carefully. By creating an account and booking appointments, you agree to the conditions below.
                    </p>

                    <h6 class="fw-bold">1. Acceptable Use</h6>
                    <p class="mb-3">
                        You agree to use <?php echo APP_NAME; ?> only for legitimate dental appointment scheduling.
                        Creating fake accounts or <strong>submitting spam, fake bookings, or abusive content</strong> is strictly prohibited.
                        We reserve the right to review suspicious activity and take appropriate action, including account suspension.
                    </p>

                    <h6 class="fw-bold">2. Appointment Policies</h6>
                    <p class="mb-3">
                        When you book an appointment, you agree to our scheduling rules, including any
                        <strong>cancellation windows and no-show policies</strong> set by the clinic.
                        Repeated no-shows or late cancellations may limit your ability to book online and can affect future scheduling privileges.
                    </p>

                    <h6 class="fw-bold">3. Data Handling &amp; Privacy</h6>
                    <p class="mb-3">
                        We collect your personal information (such as name, contact details, and appointment history)
                        <strong>only for clinic operations, appointment scheduling, and communication about your care</strong>.
                        Your data is stored in secure systems with access limited to authorized clinic personnel.
                        We do not sell your personal data, and we only share it when required for providing services or when required by law.
                    </p>

                    <h6 class="fw-bold">4. Medical &amp; Emergency Disclaimer</h6>
                    <p class="mb-3">
                        This system is intended <strong>only for scheduling and managing dental appointments</strong>.
                        It does not provide medical diagnosis, treatment, or emergency services.
                        For emergencies or urgent medical concerns, contact your local emergency services or visit the nearest emergency facility instead of using this system.
                    </p>

                    <h6 class="fw-bold">5. Account Suspension &amp; Termination</h6>
                    <p class="mb-3">
                        The clinic reserves the right to <strong>suspend or delete accounts</strong> that violate these terms,
                        including but not limited to abusive behavior, fraudulent use, security violations, or attempts to disrupt system operations.
                        Actions may also include blocking further access from associated devices or networks where necessary to protect the system.
                    </p>

                    <h6 class="fw-bold">6. Security, Logging &amp; IP Address</h6>
                    <p class="mb-0">
                        For security and fraud prevention, the system may record device and network details such as
                        <strong>IP address, browser information, and login activity</strong>.
                        These logs are visible only to authorized administrators and are used solely to
                        <strong>detect suspicious activity or possible attacks</strong>, investigate incidents, and protect patient accounts.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
        
        // Prevent date input from accepting future dates or invalid years
        document.addEventListener('DOMContentLoaded', function() {
            const birthdateInput = document.querySelector('input[name="birthdate"]');
            if (birthdateInput) {
                const currentYear = new Date().getFullYear();
                const maxDate = new Date().toISOString().split('T')[0];
                birthdateInput.setAttribute('max', maxDate);
                
                birthdateInput.addEventListener('input', function() {
                    const selectedDate = new Date(this.value);
                    const selectedYear = selectedDate.getFullYear();
                    
                    if (selectedYear > currentYear) {
                        this.setCustomValidity('Birthdate year cannot exceed current year');
                    } else if (selectedDate > new Date()) {
                        this.setCustomValidity('Birthdate cannot be in the future');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
        });
    </script>
</body>
</html>

