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
    <?php $asset_base = '../'; require __DIR__ . '/../includes/head-assets.php'; ?>
</head>
<body>
    <?php require_once '../includes/patient-sidebar.php'; ?>

    <main class="denthub-main">
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
                            <div class="col-sm-8"><span class="patient-number"><?php echo htmlspecialchars($patient['patient_number']); ?></span></div>
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
                            <div class="col-sm-8">
                                <span class="sensitive-value">
                                    <span class="sensitive-masked"><?php echo htmlspecialchars(maskEmail($patient['email'] ?? '')); ?></span>
                                    <span class="sensitive-full d-none"><?php echo htmlspecialchars($patient['email'] ?? ''); ?></span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary sensitive-toggle ms-1">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Phone:</strong></div>
                            <div class="col-sm-8">
                                <span class="sensitive-value">
                                    <span class="sensitive-masked"><?php echo htmlspecialchars(maskPhone($patient['phone'] ?? '')); ?></span>
                                    <span class="sensitive-full d-none"><?php echo htmlspecialchars($patient['phone'] ?? ''); ?></span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary sensitive-toggle ms-1">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </span>
                            </div>
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

    <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.sensitive-toggle');
            if (!btn) return;
            const container = btn.closest('.sensitive-value');
            if (!container) return;
            const masked = container.querySelector('.sensitive-masked');
            const full = container.querySelector('.sensitive-full');
            const icon = btn.querySelector('i');
            const isShowing = full && !full.classList.contains('d-none');
            if (isShowing) {
                if (full) full.classList.add('d-none');
                if (masked) masked.classList.remove('d-none');
                if (icon) {
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            } else {
                if (full) full.classList.remove('d-none');
                if (masked) masked.classList.add('d-none');
                if (icon) {
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }
            }
        });
    </script>
</body>
</html>

