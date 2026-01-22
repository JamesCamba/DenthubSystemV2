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

// Get services
$services = getServices();
$dentists = null; // Will be loaded based on selected service

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
        $branch_id = 1; // Default branch

        // Validation
        if (empty($service_id) || empty($appointment_date) || empty($appointment_time)) {
            $error = 'Please fill in all required fields.';
        } elseif (isDateBlocked($appointment_date, $branch_id)) {
            $error = 'Selected date is not available.';
        } else {
            // Check if slot is available
            $db = getDB();
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments 
                                 WHERE appointment_date = ? AND appointment_time = ? 
                                 AND status IN ('pending', 'confirmed') 
                                 AND (dentist_id = ? OR ? IS NULL)");
            $stmt->bind_param("ssii", $appointment_date, $appointment_time, $dentist_id, $dentist_id);
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

                if ($stmt->execute()) {
                    $appointment_id = $db->insert_id;
                    header('Location: appointment-confirmation.php?id=' . $appointment_id);
                    exit;
                } else {
                    $error = 'Error booking appointment. Please try again.';
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
    <title>Book Appointment - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="index.php">
                <img src="resources/468397831_122123862374527362_8536709169507539928_n.jpg" alt="<?php echo APP_NAME; ?>" height="40" class="me-2">
                <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="services.php">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="book-appointment.php">Book Appointment</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                    <?php if (isPatientLoggedIn()): ?>
                        <?php 
                        $patient = getCurrentPatient();
                        $patient_name = $patient ? htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']) : htmlspecialchars($_SESSION['patient_name'] ?? 'User');
                        ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo $patient_name; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="patient/dashboard.php">Dashboard</a></li>
                                <li><a class="dropdown-item" href="patient/profile.php">Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary ms-2" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
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
                            <form method="POST" action="" id="appointmentForm">
                                <div class="mb-3">
                                    <label class="form-label">Service Type <span class="text-danger">*</span></label>
                                    <select class="form-select" name="service_id" id="service_id" required>
                                        <option value="">Select a service...</option>
                                        <?php 
                                        $services->data_seek(0);
                                        while ($service = $services->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $service['service_id']; ?>" 
                                                <?php echo ($selected_service == $service['service_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($service['service_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Dentist (Optional)</label>
                                    <select class="form-select" name="dentist_id" id="dentist_id">
                                        <option value="">Any Available Dentist</option>
                                    </select>
                                    <small class="text-muted">Select a service first to see available dentists</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Preferred Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="appointment_date" id="appointment_date" 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                    <small class="text-muted">Select a date (tomorrow onwards)</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Preferred Time <span class="text-danger">*</span></label>
                                    <select class="form-select" name="appointment_time" id="appointment_time" required>
                                        <option value="">Select time...</option>
                                    </select>
                                    <small class="text-muted">Available time slots will load after selecting a date</small>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load dentists based on selected service
        document.getElementById('service_id').addEventListener('change', function() {
            const serviceId = this.value;
            const dentistSelect = document.getElementById('dentist_id');
            
            if (!serviceId) {
                dentistSelect.innerHTML = '<option value="">Any Available Dentist</option>';
                return;
            }
            
            // Fetch dentists for this service
            fetch(`api/get-dentists-by-service.php?service_id=${serviceId}`)
                .then(response => response.json())
                .then(data => {
                    dentistSelect.innerHTML = '<option value="">Any Available Dentist</option>';
                    if (data.success && data.dentists.length > 0) {
                        data.dentists.forEach(dentist => {
                            const option = document.createElement('option');
                            option.value = dentist.dentist_id;
                            option.textContent = dentist.full_name;
                            dentistSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        });
        
        // Load available time slots when date is selected
        document.getElementById('appointment_date').addEventListener('change', function() {
            const date = this.value;
            const dentistId = document.getElementById('dentist_id').value;
            const timeSelect = document.getElementById('appointment_time');
            
            if (!date) {
                timeSelect.innerHTML = '<option value="">Select time...</option>';
                return;
            }

            // Fetch available slots
            fetch(`api/get-available-slots.php?date=${date}&dentist_id=${dentistId}`)
                .then(response => response.json())
                .then(data => {
                    timeSelect.innerHTML = '<option value="">Select time...</option>';
                    if (data.success && data.slots.length > 0) {
                        data.slots.forEach(slot => {
                            const option = document.createElement('option');
                            option.value = slot.slot_time;
                            option.textContent = slot.display_time;
                            timeSelect.appendChild(option);
                        });
                    } else {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'No available slots for this date';
                        timeSelect.appendChild(option);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    timeSelect.innerHTML = '<option value="">Error loading slots</option>';
                });
        });

        // Reload slots when dentist changes
        document.getElementById('dentist_id').addEventListener('change', function() {
            const date = document.getElementById('appointment_date').value;
            if (date) {
                document.getElementById('appointment_date').dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>

