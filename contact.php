<?php
/**
 * Denthub Dental Clinic - Contact Page
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - <?php echo APP_NAME; ?></title>
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
                        <a class="nav-link" href="book-appointment.php">Book Appointment</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="contact.php">Contact</a>
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

    <section class="py-5">
        <div class="container">
            <h1 class="text-center mb-5">Contact Us</h1>
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-4"><i class="bi bi-geo-alt text-primary"></i> Visit Us</h5>
                            <p class="card-text">
                                <strong><?php echo APP_NAME; ?></strong><br>
                                Block 5, Lot 3 & 4, Sabalo Street<br>
                                Sangandaan, Caloocan City<br>
                                Philippines 1400
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-4"><i class="bi bi-telephone text-primary"></i> Call Us</h5>
                            <p class="card-text">
                                <strong>Phone:</strong><br>
                                <a href="tel:09166070999">0916 607 0999</a>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-4"><i class="bi bi-envelope text-primary"></i> Email Us</h5>
                            <p class="card-text">
                                <strong>Email:</strong><br>
                                <a href="mailto:denthubcenter.sdc1@gmail.com">denthubcenter.sdc1@gmail.com</a>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-4"><i class="bi bi-clock text-primary"></i> Business Hours</h5>
                            <p class="card-text">
                                <strong>Monday - Saturday:</strong><br>
                                9:00 AM - 5:00 PM<br><br>
                                <strong>Sunday:</strong><br>
                                Closed
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><?php echo APP_NAME; ?></h5>
                    <p>Quality dental care for you and your family.</p>
                </div>
                <div class="col-md-4">
                    <h5>Contact Us</h5>
                    <p><i class="bi bi-geo-alt"></i> Block 5, Lot 3 & 4, Sabalo Street, Sangandaan, Caloocan City</p>
                    <p><i class="bi bi-telephone"></i> 0916 607 0999</p>
                    <p><i class="bi bi-envelope"></i> denthubcenter.sdc1@gmail.com</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="services.php" class="text-white-50">Services</a></li>
                        <li><a href="book-appointment.php" class="text-white-50">Book Appointment</a></li>
                        <li><a href="contact.php" class="text-white-50">Contact</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-3">
            <p class="text-center mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

