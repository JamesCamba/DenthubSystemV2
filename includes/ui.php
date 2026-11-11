<?php
function themeToggleButton($compact = false) {
    $cls = $compact ? 'btn btn-sm btn-outline-light' : 'btn btn-sm btn-outline-light w-100';
    return '<button type="button" class="' . $cls . '" data-theme-toggle aria-label="Toggle color theme"><i class="bi bi-moon-stars"></i>' . ($compact ? '' : ' <span class="ms-1">Theme</span>') . '</button>';
}

function renderNavGroups($groups, $current) {
    $html = '';
    foreach ($groups as $group) {
        if (empty($group['items'])) {
            continue;
        }
        $html .= '<div class="nav-group">';
        $html .= '<div class="nav-group-label">' . htmlspecialchars($group['label']) . '</div>';
        foreach ($group['items'] as $item) {
            $active = ($current === $item['page'] || (!empty($item['match']) && in_array($current, $item['match'], true))) ? ' active' : '';
            $html .= '<a class="nav-link' . $active . '" href="' . htmlspecialchars($item['href']) . '">';
            $html .= '<i class="bi ' . htmlspecialchars($item['icon']) . ' me-2"></i> ' . htmlspecialchars($item['text']);
            $html .= '</a>';
        }
        $html .= '</div>';
    }
    return $html;
}

function adminNavGroups($currentRole) {
    $ops = [
        ['page' => 'appointments.php', 'href' => 'appointments.php', 'icon' => 'bi-calendar-check', 'text' => 'Appointments', 'match' => ['appointments.php', 'view-appointment.php', 'add-appointment.php']],
        ['page' => 'calendar.php', 'href' => 'calendar.php', 'icon' => 'bi-calendar3', 'text' => 'Calendar'],
        ['page' => 'patients.php', 'href' => 'patients.php', 'icon' => 'bi-people', 'text' => 'Patients', 'match' => ['patients.php', 'view-patient.php']],
    ];
    $admin = [
        ['page' => 'schedule.php', 'href' => 'schedule.php', 'icon' => 'bi-calendar-week', 'text' => 'Schedule'],
        ['page' => 'reports.php', 'href' => 'reports.php', 'icon' => 'bi-graph-up', 'text' => 'Reports'],
        ['page' => 'users.php', 'href' => 'users.php', 'icon' => 'bi-person-gear', 'text' => 'Users', 'match' => ['users.php', 'add-user.php', 'edit-user.php']],
        ['page' => 'activity-logs.php', 'href' => 'activity-logs.php', 'icon' => 'bi-journal-text', 'text' => 'Activity Logs'],
        ['page' => 'backups.php', 'href' => 'backups.php', 'icon' => 'bi-database', 'text' => 'Backups'],
    ];
    $groups = [
        ['label' => 'Overview', 'items' => [
            ['page' => 'dashboard.php', 'href' => 'dashboard.php', 'icon' => 'bi-grid-1x2', 'text' => 'Dashboard'],
        ]],
        ['label' => 'Clinic operations', 'items' => $ops],
    ];
    if ($currentRole === 'admin') {
        $groups[] = ['label' => 'Administration', 'items' => $admin];
    }
    $groups[] = ['label' => 'Account', 'items' => [
        ['page' => 'profile.php', 'href' => 'profile.php', 'icon' => 'bi-person', 'text' => 'My Profile'],
    ]];
    return $groups;
}

function dentistNavGroups() {
    return [
        ['label' => 'Overview', 'items' => [
            ['page' => 'dashboard.php', 'href' => 'dashboard.php', 'icon' => 'bi-grid-1x2', 'text' => 'Dashboard'],
        ]],
        ['label' => 'Practice', 'items' => [
            ['page' => 'appointments.php', 'href' => 'appointments.php', 'icon' => 'bi-calendar-check', 'text' => 'My Appointments', 'match' => ['appointments.php', 'view-appointment.php']],
            ['page' => 'calendar.php', 'href' => 'calendar.php', 'icon' => 'bi-calendar3', 'text' => 'Calendar'],
        ]],
        ['label' => 'Availability', 'items' => [
            ['page' => 'schedule-manager.php', 'href' => 'schedule-manager.php', 'icon' => 'bi-calendar-range', 'text' => 'Schedule Manager', 'match' => ['schedule-manager.php', 'schedule.php']],
        ]],
        ['label' => 'Account', 'items' => [
            ['page' => 'profile.php', 'href' => 'profile.php', 'icon' => 'bi-person', 'text' => 'My Profile'],
        ]],
    ];
}

function patientNavGroups($current) {
    return [
        ['label' => 'Overview', 'items' => [
            ['page' => 'dashboard.php', 'href' => 'dashboard.php', 'icon' => 'bi-grid-1x2', 'text' => 'Dashboard'],
        ]],
        ['label' => 'Appointments', 'items' => [
            ['page' => 'calendar.php', 'href' => 'calendar.php', 'icon' => 'bi-calendar3', 'text' => 'Calendar'],
            ['page' => 'book-appointment.php', 'href' => '../book-appointment.php', 'icon' => 'bi-calendar-plus', 'text' => 'Book Appointment'],
        ]],
        ['label' => 'Account', 'items' => [
            ['page' => 'profile.php', 'href' => 'profile.php', 'icon' => 'bi-person', 'text' => 'My Profile', 'match' => ['profile.php', 'edit-profile.php']],
        ]],
    ];
}
