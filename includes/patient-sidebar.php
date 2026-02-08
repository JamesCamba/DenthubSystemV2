<?php
/**
 * Patient account sidebar: Dashboard, Book Appointment, profile at bottom.
 * Use on all patient/* pages when logged in.
 */
$patient = getCurrentPatient();
$patient_name = $patient ? htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']) : htmlspecialchars($_SESSION['patient_name'] ?? 'User');
$current_page = basename($_SERVER['PHP_SELF']);
$is_appointments_view = ($current_page === 'dashboard.php' && isset($_GET['view']) && $_GET['view'] === 'appointments') || $current_page === 'view-appointment.php';
?>
<aside class="denthub-sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-tooth me-2"></i> DENTHUB
        <small>Center for Smile<br>DENTAL CLINIC</small>
    </div>
    <nav class="nav flex-column py-2">
        <a class="nav-link <?php echo $current_page === 'dashboard.php' && !$is_appointments_view ? 'active' : ''; ?>" href="dashboard.php">
            <i class="bi bi-grid-1x2 me-2"></i> Dashboard
        </a>
        <a class="nav-link <?php echo $is_appointments_view ? 'active' : ''; ?>" href="dashboard.php?view=appointments">
            <i class="bi bi-calendar-check me-2"></i> My Appointment
        </a>
        <a class="nav-link" href="../book-appointment.php">
            <i class="bi bi-calendar-plus me-2"></i> Book Appointment
        </a>
    </nav>
    <div class="sidebar-user dropdown">
        <a class="d-flex align-items-center text-decoration-none text-white dropdown-toggle w-100" href="#" id="sidebarUserDropdown" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-2" style="font-size: 1.5rem;"></i>
            <span><?php echo $patient_name; ?></span>
            <i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>
</aside>
