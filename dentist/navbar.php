<?php
if (!isset($user)) {
    $user = getCurrentUser();
}
$asset_img = '../assets/images';
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="denthub-mobile-topbar">
    <button class="btn btn-outline-light btn-sidebar-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="Toggle menu">
        <i class="bi bi-list" style="font-size: 1.5rem;"></i>
    </button>
    <a href="dashboard.php" class="d-flex align-items-center text-white text-decoration-none brand-img-wrap">
        <img src="<?php echo $asset_img; ?>/logowblue.png" alt="Logo" onerror="this.style.display='none'">
        <img src="<?php echo $asset_img; ?>/denthubtxt.png" alt="DENTHUB" onerror="this.style.display='none'">
        <!-- <span class="fw-bold ms-1">DENTHUB</span> -->
    </a>
</div>

<div class="offcanvas offcanvas-start denthub-sidebar-offcanvas d-lg-none" tabindex="-1" id="sidebarOffcanvas">
    <div class="offcanvas-header">
        <div class="sidebar-brand d-flex align-items-center brand-img-wrap">
            <img src="<?php echo $asset_img; ?>/logowblue.png" alt="Logo" onerror="this.style.display='none'">
            <img src="<?php echo $asset_img; ?>/denthubtxt.png" alt="DENTHUB" onerror="this.style.display='none'">
            <!-- <span class="text-white fw-bold ms-2">DENTHUB</span> -->
            <!-- <small class="d-block w-100 text-white opacity-90" style="font-size: 0.65rem;">Center for Smile<br>DENTAL CLINIC</small> -->
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="sidebar-rule" role="separator"></div>
    <div class="offcanvas-body">
        <nav class="sidebar-nav nav flex-column">
            <div class="sidebar-group">
                <span class="sidebar-group-label">Overview</span>
                <a class="nav-link <?php echo $current === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            </div>
            <div class="sidebar-group">
                <span class="sidebar-group-label">Clinic operations</span>
                <a class="nav-link <?php echo $current === 'appointments.php' ? 'active' : ''; ?>" href="appointments.php"><i class="bi bi-calendar-check"></i> My Appointment</a>
                <a class="nav-link <?php echo $current === 'schedule-manager.php' ? 'active' : ''; ?>" href="schedule-manager.php"><i class="bi bi-calendar-range"></i> Schedule Manager</a>
                <a class="nav-link <?php echo $current === 'calendar.php' ? 'active' : ''; ?>" href="calendar.php"><i class="bi bi-calendar3"></i> Calendar</a>
            </div>
        </nav>
        <div class="sidebar-rule" role="separator"></div>
        <div class="sidebar-user dropup">
            <a class="d-flex align-items-center text-decoration-none text-white dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i>
                <span class="sidebar-user-name"><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</div>

<aside class="denthub-sidebar denthub-sidebar-desktop d-none d-lg-flex">
    <div class="sidebar-brand">
        <div class="brand-img-wrap">
            <img src="<?php echo $asset_img; ?>/logowblue.png" alt="Logo" onerror="this.style.display='none'">
            <img src="<?php echo $asset_img; ?>/denthubtxt.png" alt="DENTHUB" onerror="this.style.display='none'">
        </div>
        <!-- <small>Center for Smile<br>DENTAL CLINIC</small> -->
    </div>
    <div class="sidebar-rule" role="separator"></div>
    <nav class="sidebar-nav nav flex-column">
        <div class="sidebar-group">
            <span class="sidebar-group-label">Overview</span>
            <a class="nav-link <?php echo $current === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        </div>
        <div class="sidebar-group">
            <span class="sidebar-group-label">Clinic operations</span>
            <a class="nav-link <?php echo $current === 'appointments.php' ? 'active' : ''; ?>" href="appointments.php"><i class="bi bi-calendar-check"></i> My Appointment</a>
            <a class="nav-link <?php echo $current === 'schedule-manager.php' ? 'active' : ''; ?>" href="schedule-manager.php"><i class="bi bi-calendar-range"></i> Schedule Manager</a>
            <a class="nav-link <?php echo $current === 'calendar.php' ? 'active' : ''; ?>" href="calendar.php"><i class="bi bi-calendar3"></i> Calendar</a>
        </div>
    </nav>
    <div class="sidebar-rule" role="separator"></div>
    <div class="sidebar-user dropup">
        <a class="d-flex align-items-center text-decoration-none text-white dropdown-toggle w-100" href="#" id="sidebarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle"></i>
            <span class="sidebar-user-name"><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>
</aside>
<script src="../assets/js/page-shell.js" defer></script>
