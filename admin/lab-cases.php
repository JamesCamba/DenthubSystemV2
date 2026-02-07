<?php
/**
 * Lab Cases - Removed from system. Redirect to dashboard.
 */
require_once '../includes/auth.php';
requireLogin();
header('Location: dashboard.php');
exit;
