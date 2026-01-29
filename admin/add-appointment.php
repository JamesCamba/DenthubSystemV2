<?php
/**
 * Denthub Dental Clinic - Add New Appointment
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$db = getDB();
$error = '';
$success = '';

// Get services and dentists
$services = getServices();
$dentists = getDentists();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = intval($_POST['patient_id']);
    $service_id = intval($_POST['service_id']);
    $dentist_id = !empty($_POST['dentist_id']) ? intval($_POST['dentist_id']) : null;
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $reason_for_visit = sanitize($_POST['reason_for_visit'] ?? '');
    $branch_id = $_SESSION['branch_id'] ?? 1;

    if (empty($patient_id) || empty($service_id) || empty($appointment_date) || empty($appointment_time)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if slot is available
        if ($dentist_id === null) {
            // Any dentist – avoid binding a NULL dentist_id parameter
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments 
                                 WHERE appointment_date = ? AND appointment_time = ? 
                                 AND status IN ('pending', 'confirmed')");
            $stmt->bind_param("ss", $appointment_date, $appointment_time);
        } else {
            // Specific dentist
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments 
                                 WHERE appointment_date = ? AND appointment_time = ? 
                                 AND status IN ('pending', 'confirmed') 
                                 AND dentist_id = ?");
            $stmt->bind_param("ssi", $appointment_date, $appointment_time, $dentist_id);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['count'] > 0) {
            $error = 'Selected time slot is already booked.';
        } else {
            $appointment_number = generateAppointmentNumber();
            $stmt = $db->prepare("INSERT INTO appointments (appointment_number, patient_id, dentist_id, service_id, branch_id, appointment_date, appointment_time, reason_for_visit, status, created_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?)");
            $stmt->bind_param("siiissssi", $appointment_number, $patient_id, $dentist_id, $service_id, $branch_id, $appointment_date, $appointment_time, $reason_for_visit, $_SESSION['user_id']);

            if ($stmt->execute()) {
                $success = 'Appointment created successfully!';
                header('Location: appointments.php');
                exit;
            } else {
                $error = 'Error creating appointment.';
            }
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid py-4">
        <h2 class="mb-4">Add New Appointment</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
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
                            <select class="form-select" name="service_id" required>
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
                            <label class="form-label">Dentist (Optional)</label>
                            <select class="form-select" name="dentist_id">
                                <option value="">Any Available Dentist</option>
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
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="appointment_time" required>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function searchPatient() {
            const search = document.getElementById('patient_search').value;
            if (search.length < 2) {
                alert('Please enter at least 2 characters');
                return;
            }
            window.location.href = '?patient_search=' + encodeURIComponent(search);
        }

        <?php if ($patients_list && $patients_list->num_rows > 0): ?>
            const resultsDiv = document.getElementById('patient_results');
            resultsDiv.innerHTML = '<div class="list-group"><?php 
                while ($p = $patients_list->fetch_assoc()): 
                    echo '<button type="button" class="list-group-item list-group-item-action" onclick="selectPatient(' . $p['patient_id'] . ', \'' . htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) . '\')">';
                    echo htmlspecialchars($p['patient_number'] . ' - ' . $p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['phone'] . ')');
                    echo '</button>';
                endwhile;
            ?></div>';
        <?php endif; ?>

        function selectPatient(id, name) {
            document.getElementById('patient_id').value = id;
            document.getElementById('patient_results').innerHTML = '<div class="alert alert-success">Selected: ' + name + '</div>';
        }
    </script>
</body>
</html>

