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
    <?php $nav_active = 'home'; require_once 'includes/nav-public.php'; ?>

    <!-- Hero Section (Framework: light blue) -->
    <section class="hero-section py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Your Smile, Our Priority</h1>
                    <p class="lead mb-4">Advanced dental care for a healthy smile. Book your appointment online today!</p>
                    <a href="book-appointment.php" class="btn btn-primary btn-lg me-2">
                        <i class="bi bi-calendar-check"></i> Book an Appointment
                    </a>
                    <a href="services.php" class="btn btn-outline-light btn-lg">Learn More</a>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="bi bi-heart-pulse-fill" style="font-size: 200px; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Preview (Framework: light blue cards) -->
    <section class="py-5 bg-white">
        <div class="container">
            <h2 class="section-title text-center">Our Services</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 denthub-card-light shadow-sm">
                        <div class="card-body">
                            <i class="bi bi-heart-pulse text-primary" style="font-size: 48px;"></i>
                            <h5 class="card-title mt-3">Tooth Restoration</h5>
                            <p class="card-text">Restore your damaged or decayed teeth with our professional restoration services.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 denthub-card-light shadow-sm">
                        <div class="card-body">
                            <i class="bi bi-scissors text-primary" style="font-size: 48px;"></i>
                            <h5 class="card-title mt-3">Wisdom Tooth Removal</h5>
                            <p class="card-text">Safe and professional wisdom tooth extraction by experienced dentists.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 denthub-card-light shadow-sm">
                        <div class="card-body">
                            <i class="bi bi-brush text-primary" style="font-size: 48px;"></i>
                            <h5 class="card-title mt-3">Dental Cleaning</h5>
                            <p class="card-text">Professional teeth cleaning and polishing for a brighter smile.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="services.php" class="btn btn-outline-primary border-2">View All Services</a>
            </div>
        </div>
    </section>

    <!-- Why Choose Us (Framework: icon cards) -->
    <section class="py-5" style="background-color: var(--denthub-bg);">
        <div class="container">
            <h2 class="section-title text-center">Why Choose Us?</h2>
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

    <!-- Footer (Framework: dark grey) -->
    <footer class="denthub-footer py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5 class="text-white"><?php echo APP_NAME; ?></h5>
                    <p class="mb-0">Quality dental care for you and your family.</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5 class="text-white">Contact Us</h5>
                    <p class="mb-1"><i class="bi bi-geo-alt me-2"></i> Block 5, Lot 3 & 4, Sabalo Street, Sangandaan, Caloocan City</p>
                    <p class="mb-1"><i class="bi bi-telephone me-2"></i> 0916 607 0999</p>
                    <p class="mb-0"><i class="bi bi-envelope me-2"></i> denthubcenter.sdc1@gmail.com</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5 class="text-white">Quick Links</h5>
                    <ul class="list-unstyled mb-0">
                        <li><a href="services.php">Services</a></li>
                        <li><a href="book-appointment.php">Book Appointment</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-3 border-secondary">
            <p class="text-center mb-0 small">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

