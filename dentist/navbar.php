<?php
if (!isset($user)) {
    $user = getCurrentUser();
}
?>
<aside class="denthub-sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-tooth me-2"></i> DENTHUB
        <small>Center for Smile<br>DENTAL CLINIC</small>
    </div>
    <nav class="nav flex-column py-2">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
            <i class="bi bi-grid-1x2 me-2"></i> Dashboard
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'appointments.php' ? 'active' : ''; ?>" href="appointments.php">
            <i class="bi bi-calendar-check me-2"></i> My Appointment
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'schedule.php' ? 'active' : ''; ?>" href="schedule.php">
            <i class="bi bi-calendar-week me-2"></i> My Schedule
        </a>
    </nav>
    <div class="sidebar-user dropdown">
        <a class="d-flex align-items-center text-decoration-none text-white dropdown-toggle w-100" href="#" id="sidebarUserDropdown" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-2" style="font-size: 1.5rem;"></i>
            <span><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></span>
            <i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>
</aside>
