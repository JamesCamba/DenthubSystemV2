<?php
/**
 * Denthub Dental Clinic - Landing Page
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - <?php echo APP_NAME; ?></title>
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
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="services.php">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="book-appointment.php">Book Appointment</a>
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

    <!-- Hero Section -->
    <section class="hero-section bg-primary text-white py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Your Smile is Our Priority</h1>
                    <p class="lead mb-4">Experience quality dental care with our professional team. Book your appointment online today!</p>
                    <a href="book-appointment.php" class="btn btn-light btn-lg">
                        <i class="bi bi-calendar-check"></i> Book an Appointment
                    </a>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="bi bi-heart-pulse-fill" style="font-size: 200px; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Preview -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Our Services</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-heart-pulse text-primary" style="font-size: 48px;"></i>
                            <h5 class="card-title mt-3">Tooth Restoration</h5>
                            <p class="card-text">Restore your damaged or decayed teeth with our professional restoration services.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-scissors text-primary" style="font-size: 48px;"></i>
                            <h5 class="card-title mt-3">Wisdom Tooth Removal</h5>
                            <p class="card-text">Safe and professional wisdom tooth extraction by experienced dentists.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-brush text-primary" style="font-size: 48px;"></i>
                            <h5 class="card-title mt-3">Dental Cleaning</h5>
                            <p class="card-text">Professional teeth cleaning and polishing for a brighter smile.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="services.php" class="btn btn-outline-primary">View All Services</a>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Why Choose Us?</h2>
            <div class="row g-4">
                <div class="col-md-3 text-center">
                    <i class="bi bi-people-fill text-primary" style="font-size: 48px;"></i>
                    <h5 class="mt-3">Expert Team</h5>
                    <p>Licensed and experienced dental professionals</p>
                </div>
                <div class="col-md-3 text-center">
                    <i class="bi bi-clock-history text-primary" style="font-size: 48px;"></i>
                    <h5 class="mt-3">Easy Booking</h5>
                    <p>Book your appointment online anytime</p>
                </div>
                <div class="col-md-3 text-center">
                    <i class="bi bi-shield-check text-primary" style="font-size: 48px;"></i>
                    <h5 class="mt-3">Safe & Clean</h5>
                    <p>Sterilized equipment and safe environment</p>
                </div>
                <div class="col-md-3 text-center">
                    <i class="bi bi-heart text-primary" style="font-size: 48px;"></i>
                    <h5 class="mt-3">Patient Care</h5>
                    <p>Comfortable and caring service</p>
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

