<?php
/**
 * Lab Cases - Removed from system. Redirect to dashboard.
 */
require_once '../includes/auth.php';
requireStaff();
header('Location: dashboard.php');
exit;
