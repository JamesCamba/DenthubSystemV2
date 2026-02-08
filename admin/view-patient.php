<?php
/**
 * Denthub Dental Clinic - View Patient Details
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$patient_id = intval($_GET['id'] ?? 0);
$db = getDB();

// Get patient info
$stmt = $db->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    header('Location: patients.php');
    exit;
}

// Get patient appointments
$stmt = $db->prepare("SELECT a.*, s.service_name, u.full_name as dentist_name
                      FROM appointments a
                      JOIN services s ON a.service_id = s.service_id
                      LEFT JOIN dentists d ON a.dentist_id = d.dentist_id
                      LEFT JOIN users u ON d.user_id = u.user_id
                      WHERE a.patient_id = ?
                      ORDER BY a.appointment_date DESC, a.appointment_time DESC
                      LIMIT 20");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$appointments = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patient - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="denthub-main">
    <div class="container-fluid py-4">
        <h1 class="denthub-page-title">Patient Profile</h1>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <a href="add-appointment.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> New Appointment
                </a>
                <a href="patients.php" class="btn btn-secondary">Back to Patients</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4 denthub-card-rounded shadow-sm">
                    <div class="view-patient-tab-primary">
                        <h5 class="mb-0">Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Patient Number:</strong><br><code><?php echo htmlspecialchars($patient['patient_number']); ?></code></p>
                        <p><strong>Name:</strong><br><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                        <?php if ($patient['middle_name']): ?>
                        <p><strong>Middle Name:</strong><br><?php echo htmlspecialchars($patient['middle_name']); ?></p>
                        <?php endif; ?>
                        <p><strong>Email:</strong><br><?php echo htmlspecialchars(maskEmail($patient['email'])); ?></p>
                        <p><strong>Phone:</strong><br><?php echo htmlspecialchars(maskPhone($patient['phone'])); ?></p>
                        <?php if ($patient['birthdate']): ?>
                        <p><strong>Birthdate:</strong><br><?php echo formatDate($patient['birthdate']); ?></p>
                        <?php endif; ?>
                        <?php if ($patient['gender']): ?>
                        <p><strong>Gender:</strong><br><?php echo htmlspecialchars($patient['gender']); ?></p>
                        <?php endif; ?>
                        <?php if ($patient['address']): ?>
                        <p><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($patient['address'])); ?></p>
                        <?php endif; ?>
                        <?php if ($patient['medical_history']): ?>
                        <p><strong>Medical History:</strong><br><?php echo nl2br(htmlspecialchars($patient['medical_history'])); ?></p>
                        <?php endif; ?>
                        <?php if ($patient['allergies']): ?>
                        <p><strong>Allergies:</strong><br><?php echo nl2br(htmlspecialchars($patient['allergies'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card denthub-card-rounded shadow-sm">
                    <div class="view-patient-tab-green">
                        <h5 class="mb-0">Appointment History</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($appointments->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Reference #</th>
                                            <th>Service</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Dentist</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($apt = $appointments->fetch_assoc()): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($apt['appointment_number']); ?></code></td>
                                                <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                                                <td><?php echo formatDate($apt['appointment_date']); ?></td>
                                                <td><?php echo formatTime($apt['appointment_time']); ?></td>
                                                <td><?php echo $apt['dentist_name'] ? htmlspecialchars($apt['dentist_name']) : 'TBA'; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadge($apt['status']); ?>">
                                                        <?php echo ucfirst($apt['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="view-appointment.php?id=<?php echo $apt['appointment_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No appointment history.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

