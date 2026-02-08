<?php
/**
 * Denthub Dental Clinic - Contact Page
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/database.php';

$db = getDB();
$branches = [];
$r = $db->query("SELECT branch_id, branch_name, address, phone, email FROM branches WHERE is_active = TRUE ORDER BY branch_id");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $branches[] = $row;
    }
}
$main_phone = '0916 607 0999';
$main_email = 'denthubcenter.sdc1@gmail.com';
if (!empty($branches)) {
    $first = $branches[0];
    if (!empty($first['phone'])) $main_phone = $first['phone'];
    if (!empty($first['email'])) $main_email = $first['email'];
}
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
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="contact-card h-100 shadow-sm">
                        <h5 class="contact-card-title"><?php echo APP_NAME; ?></h5>
                        <div class="contact-card-body">
                            <p class="text-muted">Quality dental care for you and your family.</p>
                        </div>
                    </div>
                </div>
                <?php foreach ($branches as $b): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="contact-card h-100 shadow-sm">
                        <h5 class="contact-card-title"><i class="bi bi-geo-alt me-2"></i><?php echo htmlspecialchars($b['branch_name']); ?></h5>
                        <div class="contact-card-body">
                            <p><?php echo nl2br(htmlspecialchars($b['address'])); ?></p>
                            <?php if (!empty($b['phone'])): ?>
                            <p><a href="tel:<?php echo preg_replace('/\D/', '', $b['phone']); ?>"><?php echo htmlspecialchars($b['phone']); ?></a></p>
                            <?php endif; ?>
                            <?php if (!empty($b['email'])): ?>
                            <p><a href="mailto:<?php echo htmlspecialchars($b['email']); ?>"><?php echo htmlspecialchars($b['email']); ?></a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="col-md-6 col-lg-4">
                    <div class="contact-card h-100 shadow-sm">
                        <h5 class="contact-card-title">Phone:</h5>
                        <div class="contact-card-body">
                            <p><a href="tel:09166070999"><?php echo htmlspecialchars($main_phone); ?></a></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="contact-card h-100 shadow-sm">
                        <h5 class="contact-card-title">Email:</h5>
                        <div class="contact-card-body">
                            <p><a href="mailto:<?php echo htmlspecialchars($main_email); ?>"><?php echo htmlspecialchars($main_email); ?></a></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="contact-card h-100 shadow-sm">
                        <h5 class="contact-card-title">Business Hours</h5>
                        <div class="contact-card-body">
                            <p><span class="text-primary fw-semibold">Monday - Saturday:</span><br><span class="ms-3">9:00 AM - 5:00 PM</span></p>
                            <p><span class="text-primary fw-semibold">Sunday:</span><br><span class="ms-3">Closed</span></p>
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
