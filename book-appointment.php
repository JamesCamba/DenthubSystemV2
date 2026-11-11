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
        $service_id = intval($_POST['service_id'] ?? 0);
        $dentist_id = !empty($_POST['dentist_id']) ? intval($_POST['dentist_id']) : 0;
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $reason_for_visit = sanitize($_POST['reason_for_visit'] ?? '');
        $branch_id = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;

        if (!verifyCsrf()) {
            $error = 'Invalid request. Please refresh the page and try again.';
        } elseif (empty($service_id) || empty($appointment_date) || empty($appointment_time) || empty($dentist_id) || empty($branch_id)) {
            $error = 'Please fill in all required fields.';
        } elseif (hasUpcomingAppointment($patient_id)) {
            $error = 'You already have an upcoming appointment. Please complete or cancel it before booking a new one.';
        } elseif (!canBookOnline($patient_id)) {
            $error = 'You have multiple recent no-shows. Please call the clinic to book a new appointment.';
        } else {
            $created = createAppointmentRecord(
                $patient_id,
                $dentist_id,
                $service_id,
                $branch_id,
                $appointment_date,
                $appointment_time,
                $reason_for_visit,
                'pending',
                null,
                ['allow_today' => false]
            );
            if (!empty($created['ok'])) {
                header('Location: appointment-confirmation.php?id=' . $created['appointment_id']);
                exit;
            }
            $error = $created['error'] ?? 'We could not complete your booking. Please try again or contact the clinic.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - <?php echo APP_NAME; ?></title>
    <?php $asset_base = ''; require __DIR__ . '/includes/head-assets.php'; ?>
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
                            <div class="alert alert-danger"><?php echo e($error); ?></div>
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
                                <?php echo csrfField(); ?>
                                <div class="mb-3">
                                    <label class="form-label">Branch <span class="text-danger">*</span></label>
                                    <select class="form-select" name="branch_id" id="branch_id" required>
                                        <option value="">Select a branch...</option>
                                        <?php foreach ($branches as $b): ?>
                                            <option value="<?php echo (int)$b['branch_id']; ?>" <?php echo ((int)$prefill_branch_id === (int)$b['branch_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($b['branch_name']); ?><?php if (!empty($b['address'])): ?> — <?php echo htmlspecialchars($b['address']); ?><?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="text-danger small mt-1 d-none" id="branchError"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Preferred Date <span class="text-danger">*</span></label>
                                    <input type="hidden" name="appointment_date" id="appointment_date" required value="<?php echo htmlspecialchars($prefill_date); ?>">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="prevMonthBtn"><i class="bi bi-chevron-left"></i></button>
                                        <strong id="monthLabel">Select a branch</strong>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="nextMonthBtn"><i class="bi bi-chevron-right"></i></button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0 text-center align-middle booking-mini-cal">
                                            <thead>
                                                <tr>
                                                    <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bookingCalendarBody"></tbody>
                                        </table>
                                    </div>
                                    <small class="text-muted d-block mt-2">Past, closed, blocked, and full days are greyed out. Booking is limited to the next 3 months, starting tomorrow.</small>
                                    <div class="text-danger small mt-1 d-none" id="dateError"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Service Type <span class="text-danger">*</span></label>
                                    <select class="form-select" name="service_id" id="service_id" required disabled>
                                        <option value="">Select date and branch first</option>
                                    </select>
                                    <div class="text-danger small mt-1 d-none" id="serviceError"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Dentist <span class="text-danger">*</span></label>
                                    <select class="form-select" name="dentist_id" id="dentist_id" required disabled>
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

        let calYear = (window.Denthub && Denthub.todayParts) ? Denthub.todayParts().y : new Date().getFullYear();
        let calMonth = (window.Denthub && Denthub.todayParts) ? Denthub.todayParts().m : (new Date().getMonth() + 1);
        let calMinDate = '';
        let calMaxDate = '';

        function resetBranch() {
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

        function renderBookingCalendar(data) {
            const body = document.getElementById('bookingCalendarBody');
            body.innerHTML = '';
            const year = data.year;
            const month = data.month;
            calYear = year;
            calMonth = month;
            const firstDay = new Date(year, month - 1, 1).getDay();
            const daysInMonth = new Date(year, month, 0).getDate();
            const dayMap = {};
            (data.days || []).forEach(d => { dayMap[d.date] = d; });
            document.getElementById('monthLabel').textContent = new Date(year, month - 1, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' });
            const selected = dateInput.value;
            let day = 1;
            for (let week = 0; week < 6; week++) {
                const tr = document.createElement('tr');
                for (let dow = 0; dow < 7; dow++) {
                    const td = document.createElement('td');
                    if (week === 0 && dow < firstDay || day > daysInMonth) {
                        td.innerHTML = '&nbsp;';
                    } else {
                        const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
                        const info = dayMap[dateStr] || { slots: 0, blocked: true, selectable: false, past: true };
                        td.textContent = String(day);
                        td.dataset.date = dateStr;
                        td.className = 'booking-cal-day';
                        if (info.past || info.outside_window) {
                            td.classList.add('is-disabled');
                            td.title = info.past ? 'Past date' : 'Outside 3-month window';
                        } else if (info.blocked) {
                            td.classList.add('is-disabled', 'is-blocked');
                            td.title = info.reason || 'Blocked';
                        } else if (!info.selectable) {
                            td.classList.add('is-disabled');
                            td.title = 'No available slots';
                        } else {
                            td.classList.add('is-open');
                            td.title = info.slots + ' slots';
                            td.addEventListener('click', () => selectBookingDate(dateStr));
                        }
                        if (selected === dateStr) td.classList.add('is-selected');
                        day++;
                    }
                    tr.appendChild(td);
                }
                body.appendChild(tr);
                if (day > daysInMonth) break;
            }
        }

        async function loadBookingCalendar() {
            const branchId = branchSelect.value;
            const body = document.getElementById('bookingCalendarBody');
            if (!branchId) {
                document.getElementById('monthLabel').textContent = 'Select a branch';
                body.innerHTML = '';
                return;
            }
            try {
                const res = await fetch(`api/get-calendar-availability.php?role=patient&branch_id=${encodeURIComponent(branchId)}&year=${calYear}&month=${calMonth}`);
                const data = await res.json();
                if (!data.success) {
                    showError(dateError, data.message || 'Unable to load calendar.');
                    return;
                }
                calMinDate = data.min_date || calMinDate;
                calMaxDate = data.max_date || calMaxDate;
                renderBookingCalendar(data);
            } catch (e) {
                showError(dateError, 'Error loading calendar.');
            }
        }

        function selectBookingDate(dateStr) {
            dateInput.value = dateStr;
            document.querySelectorAll('.booking-cal-day').forEach(el => el.classList.toggle('is-selected', el.dataset.date === dateStr));
            showError(dateError, '');
            handleDateChange();
        }

        async function handleDateChange() {
            const date = dateInput.value;
            resetService();
            resetDentist();
            resetTime();
            updateUrlParams();
            if (!date || !branchSelect.value) return;
            await loadServicesForDate();
        }

        async function handleBranchChange() {
            dateInput.value = '';
            resetService();
            resetDentist();
            resetTime();
            showError(branchError, '');
            showError(dateError, '');
            updateUrlParams();
            const today = window.Denthub ? Denthub.todayParts() : { y: new Date().getFullYear(), m: new Date().getMonth() + 1 };
            calYear = today.y;
            calMonth = today.m;
            await loadBookingCalendar();
        }

        async function loadServicesForDate() {
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
                const serviceId = serviceSelect.value;
                const res = await fetch(`api/get-available-slots.php?date=${encodeURIComponent(date)}&dentist_id=${encodeURIComponent(dentistId)}&branch_id=${encodeURIComponent(branchId)}&service_id=${encodeURIComponent(serviceId)}`);
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
        branchSelect.addEventListener('change', handleBranchChange);
        document.getElementById('prevMonthBtn').addEventListener('click', () => {
            const next = window.Denthub ? Denthub.addMonths(calYear, calMonth, -1) : { y: calYear, m: calMonth - 1 };
            if (calMinDate && window.Denthub && !Denthub.monthInRange(next.y, next.m, calMinDate, calMaxDate)) return;
            calYear = next.y;
            calMonth = next.m;
            loadBookingCalendar();
        });
        document.getElementById('nextMonthBtn').addEventListener('click', () => {
            const next = window.Denthub ? Denthub.addMonths(calYear, calMonth, 1) : { y: calYear, m: calMonth + 1 };
            if (calMaxDate && window.Denthub && !Denthub.monthInRange(next.y, next.m, calMinDate, calMaxDate)) return;
            calYear = next.y;
            calMonth = next.m;
            loadBookingCalendar();
        });
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
            const params = new URLSearchParams(window.location.search);
            const preBranch = params.get('branch_id');
            const preService = params.get('service');
            const preDentist = params.get('dentist_id');
            const preDate = params.get('date') || dateInput.value;
            if (preBranch) branchSelect.value = preBranch;
            if (branchSelect.value) {
                handleBranchChange().then(() => {
                    if (preDate) {
                        dateInput.value = preDate;
                        handleDateChange().then(() => {
                            if (preService) {
                                serviceSelect.value = preService;
                                if (!serviceSelect.disabled) {
                                    handleServiceChange().then(() => {
                                        if (preDentist) {
                                            dentistSelect.value = preDentist;
                                            if (!dentistSelect.disabled) handleDentistChange();
                                        }
                                    });
                                }
                            }
                        });
                    }
                });
            }
        });
    </script>
</body>
</html>

