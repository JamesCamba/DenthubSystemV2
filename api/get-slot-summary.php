<?php
/**
 * API: Slot summary for today and tomorrow.
 * role=admin|dentist|patient
 * branch_id: for admin/patient (0 or empty = all branches for admin)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$role = $_GET['role'] ?? '';
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

$today = appTodayImmutable()->format('Y-m-d');
$tomorrow = appTodayImmutable()->modify('+1 day')->format('Y-m-d');

try {
    $db = getDB();
    $branches = [];

    if ($role === 'admin' || $role === 'staff' || ($role === '' && (hasRole('admin') || hasRole('staff')))) {
        requireStaff();
        if ($branch_id > 0) {
            $stmt = $db->prepare("SELECT branch_id, branch_name FROM branches WHERE is_active = TRUE AND branch_id = ? ORDER BY branch_name");
            $stmt->bind_param("i", $branch_id);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $db->query("SELECT branch_id, branch_name FROM branches WHERE is_active = TRUE ORDER BY branch_name");
        }
        while ($row = $res->fetch_assoc()) {
            $bid = (int)$row['branch_id'];
            $branches[] = [
                'branch_id' => $bid,
                'branch_name' => $row['branch_name'],
                'today' => getAvailableSlotCountForDateAndBranch($today, $bid),
                'tomorrow' => getAvailableSlotCountForDateAndBranch($tomorrow, $bid),
            ];
        }
    } elseif ($role === 'dentist') {
        requireRole('dentist');
        // Use dentist's own branch and id
        $stmt = $db->prepare("SELECT d.dentist_id, d.branch_id, u.full_name 
                              FROM dentists d 
                              JOIN users u ON d.user_id = u.user_id 
                              WHERE d.user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $dent = $stmt->get_result()->fetch_assoc();
        if ($dent) {
            $bid = (int)$dent['branch_id'];
            $did = (int)$dent['dentist_id'];
            $branches[] = [
                'branch_id' => $bid,
                'branch_name' => '',
                'today' => getAvailableSlotCountForDateAndBranch($today, $bid, $did),
                'tomorrow' => getAvailableSlotCountForDateAndBranch($tomorrow, $bid, $did),
            ];
        }
    } elseif ($role === 'patient') {
        requirePatientLogin();
        if ($branch_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'branch_id is required for patients']);
            exit;
        }
        $stmt = $db->prepare("SELECT branch_name FROM branches WHERE branch_id = ? AND is_active = TRUE");
        $stmt->bind_param("i", $branch_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $branches[] = [
                'branch_id' => $branch_id,
                'branch_name' => $row['branch_name'],
                'today' => getAvailableSlotCountForDateAndBranch($today, $branch_id),
                'tomorrow' => getAvailableSlotCountForDateAndBranch($tomorrow, $branch_id),
            ];
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid role']);
        exit;
    }

    echo json_encode(['success' => true, 'today' => $today, 'tomorrow' => $tomorrow, 'branches' => $branches]);
} catch (Throwable $e) {
    error_log('get-slot-summary error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

