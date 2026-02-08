<?php
/**
 * API: Get dentists by branch + service (assigned to branch and with service mastery)
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$service_id = intval($_GET['service_id'] ?? 0);
$branch_id = !empty($_GET['branch_id']) ? intval($_GET['branch_id']) : null;

if ($service_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
    exit;
}

$dentists = getDentists(true, $service_id, $branch_id);
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
