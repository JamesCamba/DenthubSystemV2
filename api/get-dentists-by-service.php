<?php
/**
 * API: Get dentists by service ID (filtered by mastery)
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$service_id = intval($_GET['service_id'] ?? 0);

if ($service_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
    exit;
}

$dentists = getDentists(true, $service_id);
$result = [];

while ($dentist = $dentists->fetch_assoc()) {
    $result[] = [
        'dentist_id' => $dentist['dentist_id'],
        'full_name' => $dentist['full_name'],
        'email' => $dentist['email'],
        'phone' => $dentist['phone']
    ];
}

echo json_encode(['success' => true, 'dentists' => $result]);
