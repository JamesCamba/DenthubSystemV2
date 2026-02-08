<?php
/**
 * Denthub Dental Clinic - Services Page
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$services = getServices();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php $nav_active = 'services'; require_once 'includes/nav-public.php'; ?>

    <!-- Services Section -->
    <section class="py-5">
        <div class="container">
            <h1 class="section-title text-center mb-5">Our Dental Services</h1>
            <p class="text-center text-muted mb-5">Choose from our wide range of professional dental services</p>

            <div class="row g-4">
                <?php while ($service = $services->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 denthub-card-light shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($service['service_name']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($service['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> <?php echo $service['duration_minutes']; ?> mins
                                        </small>
                                        <?php if ($service['price']): ?>
                                            <br><strong class="text-primary">₱<?php echo number_format($service['price'], 2); ?></strong>
                                        <?php endif; ?>
                                    </div>
                                    <a href="book-appointment.php?service=<?php echo $service['service_id']; ?>" class="btn btn-primary btn-sm">
                                        Book This Service
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="text-center mt-5">
                <a href="book-appointment.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-calendar-check"></i> Book an Appointment Now
                </a>
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

