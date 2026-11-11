<?php
/**
 * Denthub Dental Clinic - Add New Appointment
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireStaff();

$db = getDB();
$error = '';
$success = '';

// Get services and dentists
$services = getServices();
$dentists = getDentists();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } else {
    $patient_id = intval($_POST['patient_id']);
    $service_id = intval($_POST['service_id']);
    $dentist_id = !empty($_POST['dentist_id']) ? intval($_POST['dentist_id']) : 0;
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $reason_for_visit = sanitize($_POST['reason_for_visit'] ?? '');
    $branch_id = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : (int)($_SESSION['branch_id'] ?? 0);

    if (empty($patient_id) || empty($service_id) || empty($appointment_date) || empty($appointment_time) || empty($dentist_id) || $branch_id <= 0) {
        $error = 'Please fill in all required fields, including dentist and branch.';
    } else {
        $created = createAppointmentRecord(
            $patient_id,
            $dentist_id,
            $service_id,
            $branch_id,
            $appointment_date,
            $appointment_time,
            $reason_for_visit,
            'confirmed',
            $_SESSION['user_id'] ?? null,
            ['allow_today' => true]
        );
        if (!empty($created['ok'])) {
            header('Location: appointments.php');
            exit;
        }
        $error = $created['error'] ?? 'We could not create the appointment. Please try again.';
    }
    }
}

// Search patients
$patient_search = $_GET['patient_search'] ?? '';
$patients_list = null;
if ($patient_search) {
    $stmt = $db->prepare("SELECT * FROM patients WHERE first_name LIKE ? OR last_name LIKE ? OR patient_number LIKE ? OR phone LIKE ? LIMIT 20");
    $search_param = "%$patient_search%";
    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $patients_list = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Appointment - <?php echo APP_NAME; ?></title>
    <?php $asset_base = '../'; require __DIR__ . '/../includes/head-assets.php'; ?>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="denthub-main">
    <div class="container-fluid py-4">
        <h2 class="mb-4">Add New Appointment</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <!-- Patient Search -->
                    <div class="mb-4">
                        <label class="form-label">Search Patient <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="patient_search" placeholder="Search by name, patient number, or phone..." value="<?php echo htmlspecialchars($patient_search); ?>">
                            <button type="button" class="btn btn-primary" onclick="searchPatient()">Search</button>
                        </div>
                        <input type="hidden" name="patient_id" id="patient_id" required>
                        <div id="patient_results" class="mt-2"></div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Service <span class="text-danger">*</span></label>
                            <select class="form-select" name="service_id" id="service_id" required>
                                <option value="">Select service...</option>
                                <?php 
                                $services->data_seek(0);
                                while ($service = $services->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $service['service_id']; ?>">
                                        <?php echo htmlspecialchars($service['service_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Dentist <span class="text-danger">*</span></label>
                            <select class="form-select" name="dentist_id" id="dentist_id" required>
                                <option value="">Select dentist...</option>
                                <?php 
                                $dentists->data_seek(0);
                                while ($dentist = $dentists->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $dentist['dentist_id']; ?>">
                                        <?php echo htmlspecialchars($dentist['full_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Branch <span class="text-danger">*</span></label>
                            <select class="form-select" name="branch_id" id="branch_id" required>
                                <?php
                                $brRes = $db->query("SELECT branch_id, branch_name FROM branches WHERE is_active = TRUE ORDER BY branch_name");
                                $sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
                                while ($br = $brRes->fetch_assoc()):
                                ?>
                                    <option value="<?php echo (int)$br['branch_id']; ?>" <?php echo $sessionBranch === (int)$br['branch_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($br['branch_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <?php
                                [$minStaff, $maxStaff] = getBookingDateBounds(true);
                            ?>
                            <input type="date" class="form-control" name="appointment_date" id="appointment_date" required
                                   min="<?php echo $minStaff->format('Y-m-d'); ?>"
                                   max="<?php echo $maxStaff->format('Y-m-d'); ?>">
                            <small class="text-muted">Today through 3 months ahead. Past dates are not allowed.</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Time <span class="text-danger">*</span></label>
                            <select class="form-select" name="appointment_time" id="appointment_time" required>
                                <option value="">Select date, dentist and service first</option>
                            </select>
                            <small class="text-muted">Only open clinic slots that do not overlap are listed.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason for Visit (Optional)</label>
                        <textarea class="form-control" name="reason_for_visit" rows="3"></textarea>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="appointments.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Appointment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </main>

    <script>
        function searchPatient() {
            const search = document.getElementById('patient_search').value;
            if (search.length < 2) {
                alert('Please enter at least 2 characters');
                return;
            }
            window.location.href = '?patient_search=' + encodeURIComponent(search);
        }

        const patientMatches = <?php
            $patientMatches = [];
            if ($patients_list) {
                $patients_list->data_seek(0);
                while ($p = $patients_list->fetch_assoc()) {
                    $patientMatches[] = [
                        'id' => (int)$p['patient_id'],
                        'name' => trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')),
                        'label' => ($p['patient_number'] ?? '') . ' - ' . trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) . ' (' . maskPhone($p['phone'] ?? '') . ')',
                    ];
                }
            }
            echo json_encode($patientMatches, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>;
        if (patientMatches.length) {
            const wrap = document.createElement('div');
            wrap.className = 'list-group';
            patientMatches.forEach(function (p) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action';
                btn.textContent = p.label;
                btn.addEventListener('click', function () { selectPatient(p.id, p.name); });
                wrap.appendChild(btn);
            });
            document.getElementById('patient_results').replaceChildren(wrap);
        }

        function selectPatient(id, name) {
            document.getElementById('patient_id').value = id;
            const note = document.createElement('div');
            note.className = 'alert alert-success';
            note.textContent = 'Selected: ' + name;
            document.getElementById('patient_results').replaceChildren(note);
        }

        async function loadStaffSlots() {
            const date = document.getElementById('appointment_date').value;
            const branchId = document.getElementById('branch_id').value;
            const dentistId = document.getElementById('dentist_id').value;
            const serviceId = document.getElementById('service_id').value;
            const timeSelect = document.getElementById('appointment_time');
            if (!date || !branchId || !dentistId || !serviceId) {
                timeSelect.innerHTML = '<option value="">Select date, dentist and service first</option>';
                return;
            }
            timeSelect.innerHTML = '<option value="">Loading...</option>';
            try {
                const res = await fetch(`../api/get-available-slots.php?date=${encodeURIComponent(date)}&dentist_id=${encodeURIComponent(dentistId)}&branch_id=${encodeURIComponent(branchId)}&service_id=${encodeURIComponent(serviceId)}`);
                const data = await res.json();
                timeSelect.innerHTML = '<option value="">Select time...</option>';
                if (data.success && data.slots && data.slots.length) {
                    data.slots.forEach(slot => {
                        const opt = document.createElement('option');
                        opt.value = slot.slot_time;
                        opt.textContent = slot.display_time;
                        timeSelect.appendChild(opt);
                    });
                } else {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = data.message || 'No available slots';
                    opt.disabled = true;
                    timeSelect.appendChild(opt);
                }
            } catch (e) {
                timeSelect.innerHTML = '<option value="">Error loading slots</option>';
            }
        }
        ['appointment_date', 'branch_id', 'dentist_id', 'service_id'].forEach(id => {
            document.getElementById(id).addEventListener('change', loadStaffSlots);
        });
    </script>
</body>
</html>

