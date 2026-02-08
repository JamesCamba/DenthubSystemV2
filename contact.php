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
    <?php $nav_active = 'contact'; require_once 'includes/nav-public.php'; ?>

    <section class="py-5">
        <div class="container">
            <h1 class="section-title text-center mb-5">Contact Us</h1>
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100 denthub-card-light shadow-sm">
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
                    <div class="card h-100 denthub-card-light shadow-sm">
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
                    <div class="card h-100 denthub-card-light shadow-sm">
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
                    <div class="card h-100 denthub-card-light shadow-sm">
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

    <!-- Footer #363848, text white -->
    <footer class="denthub-footer py-4 mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5 class="text-white"><?php echo APP_NAME; ?></h5>
                    <p class="text-white mb-0">Quality dental care for you and your family.</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5 class="text-white">Contact Us</h5>
                    <p class="text-white mb-1"><i class="bi bi-geo-alt me-2"></i> Block 5, Lot 3 & 4, Sabalo Street, Sangandaan, Caloocan City</p>
                    <p class="text-white mb-1"><i class="bi bi-telephone me-2"></i> 0916 607 0999</p>
                    <p class="text-white mb-0"><i class="bi bi-envelope me-2"></i> denthubcenter.sdc1@gmail.com</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5 class="text-white">Quick Links</h5>
                    <ul class="list-unstyled mb-0">
                        <li><a href="services.php" class="text-white">Services</a></li>
                        <li><a href="book-appointment.php" class="text-white">Book Appointment</a></li>
                        <li><a href="contact.php" class="text-white">Contact</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-3 border-light opacity-25">
            <p class="text-center mb-0 small text-white">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

