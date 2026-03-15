<?php
/**
 * API: Dentist updates their active branch.
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

requireRole('dentist');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
if ($branch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid branch']);
    exit;
}

$db = getDB();

// Ensure branch exists and is active
$stmt = $db->prepare("SELECT branch_id FROM branches WHERE branch_id = ? AND is_active = TRUE");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Branch not found']);
    exit;
}

// Update dentist's default branch
$stmt = $db->prepare("
    UPDATE dentists
    SET branch_id = ?
    WHERE user_id = ?
");
$stmt->bind_param("ii", $branch_id, $_SESSION['user_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update branch']);
}

