<?php
/**
 * Denthub Dental Clinic - Patient View Appointment
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/export.php';

requirePatientLogin();

$appointment_id = intval($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("SELECT a.*, p.first_name, p.last_name, p.phone, p.email, 
                      s.service_name, d.dentist_id, u.full_name as dentist_name, b.branch_name
                      FROM appointments a
                      JOIN patients p ON a.patient_id = p.patient_id
                      JOIN services s ON a.service_id = s.service_id
                      LEFT JOIN dentists d ON a.dentist_id = d.dentist_id
                      LEFT JOIN users u ON d.user_id = u.user_id
                      LEFT JOIN branches b ON a.branch_id = b.branch_id
                      WHERE a.appointment_id = ? AND a.patient_id = ?");
$stmt->bind_param("ii", $appointment_id, $_SESSION['patient_id']);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    header('Location: dashboard.php');
    exit;
}

// Handle reschedule (patient)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule'])) {
    $new_date = sanitize($_POST['reschedule_date'] ?? '');
    $new_time = sanitize($_POST['reschedule_time'] ?? '');
    $is_reschedulable = in_array($appointment['status'], ['pending', 'confirmed']);

    if ($is_reschedulable && $new_date && $new_time) {
        if (rescheduleAppointment($appointment_id, $new_date, $new_time, null)) {
            header('Location: view-appointment.php?id=' . $appointment_id . '&rescheduled=1');
            exit;
        }
    }
    $reschedule_error = 'Failed to reschedule. Please ensure the slot is available.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment - <?php echo APP_NAME; ?></title>
    <?php $asset_base = '../'; require __DIR__ . '/../includes/head-assets.php'; ?>
</head>
<body class="print-document-page">
    <?php require_once '../includes/patient-sidebar.php'; ?>

    <main class="denthub-main">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="print-only"><?php echo printAppointmentDocument($appointment, 'Appointment confirmation'); ?></div>
                <div class="card shadow print-hide">
                    <div class="card-header denthub-card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Appointment Details</h4>
                        <button type="button" class="btn btn-sm btn-light no-print" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Reference Number:</strong></div>
                            <div class="col-sm-8"><span class="ref-number"><?php echo htmlspecialchars($appointment['appointment_number']); ?></span></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Service:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($appointment['service_name']); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Date:</strong></div>
                            <div class="col-sm-8"><?php echo formatDate($appointment['appointment_date']); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Time:</strong></div>
                            <div class="col-sm-8"><?php echo formatTime($appointment['appointment_time']); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Status:</strong></div>
                            <div class="col-sm-8">
                                <span class="badge bg-<?php echo getStatusBadge($appointment['status']); ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($appointment['dentist_name']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Dentist:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($appointment['dentist_name']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($appointment['reason_for_visit']): ?>
                        <div class="row">
                            <div class="col-sm-4"><strong>Reason for Visit:</strong></div>
                            <div class="col-sm-8"><?php echo nl2br(htmlspecialchars($appointment['reason_for_visit'])); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['rescheduled'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            Your appointment has been rescheduled successfully. You will receive a confirmation email.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($reschedule_error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo htmlspecialchars($reschedule_error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <?php 
                        $is_reschedulable = in_array($appointment['status'], ['pending', 'confirmed']);
                        if ($is_reschedulable): 
                        ?>
                        <hr>
                        <h6 class="mb-2">Reschedule Appointment</h6>
                        <form method="POST" action="" id="rescheduleForm" class="row g-2 align-items-end mb-3">
                            <input type="hidden" name="reschedule" value="1">
                            <div class="col-auto">
                                <label class="form-label small mb-0">New Date</label>
                                <input type="date" class="form-control form-control-sm" name="reschedule_date" id="reschedule_date" required <?php echo bookingDateInputAttributes(false); ?>>
                            </div>
                            <div class="col-auto">
                                <label class="form-label small mb-0">New Time</label>
                                <select class="form-select form-select-sm" name="reschedule_time" id="reschedule_time" required>
                                    <option value="">Select time...</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-warning btn-sm">Reschedule</button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <hr>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.Denthub && Denthub.bindRescheduleSlots) {
                Denthub.bindRescheduleSlots({
                    appointmentId: <?php echo (int)$appointment_id; ?>,
                    dentistId: <?php echo !empty($appointment['dentist_id']) ? (int)$appointment['dentist_id'] : 'null'; ?>,
                    branchId: <?php echo (int)($appointment['branch_id'] ?? 0); ?>,
                    serviceId: <?php echo (int)($appointment['service_id'] ?? 0); ?>
                });
            }
        });
    </script>
    </main>
</body>
</html>

