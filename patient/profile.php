<?php
/**
 * Denthub Dental Clinic - Patient Profile
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requirePatientLogin();

$db = getDB();
$patient = getCurrentPatient();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php $nav_base = '../'; $nav_patient_base = ''; $nav_active = 'profile'; require_once '../includes/nav-public.php'; ?>

    <main class="denthub-main" style="margin-left:0;">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header denthub-card-header">
                        <h4 class="mb-0">My Profile</h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Patient Number:</strong></div>
                            <div class="col-sm-8"><code><?php echo htmlspecialchars($patient['patient_number']); ?></code></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Name:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
                        </div>
                        <?php if ($patient['middle_name']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Middle Name:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($patient['middle_name']); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Email:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars(maskEmail($patient['email'] ?? '')); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Phone:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars(maskPhone($patient['phone'] ?? '')); ?></div>
                        </div>
                        <?php if ($patient['birthdate']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Birthdate:</strong></div>
                            <div class="col-sm-8"><?php echo formatDate($patient['birthdate']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($patient['gender']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Gender:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($patient['gender']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($patient['address']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Address:</strong></div>
                            <div class="col-sm-8"><?php echo nl2br(htmlspecialchars($patient['address'])); ?></div>
                        </div>
                        <?php endif; ?>

                        <hr>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="edit-profile.php" class="btn btn-outline-primary">Edit phone / email</a>
                            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

