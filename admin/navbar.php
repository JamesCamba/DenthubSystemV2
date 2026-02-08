<?php
if (!isset($user)) {
    $user = getCurrentUser();
}
$asset_img = '../assets/images';
$current = basename($_SERVER['PHP_SELF']);
?>
<!-- Mobile top bar: hamburger + logo (visible only on small screens) -->
<div class="denthub-mobile-topbar">
    <button class="btn btn-outline-light btn-sidebar-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="Toggle menu">
        <i class="bi bi-list" style="font-size: 1.5rem;"></i>
    </button>
    <a href="dashboard.php" class="d-flex align-items-center text-white text-decoration-none brand-img-wrap">
        <img src="<?php echo $asset_img; ?>/logowblue.png" alt="Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
        <span class="d-none"><i class="bi bi-tooth me-1"></i></span>
        <img src="<?php echo $asset_img; ?>/denthubtxt.png" alt="DENTHUB" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
        <span class="d-none fw-bold ms-1">DENTHUB</span>
    </a>
</div>

<!-- Offcanvas sidebar (mobile) -->
<div class="offcanvas offcanvas-start denthub-sidebar-offcanvas d-lg-none" tabindex="-1" id="sidebarOffcanvas">
    <div class="offcanvas-header border-bottom border-white border-opacity-25">
        <div class="sidebar-brand d-flex align-items-center brand-img-wrap">
            <img src="<?php echo $asset_img; ?>/logowblue.png" alt="Logo" onerror="this.style.display='none'">
            <img src="<?php echo $asset_img; ?>/denthubtxt.png" alt="DENTHUB" onerror="this.style.display='none'">
            <span class="text-white fw-bold ms-2">DENTHUB</span>
            <!-- <small class="d-block w-100 text-white opacity-90" style="font-size: 0.65rem;">Center for Smile<br>DENTAL CLINIC</small> -->
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0">
        <nav class="nav flex-column py-2">
            <a class="nav-link <?php echo $current === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php"><i class="bi bi-grid-1x2 me-2"></i> Dashboard</a>
            <a class="nav-link <?php echo $current === 'appointments.php' ? 'active' : ''; ?>" href="appointments.php"><i class="bi bi-calendar-check me-2"></i> Appointments</a>
            <a class="nav-link <?php echo $current === 'patients.php' ? 'active' : ''; ?>" href="patients.php"><i class="bi bi-people me-2"></i> Patients</a>
            <?php if (hasRole('admin')): ?>
            <a class="nav-link <?php echo $current === 'schedule.php' ? 'active' : ''; ?>" href="schedule.php"><i class="bi bi-calendar-week me-2"></i> Schedule</a>
            <a class="nav-link <?php echo $current === 'reports.php' ? 'active' : ''; ?>" href="reports.php"><i class="bi bi-graph-up me-2"></i> Reports</a>
            <a class="nav-link <?php echo $current === 'users.php' ? 'active' : ''; ?>" href="users.php"><i class="bi bi-person-gear me-2"></i> Users</a>
            <a class="nav-link <?php echo $current === 'activity-logs.php' ? 'active' : ''; ?>" href="activity-logs.php"><i class="bi bi-journal-text me-2"></i> Activity Logs</a>
            <a class="nav-link <?php echo $current === 'backups.php' ? 'active' : ''; ?>" href="backups.php"><i class="bi bi-database me-2"></i> Backups</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-user dropdown p-3 mt-auto">
            <a class="d-flex align-items-center text-decoration-none text-white dropdown-toggle" href="#" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle me-2" style="font-size: 1.5rem;"></i>
                <span><?php echo htmlspecialchars($user['full_name']); ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Desktop fixed sidebar -->
<aside class="denthub-sidebar denthub-sidebar-desktop d-none d-lg-flex">
    <div class="sidebar-brand">
        <div class="brand-img-wrap">
            <img src="<?php echo $asset_img; ?>/logowblue.png" alt="Logo" onerror="this.style.display='none'">
            <img src="<?php echo $asset_img; ?>/denthubtxt.png" alt="DENTHUB" onerror="this.style.display='none'">
        </div>
        <!-- <small>Center for Smile<br>DENTAL CLINIC</small> -->
    </div>
    <nav class="nav flex-column py-2">
        <a class="nav-link <?php echo $current === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php"><i class="bi bi-grid-1x2 me-2"></i> Dashboard</a>
        <a class="nav-link <?php echo $current === 'appointments.php' ? 'active' : ''; ?>" href="appointments.php"><i class="bi bi-calendar-check me-2"></i> Appointments</a>
        <a class="nav-link <?php echo $current === 'patients.php' ? 'active' : ''; ?>" href="patients.php"><i class="bi bi-people me-2"></i> Patients</a>
        <?php if (hasRole('admin')): ?>
        <a class="nav-link <?php echo $current === 'schedule.php' ? 'active' : ''; ?>" href="schedule.php"><i class="bi bi-calendar-week me-2"></i> Schedule</a>
        <a class="nav-link <?php echo $current === 'reports.php' ? 'active' : ''; ?>" href="reports.php"><i class="bi bi-graph-up me-2"></i> Reports</a>
        <a class="nav-link <?php echo $current === 'users.php' ? 'active' : ''; ?>" href="users.php"><i class="bi bi-person-gear me-2"></i> Users</a>
        <a class="nav-link <?php echo $current === 'activity-logs.php' ? 'active' : ''; ?>" href="activity-logs.php"><i class="bi bi-journal-text me-2"></i> Activity Logs</a>
        <a class="nav-link <?php echo $current === 'backups.php' ? 'active' : ''; ?>" href="backups.php"><i class="bi bi-database me-2"></i> Backups</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-user dropdown">
        <a class="d-flex align-items-center text-decoration-none text-white dropdown-toggle w-100" href="#" id="sidebarUserDropdown" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-2" style="font-size: 1.5rem;"></i>
            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>
</aside>
