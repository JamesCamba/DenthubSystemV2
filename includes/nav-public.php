<?php
/**
 * Shared public navigation (Framework theme: dark blue bar, green CTA)
 * Used by: index, services, contact, book-appointment, patient/*
 * Set $nav_base (e.g. '../' for patient/*) and $nav_patient_base ('' for patient/*, 'patient/' for root)
 */
$nav_base = $nav_base ?? '';
$nav_patient_base = $nav_patient_base ?? 'patient/';
?>
<nav class="navbar navbar-expand-lg navbar-public">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?php echo $nav_base; ?>index.php">
            <img src="<?php echo $nav_base; ?>resources/468397831_122123862374527362_8536709169507539928_n.jpg" alt="<?php echo APP_NAME; ?>" height="36" class="me-2 rounded d-none d-md-inline">
            <span class="d-inline d-md-none"><i class="bi bi-tooth me-1"></i></span>
            <span>DENTHUB<br class="d-none d-lg-block"><small class="fw-normal d-lg-inline" style="font-size: 0.65em;">DENTAL CLINIC</small></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link <?php echo ($nav_active ?? '') === 'home' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link <?php echo ($nav_active ?? '') === 'services' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>services.php">Services</a></li>
                <li class="nav-item"><a class="nav-link <?php echo ($nav_active ?? '') === 'book' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>book-appointment.php">Book Appointment</a></li>
                <li class="nav-item"><a class="nav-link <?php echo ($nav_active ?? '') === 'contact' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>contact.php">Contact</a></li>
                <?php if (isPatientLoggedIn()): ?>
                    <?php 
                    $patient = getCurrentPatient();
                    $patient_name = $patient ? htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']) : htmlspecialchars($_SESSION['patient_name'] ?? 'User');
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <span class="d-none d-md-inline"><?php echo $patient_name; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo $nav_patient_base; ?>dashboard.php"><i class="bi bi-grid me-2"></i> Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?php echo $nav_patient_base; ?>profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $nav_base; ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $nav_base; ?>login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-book ms-2" href="<?php echo $nav_base; ?>book-appointment.php">Book Appointment</a></li>
                    <li class="nav-item"><a class="btn btn-outline-light ms-2" href="<?php echo $nav_base; ?>register.php">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
