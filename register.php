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

$error = '';
$success = '';
$step = $_GET['step'] ?? 'register'; // register, verify

// Handle resend code request
if ($step === 'verify' && isset($_POST['resend_code'])) {
    $email = sanitize($_POST['email'] ?? '');
    
    if (time() < $_SESSION['register_block_until']) {
        $error = 'Too many attempts. Please try again in 1 hour.';
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
                $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
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

                    $stmt = $db->prepare("INSERT INTO patients (patient_number, first_name, last_name, middle_name, email, phone, birthdate, gender, address, phone_hash, first_name_metaphone, last_name_metaphone) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param(
                        "ssssssssssss",
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
                        $last_meta
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
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $birthdate = $_POST['birthdate'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = sanitize($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email address.';
    } elseif (!validatePhone($phone)) {
        $error = 'Invalid phone number.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!empty($birthdate) && strtotime($birthdate) > strtotime('today')) {
        $error = 'Birthdate cannot be in the future.';
    } elseif (!empty($birthdate) && (int)date('Y', strtotime($birthdate)) > (int)date('Y')) {
        $error = 'Birthdate year cannot exceed current year.';
    } else {
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
                    $error = 'Email already registered. Please login instead.';
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
                            $error = 'Possible duplicate found: ' . htmlspecialchars($dupName) . ' (born ' . htmlspecialchars($dupDob) . '). Please contact the clinic before creating a new account.';
                        }
                    }

                    // Only proceed if no duplicate was found
                    if (empty($error)) {
                        // Store registration data in session (NOT in database yet), include metaphone keys
                    $_SESSION['pending_registration'] = [
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'middle_name' => $middle_name,
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
                    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
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
                            // Email sent successfully - Redirect to verification step
                            header('Location: register.php?step=verify&email=' . urlencode($email));
                            exit;
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
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
                                    <input type="text" class="form-control" name="first_name" required value="<?php echo $_POST['first_name'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="last_name" required value="<?php echo $_POST['last_name'] ?? ''; ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name" value="<?php echo $_POST['middle_name'] ?? ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required value="<?php echo $_POST['email'] ?? ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="phone" required maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                <small class="text-muted">Philippines mobile: 11 digits (e.g. 09161234567)</small>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Birthdate</label>
                                    <input type="date" class="form-control" name="birthdate" 
                                           max="<?php echo date('Y-m-d'); ?>" 
                                           value="<?php echo $_POST['birthdate'] ?? ''; ?>">
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
                                <textarea class="form-control" name="address" rows="2"><?php echo $_POST['address'] ?? ''; ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                        <i class="bi bi-eye" id="password-icon"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                        <i class="bi bi-eye" id="confirm_password-icon"></i>
                                    </button>
                                </div>
                            </div>
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

