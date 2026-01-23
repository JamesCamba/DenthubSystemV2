<?php
/**
 * Denthub Dental Clinic - Patient Registration with Email Verification
 */
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/mailer.php';

session_start();

$error = '';
$success = '';
$step = $_GET['step'] ?? 'register'; // register, verify

// Handle verification code submission
if ($step === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $code = sanitize($_POST['verification_code'] ?? '');
    
    if (empty($email) || empty($code)) {
        $error = 'Please enter the verification code.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM email_verification_codes 
                              WHERE email = ? AND verification_code = ? 
                              AND is_used = FALSE AND expires_at > NOW() 
                              ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("ss", $email, $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $verification = $result->fetch_assoc();
            
            // Mark code as used (PostgreSQL boolean)
            $updateStmt = $db->prepare("UPDATE email_verification_codes SET is_used = TRUE WHERE code_id = ?");
            $updateStmt->bind_param("i", $verification['code_id']);
            $updateStmt->execute();
            
            // Verify patient account (PostgreSQL boolean)
            $verifyStmt = $db->prepare("UPDATE patient_accounts SET is_verified = TRUE WHERE email = ?");
            $verifyStmt->bind_param("s", $email);
            $verifyStmt->execute();
            
                            $success = 'Email verified successfully! You can now <a href="login-unified.php">login</a>.';
            $step = 'complete';
        } else {
            $error = 'Invalid or expired verification code. Please try again.';
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
    } else {
        $db = getDB();
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT patient_id FROM patients WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Email already registered. Please login instead.';
        } else {
            // Create patient record
            $patient_number = generatePatientNumber();
            $stmt = $db->prepare("INSERT INTO patients (patient_number, first_name, last_name, middle_name, email, phone, birthdate, gender, address) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssss", $patient_number, $first_name, $last_name, $middle_name, $email, $phone, $birthdate, $gender, $address);
            
            if ($stmt->execute()) {
                $patient_id = $db->insert_id;
                
                // Generate 6-digit verification code BEFORE creating account
                $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                // Save verification code FIRST
                $codeStmt = $db->prepare("INSERT INTO email_verification_codes (email, verification_code, purpose, expires_at) 
                                          VALUES (?, ?, 'registration', ?)");
                $codeStmt->bind_param("sss", $email, $verification_code, $expires_at);
                
                if (!$codeStmt->execute()) {
                    $error = 'Error generating verification code. Please try again.';
                } else {
                    // Send verification email BEFORE creating account
                    $mailer = getMailer();
                    $patient_name = $first_name . ' ' . $last_name;
                    
                    if ($mailer->sendVerificationCode($email, $verification_code, $patient_name)) {
                        // Email sent successfully - NOW create the account (unverified)
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $verification_token = bin2hex(random_bytes(32));
                        
                        $stmt = $db->prepare("INSERT INTO patient_accounts (patient_id, email, password_hash, verification_token, is_verified) 
                                              VALUES (?, ?, ?, ?, FALSE)");
                        $stmt->bind_param("isss", $patient_id, $email, $password_hash, $verification_token);
                        
                        if ($stmt->execute()) {
                            // Success! Redirect to verification step
                            header('Location: register.php?step=verify&email=' . urlencode($email));
                            exit;
                        } else {
                            // Account creation failed - delete verification code
                            $deleteCodeStmt = $db->prepare("DELETE FROM email_verification_codes WHERE email = ? AND verification_code = ?");
                            $deleteCodeStmt->bind_param("ss", $email, $verification_code);
                            $deleteCodeStmt->execute();
                            $error = 'Error creating account. Please try again.';
                        }
                    } else {
                        // Email sending failed - delete verification code and don't create account
                        $deleteCodeStmt = $db->prepare("DELETE FROM email_verification_codes WHERE email = ? AND verification_code = ?");
                        $deleteCodeStmt->bind_param("ss", $email, $verification_code);
                        $deleteCodeStmt->execute();
                        $error = 'Failed to send verification email. Please check your email address and try again.';
                    }
                }
            } else {
                $error = 'Error registering. Please try again.';
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
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-person-plus text-primary" style="font-size: 48px;"></i>
                            <h2 class="mt-3">Create Account</h2>
                            <p class="text-muted">Register to book appointments online</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <?php if ($step === 'verify'): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-envelope-check"></i> A verification code has been sent to <strong><?php echo htmlspecialchars($_GET['email'] ?? ''); ?></strong>
                            </div>
                            <form method="POST" action="">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                                <div class="mb-3">
                                    <label class="form-label">Verification Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control text-center" name="verification_code" 
                                           maxlength="6" pattern="[0-9]{6}" required autofocus
                                           placeholder="000000" style="font-size: 24px; letter-spacing: 8px; font-family: monospace;">
                                    <small class="text-muted">Enter the 6-digit code sent to your email</small>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">Verify Email</button>
                                </div>
                            </form>
                            <div class="text-center mt-3">
                                <p class="text-muted small">Didn't receive the code? <a href="register.php">Register again</a></p>
                            </div>
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
                                <input type="tel" class="form-control" name="phone" required value="<?php echo $_POST['phone'] ?? ''; ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Birthdate</label>
                                    <input type="date" class="form-control" name="birthdate" value="<?php echo $_POST['birthdate'] ?? ''; ?>">
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
                                <input type="password" class="form-control" name="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Register</button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <p>Already have an account? <a href="login-unified.php">Login here</a></p>
                            <a href="index.php" class="text-muted">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

