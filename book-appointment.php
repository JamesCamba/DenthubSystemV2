<?php
/**
 * Denthub Dental Clinic - Book Appointment
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$selected_service = $_GET['service'] ?? '';
$hasUpcomingAppointment = false;

// Get services and branches
$services = getServices();
$dentists = null;
$db = getDB();
$branchesResult = $db->query("SELECT branch_id, branch_name, address FROM branches WHERE is_active = TRUE ORDER BY branch_name");
$branches = [];
while ($row = $branchesResult->fetch_assoc()) {
    $branches[] = $row;
}

// Optional prefill from query string (used by calendar "Book" shortcuts)
$prefill_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
$prefill_date = isset($_GET['date']) ? $_GET['date'] : '';

// Pre-check for upcoming appointments (used for UX + client-side guard)
if (isPatientLoggedIn()) {
    $patient_id = $_SESSION['patient_id'];
    $hasUpcomingAppointment = hasUpcomingAppointment($patient_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is logged in
    if (!isPatientLoggedIn()) {
        $error = 'Please login first to book an appointment.';
    } else {
        $patient_id = $_SESSION['patient_id'];
        $service_id = intval($_POST['service_id']);
        $dentist_id = !empty($_POST['dentist_id']) ? intval($_POST['dentist_id']) : null;
        $appointment_date = $_POST['appointment_date'];
        $appointment_time = $_POST['appointment_time'];
        $reason_for_visit = sanitize($_POST['reason_for_visit'] ?? '');
        $branch_id = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : 1;

        // Validation
        if (empty($service_id) || empty($appointment_date) || empty($appointment_time)) {
            $error = 'Please fill in all required fields.';
        } else {
            // Enforce booking window: tomorrow up to 6 months ahead (exclude today and past)
            $today = new DateTimeImmutable('today');
            $minDate = $today->modify('+1 day');
            $maxDate = $today->modify('+6 months');
            $apptDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $appointment_date) ?: null;
            if (!$apptDateObj || $apptDateObj < $minDate || $apptDateObj > $maxDate) {
                $error = 'Selected date is outside the allowed booking window.';
            }
        }
        if (empty($error) && hasUpcomingAppointment($patient_id)) {
            $error = 'You already have an upcoming appointment. Please complete or cancel it before booking a new one.';
        } elseif (empty($error) && !canBookOnline($patient_id)) {
            $error = 'You have multiple recent no-shows. Please call the clinic to book a new appointment.';
        } elseif (empty($error) && isDateBlocked($appointment_date, $branch_id)) {
            $error = 'Selected date is not available.';
        } elseif (empty($error) && !dentistIsAssignedToBranch($dentist_id, $branch_id)) {
            $error = 'That dentist is not assigned to the selected branch.';
        } elseif (empty($error)) {
            // Check if slot is available
            $db = getDB();

            if ($dentist_id === null) {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments 
                                     WHERE appointment_date = ? AND appointment_time = ? AND branch_id = ?
                                     AND status IN ('pending', 'confirmed')");
                $stmt->bind_param("ssi", $appointment_date, $appointment_time, $branch_id);
            } else {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments 
                                     WHERE appointment_date = ? AND appointment_time = ? AND branch_id = ?
                                     AND status IN ('pending', 'confirmed') 
                                     AND dentist_id = ?");
                $stmt->bind_param("ssii", $appointment_date, $appointment_time, $branch_id, $dentist_id);
            }

            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if ($result['count'] > 0) {
                $error = 'Selected time slot is already booked. Please choose another time.';
            } else {
                // Create appointment
                $appointment_number = generateAppointmentNumber();
                $stmt = $db->prepare("INSERT INTO appointments (appointment_number, patient_id, dentist_id, service_id, branch_id, appointment_date, appointment_time, reason_for_visit, status) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("siiissss", $appointment_number, $patient_id, $dentist_id, $service_id, $branch_id, $appointment_date, $appointment_time, $reason_for_visit);

                try {
                    if ($stmt->execute()) {
                        $appointment_id = $db->insert_id;
                        header('Location: appointment-confirmation.php?id=' . $appointment_id);
                        exit;
                    }
                } catch (Exception $e) {
                    error_log('Book appointment error: ' . $e->getMessage());
                }
                $error = 'We could not complete your booking. Please try again or contact the clinic.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php echo denthubFaviconTags(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php $nav_active = 'book'; require_once 'includes/nav-public.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow denthub-card-light">
                    <div class="card-body p-5">
                        <h2 class="mb-4">Book an Appointment</h2>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if (!isPatientLoggedIn()): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-info-circle"></i> Please <a href="login.php">login</a> or <a href="register.php">register</a> to book an appointment.
                            </div>
                        <?php else: ?>
                            <?php if ($hasUpcomingAppointment): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-exclamation-circle"></i>
                                    You already have an upcoming appointment. Please complete or cancel it before booking a new one.
                                </div>
                            <?php endif; ?>
                            <form method="POST" action="" id="appointmentForm">
                                <div class="mb-3">
                                    <label class="form-label">Preferred Date <span class="text-danger">*</span></label>
                                    <?php
                                        $today = new DateTimeImmutable('today');
                                        $minDate = $today->modify('+1 day');
                                        $maxDate = $today->modify('+6 months');
                                    ?>
                                    <input type="date" class="form-control" name="appointment_date" id="appointment_date" 
                                           min="<?php echo $minDate->format('Y-m-d'); ?>" 
                                           max="<?php echo $maxDate->format('Y-m-d'); ?>"
                                           required
                                           value="<?php echo htmlspecialchars($prefill_date); ?>">
                                    <small class="text-muted">Select a future date. Only dates with available slots will allow you to proceed.</small>
                                    <div class="text-danger small mt-1 d-none" id="dateError"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Branch <span class="text-danger">*</span></label>
                                    <select class="form-select" name="branch_id" id="branch_id" required disabled>
                                        <option value="">Select a date first</option>
                                        <?php foreach ($branches as $b): ?>
                                            <option value="<?php echo (int)$b['branch_id']; ?>">
                                                <?php echo htmlspecialchars($b['branch_name']); ?><?php if (!empty($b['address'])): ?> — <?php echo htmlspecialchars($b['address']); ?><?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="text-danger small mt-1 d-none" id="branchError"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Service Type <span class="text-danger">*</span></label>
                                    <select class="form-select" name="service_id" id="service_id" required disabled>
                                        <option value="">Select date and branch first</option>
                                    </select>
                                    <div class="text-danger small mt-1 d-none" id="serviceError"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Dentist <span class="text-muted">(Required once service selected)</span></label>
                                    <select class="form-select" name="dentist_id" id="dentist_id" disabled>
                                        <option value="">Select date, branch and service first</option>
                                    </select>
                                    <small class="text-muted">Only dentists with availability for the selected date, branch, and service are shown.</small>
                                    <div class="text-danger small mt-1 d-none" id="dentistError"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Preferred Time <span class="text-danger">*</span></label>
                                    <select class="form-select" name="appointment_time" id="appointment_time" required disabled>
                                        <option value="">Select dentist first</option>
                                    </select>
                                    <small class="text-muted">Only available time slots for your chosen dentist will appear here.</small>
                                    <div class="text-danger small mt-1 d-none" id="timeError"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Reason for Visit (Optional)</label>
                                    <textarea class="form-control" name="reason_for_visit" rows="3" 
                                              placeholder="Please describe your concern or reason for the appointment..."></textarea>
                                </div>

                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Your appointment will be confirmed by our staff. You will receive a confirmation via SMS or email.
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">Book Appointment</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="denthub-footer py-4 mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><?php echo APP_NAME; ?></h5>
                    <p>Quality dental care for you and your family.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="services.php">Services</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-3 opacity-25">
            <p class="text-center mb-0 small">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const HAS_UPCOMING_APPOINTMENT = <?php echo isPatientLoggedIn() ? ($hasUpcomingAppointment ? 'true' : 'false') : 'false'; ?>;

        const dateInput = document.getElementById('appointment_date');
        const branchSelect = document.getElementById('branch_id');
        const serviceSelect = document.getElementById('service_id');
        const dentistSelect = document.getElementById('dentist_id');
        const timeSelect = document.getElementById('appointment_time');

        const dateError = document.getElementById('dateError');
        const branchError = document.getElementById('branchError');
        const serviceError = document.getElementById('serviceError');
        const dentistError = document.getElementById('dentistError');
        const timeError = document.getElementById('timeError');

        function showError(el, msg) {
            if (!el) return;
            el.textContent = msg || '';
            el.classList.toggle('d-none', !msg);
        }

        function resetBranch() {
            branchSelect.value = '';
            branchSelect.disabled = true;
            branchSelect.innerHTML = '<option value="">Select a date first</option>';
            showError(branchError, '');
        }

        function resetService() {
            serviceSelect.value = '';
            serviceSelect.disabled = true;
            serviceSelect.innerHTML = '<option value="">Select date and branch first</option>';
            showError(serviceError, '');
        }

        function resetDentist() {
            dentistSelect.value = '';
            dentistSelect.disabled = true;
            dentistSelect.innerHTML = '<option value="">Select date, branch and service first</option>';
            showError(dentistError, '');
        }

        function resetTime() {
            timeSelect.value = '';
            timeSelect.disabled = true;
            timeSelect.innerHTML = '<option value="">Select dentist first</option>';
            showError(timeError, '');
        }

        function updateUrlParams() {
            const params = new URLSearchParams(window.location.search);
            const dateVal = dateInput.value;
            const branchVal = branchSelect.value;
            const serviceVal = serviceSelect.value;
            const dentistVal = dentistSelect.value;
            if (dateVal) params.set('date', dateVal); else params.delete('date');
            if (branchVal) params.set('branch_id', branchVal); else params.delete('branch_id');
            if (serviceVal) params.set('service', serviceVal); else params.delete('service');
            if (dentistVal) params.set('dentist_id', dentistVal); else params.delete('dentist_id');
            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.replaceState({}, '', newUrl);
        }

        // Step 1: Date selection
        async function handleDateChange() {
            const date = dateInput.value;
            showError(dateError, '');
            resetBranch();
            resetService();
            resetDentist();
            resetTime();
            updateUrlParams();

            if (!date) {
                return;
            }

            branchSelect.disabled = true;
            branchSelect.innerHTML = '<option value="">Checking availability...</option>';

            try {
                const res = await fetch(`api/get-available-branches.php?date=${encodeURIComponent(date)}`);
                const data = await res.json();
                if (!data.success) {
                    showError(dateError, data.message || 'Unable to check availability for this date.');
                    resetBranch();
                    return;
                }
                const branches = data.branches || [];
                if (!branches.length) {
                    showError(dateError, 'No branches have available slots on this date.');
                    resetBranch();
                    return;
                }

                branchSelect.innerHTML = '<option value="">Select a branch...</option>';
                branches.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.branch_id;
                    opt.textContent = b.branch_name + (b.address ? ' — ' + b.address : '');
                    if (!b.has_slots) {
                        opt.disabled = true;
                        opt.classList.add('text-muted');
                    }
                    branchSelect.appendChild(opt);
                });
                branchSelect.disabled = false;
            } catch (e) {
                console.error(e);
                showError(dateError, 'Error loading availability for this date.');
                resetBranch();
            }
        }

        // Step 2: Branch selection
        async function handleBranchChange() {
            resetService();
            resetDentist();
            resetTime();
            showError(branchError, '');
            updateUrlParams();

            const date = dateInput.value;
            const branchId = branchSelect.value;
            if (!date || !branchId) {
                return;
            }

            serviceSelect.disabled = true;
            serviceSelect.innerHTML = '<option value="">Loading services...</option>';

            try {
                const res = await fetch(`api/get-available-services.php?date=${encodeURIComponent(date)}&branch_id=${encodeURIComponent(branchId)}`);
                const data = await res.json();
                if (!data.success) {
                    showError(serviceError, data.message || 'Unable to load services for this branch and date.');
                    resetService();
                    return;
                }
                const servicesData = data.services || [];
                if (!servicesData.length) {
                    showError(serviceError, 'No services have availability for this date and branch.');
                    resetService();
                    return;
                }
                serviceSelect.innerHTML = '<option value="">Select a service...</option>';
                servicesData.forEach(svc => {
                    const opt = document.createElement('option');
                    opt.value = svc.service_id;
                    opt.textContent = svc.service_name;
                    if (!svc.has_slots) {
                        opt.disabled = true;
                        opt.classList.add('text-muted');
                    }
                    serviceSelect.appendChild(opt);
                });
                serviceSelect.disabled = false;
            } catch (e) {
                console.error(e);
                showError(serviceError, 'Error loading services.');
                resetService();
            }
        }

        // Step 3: Service selection
        async function handleServiceChange() {
            resetDentist();
            resetTime();
            showError(serviceError, '');
            updateUrlParams();

            const date = dateInput.value;
            const branchId = branchSelect.value;
            const serviceId = serviceSelect.value;
            if (!date || !branchId || !serviceId) {
                return;
            }

            dentistSelect.disabled = true;
            dentistSelect.innerHTML = '<option value="">Loading dentists...</option>';

            try {
                const res = await fetch(`api/get-dentists-by-service.php?branch_id=${encodeURIComponent(branchId)}&service_id=${encodeURIComponent(serviceId)}&date=${encodeURIComponent(date)}`);
                const data = await res.json();
                if (!data.success) {
                    showError(dentistError, data.message || 'Unable to load dentists.');
                    resetDentist();
                    return;
                }
                const dentists = data.dentists || [];
                if (!dentists.length) {
                    showError(dentistError, 'No dentists have availability for this date, branch, and service.');
                    resetDentist();
                    return;
                }
                dentistSelect.innerHTML = '<option value="">Select a dentist...</option>';
                dentists.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.dentist_id;
                    opt.textContent = d.full_name;
                    dentistSelect.appendChild(opt);
                });
                dentistSelect.disabled = false;
            } catch (e) {
                console.error(e);
                showError(dentistError, 'Error loading dentists.');
                resetDentist();
            }
        }

        // Step 4 & 5: Dentist and time selection
        async function handleDentistChange() {
            resetTime();
            showError(dentistError, '');
            updateUrlParams();

            const date = dateInput.value;
            const branchId = branchSelect.value;
            const dentistId = dentistSelect.value;
            if (!date || !branchId || !dentistId) {
                return;
            }
            await loadSlots(date, branchId, dentistId);
        }

        async function loadSlots(date, branchId, dentistId) {
            timeSelect.disabled = true;
            timeSelect.innerHTML = '<option value="">Loading slots...</option>';
            showError(timeError, '');

            try {
                const res = await fetch(`api/get-available-slots.php?date=${encodeURIComponent(date)}&dentist_id=${encodeURIComponent(dentistId)}&branch_id=${encodeURIComponent(branchId)}`);
                const data = await res.json();
                timeSelect.innerHTML = '<option value="">Select time...</option>';
                if (data.success && data.slots && data.slots.length > 0) {
                    data.slots.forEach(slot => {
                        const opt = document.createElement('option');
                        opt.value = slot.slot_time;
                        opt.textContent = slot.display_time;
                        timeSelect.appendChild(opt);
                    });
                    timeSelect.disabled = false;
                } else {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = data.message || 'No available slots for this combination.';
                    opt.disabled = true;
                    timeSelect.appendChild(opt);
                    timeSelect.disabled = true;
                    showError(timeError, 'No available time slots. Please adjust your selections.');
                }
            } catch (e) {
                console.error(e);
                timeSelect.innerHTML = '<option value="">Error loading slots</option>';
                timeSelect.disabled = true;
                showError(timeError, 'Error loading time slots.');
            }
        }

        // Event wiring and initial prefill handling
        dateInput.addEventListener('change', handleDateChange);
        branchSelect.addEventListener('change', handleBranchChange);
        serviceSelect.addEventListener('change', handleServiceChange);
        dentistSelect.addEventListener('change', handleDentistChange);

        document.addEventListener('DOMContentLoaded', () => {
            if (HAS_UPCOMING_APPOINTMENT) {
                const form = document.getElementById('appointmentForm');
                if (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        alert('You already have an upcoming appointment. Please complete or cancel it before booking a new one.');
                    });
                }
            }

            // Handle prefilled date/branch/service from URL (calendar / service links)
            if (dateInput.value) {
                handleDateChange().then(() => {
                    const params = new URLSearchParams(window.location.search);
                    const preBranch = params.get('branch_id');
                    const preService = params.get('service');
                    const preDentist = params.get('dentist_id');
                    if (preBranch) {
                        branchSelect.value = preBranch;
                        if (!branchSelect.disabled) {
                            handleBranchChange().then(() => {
                                if (preService) {
                                    serviceSelect.value = preService;
                                    if (!serviceSelect.disabled) {
                                        handleServiceChange().then(() => {
                                            if (preDentist) {
                                                dentistSelect.value = preDentist;
                                                if (!dentistSelect.disabled) {
                                                    handleDentistChange();
                                                }
                                            }
                                        });
                                    }
                                }
                            });
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>

