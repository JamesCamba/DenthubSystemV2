<?php
/**
 * Denthub Dental Clinic - View Appointment Details
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$appointment_id = intval($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("SELECT a.*, p.*, s.service_name, s.price, u.full_name as dentist_name, b.branch_name
                      FROM appointments a
                      JOIN patients p ON a.patient_id = p.patient_id
                      JOIN services s ON a.service_id = s.service_id
                      LEFT JOIN dentists d ON a.dentist_id = d.dentist_id
                      LEFT JOIN users u ON d.user_id = u.user_id
                      LEFT JOIN branches b ON a.branch_id = b.branch_id
                      WHERE a.appointment_id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    header('Location: appointments.php');
    exit;
}

// Handle status update (use centralized function so confirmation/cancellation emails are sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $status = sanitize($_POST['status']);
    $notes = sanitize($_POST['notes'] ?? '');
    
    $current_status = $appointment['status'];
    $allowed_statuses = array_keys(getAvailableStatusOptions($current_status, $appointment['appointment_date']));
    
    if (in_array($status, $allowed_statuses) && updateAppointmentStatus($appointment_id, $status, $notes, $_SESSION['user_id'] ?? null)) {
        header('Location: view-appointment.php?id=' . $appointment_id . '&updated=1');
        exit;
    }
}

// Handle reschedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule'])) {
    $new_date = sanitize($_POST['reschedule_date'] ?? '');
    $new_time = sanitize($_POST['reschedule_time'] ?? '');
    $is_reschedulable = in_array($appointment['status'], ['pending', 'confirmed']);

    if ($is_reschedulable && $new_date && $new_time) {
        if (rescheduleAppointment($appointment_id, $new_date, $new_time, $_SESSION['user_id'] ?? null)) {
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Appointment Details</h2>
            <a href="appointments.php" class="btn btn-secondary">Back to Appointments</a>
        </div>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Appointment status updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['rescheduled'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Appointment rescheduled successfully. Patient has been notified.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($reschedule_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($reschedule_error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Appointment Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Reference Number:</strong></div>
                            <div class="col-sm-8"><code><?php echo htmlspecialchars($appointment['appointment_number']); ?></code></div>
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
                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to change the appointment status? This will notify the patient by email.');">
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Status:</strong></div>
                                <div class="col-sm-8">
                                    <?php 
                                    $statusOptions = getAvailableStatusOptions($appointment['status'], $appointment['appointment_date']);
                                    $isLocked = in_array($appointment['status'], ['completed', 'cancelled', 'no_show']);
                                    ?>
                                    <?php if ($isLocked): ?>
                                        <span class="badge bg-<?php echo getStatusBadge($appointment['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                        </span>
                                        <small class="text-muted d-block mt-1">Status is locked and cannot be changed.</small>
                                    <?php else: ?>
                                        <select name="status" class="form-select form-select-sm d-inline-block" style="width: auto;">
                                            <?php foreach ($statusOptions as $value => $label): ?>
                                                <option value="<?php echo $value; ?>" <?php echo $appointment['status'] === $value ? 'selected' : ''; ?>>
                                                    <?php echo $label; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!$isLocked): ?>
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Notes:</strong></div>
                                <div class="col-sm-8">
                                    <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($appointment['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4"></div>
                                <div class="col-sm-8">
                                    <input type="hidden" name="update_status" value="1">
                                    <button type="submit" class="btn btn-primary">Update Status</button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </form>
                        <?php 
                        $is_reschedulable = in_array($appointment['status'], ['pending', 'confirmed']);
                        if ($is_reschedulable): 
                        ?>
                        <hr class="my-3">
                        <h6 class="mb-2">Reschedule Appointment</h6>
                        <form method="POST" action="" id="rescheduleForm" class="row g-2 align-items-end">
                            <input type="hidden" name="reschedule" value="1">
                            <div class="col-auto">
                                <label class="form-label small mb-0">New Date</label>
                                <input type="date" class="form-control form-control-sm" name="reschedule_date" id="reschedule_date" required min="<?php echo date('Y-m-d'); ?>">
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
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Dentist:</strong></div>
                            <div class="col-sm-8">
                                <?php 
                                if ($appointment['dentist_name']) {
                                    echo htmlspecialchars($appointment['dentist_name']);
                                } else {
                                    echo '<span class="text-muted">Not assigned</span>';
                                }
                                ?>
                            </div>
                        </div>
                        <?php if ($appointment['reason_for_visit']): ?>
                        <div class="row">
                            <div class="col-sm-4"><strong>Reason for Visit:</strong></div>
                            <div class="col-sm-8"><?php echo nl2br(htmlspecialchars($appointment['reason_for_visit'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Patient Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong><br><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></p>
                        <p><strong>Patient #:</strong><br><code><?php echo htmlspecialchars($appointment['patient_number']); ?></code></p>
                        <p><strong>Phone:</strong><br><?php echo htmlspecialchars(maskPhone($appointment['phone'] ?? '')); ?></p>
                        <?php if (!empty($appointment['email'])): ?>
                        <p><strong>Email:</strong><br><?php echo htmlspecialchars(maskEmail($appointment['email'])); ?></p>
                        <?php endif; ?>
                        <a href="view-patient.php?id=<?php echo $appointment['patient_id']; ?>" class="btn btn-sm btn-primary w-100 mt-3">View Full Profile</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var rescheduleDate = document.getElementById('reschedule_date');
            var rescheduleTime = document.getElementById('reschedule_time');
            if (!rescheduleDate || !rescheduleTime) return;
            var appointmentId = <?php echo (int)$appointment_id; ?>;
            var dentistId = <?php echo !empty($appointment['dentist_id']) ? (int)$appointment['dentist_id'] : 'null'; ?>;
            var branchId = <?php echo (int)($appointment['branch_id'] ?? 1); ?>;
            rescheduleDate.addEventListener('change', function() {
                var date = this.value;
                rescheduleTime.innerHTML = '<option value="">Loading...</option>';
                if (!date) { rescheduleTime.innerHTML = '<option value="">Select time...</option>'; return; }
                var url = '../api/get-available-slots.php?date=' + encodeURIComponent(date) + '&branch_id=' + branchId + '&exclude_appointment_id=' + appointmentId;
                if (dentistId) url += '&dentist_id=' + dentistId;
                fetch(url).then(function(r) { return r.json(); }).then(function(data) {
                    rescheduleTime.innerHTML = '<option value="">Select time...</option>';
                    if (data.success && data.slots) {
                        data.slots.forEach(function(s) {
                            var opt = document.createElement('option');
                            opt.value = s.slot_time;
                            opt.textContent = s.display_time;
                            rescheduleTime.appendChild(opt);
                        });
                    }
                }).catch(function() {
                    rescheduleTime.innerHTML = '<option value="">Error loading slots</option>';
                });
            });
        });
    </script>
</body>
</html>

