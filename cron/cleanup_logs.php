<?php
/**
 * Denthub Dental Clinic - Log Cleanup / Rotation
 *
 * Retention policy:
 * - login_success: keep 14 days
 * - login_failed:  keep 7 days
 * - critical actions (appointment changes, user edits, etc): keep 1 month
 *
 * Intended to be executed via cron (CLI or HTTP).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// If called via web, send a simple JSON response
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
}

try {
    $db = getDB();

    // Ensure helpful indexes exist (in addition to schema migrations)
    // PostgreSQL supports IF NOT EXISTS so this is safe to run repeatedly.
    $db->query("CREATE INDEX IF NOT EXISTS idx_activity_log_action ON activity_log (action_type)");
    $db->query("CREATE INDEX IF NOT EXISTS idx_activity_log_created_at ON activity_log (created_at)");

    // 1) Successful login logs: keep 14 days
    $stmt1 = $db->prepare("
        DELETE FROM activity_log
        WHERE action_type = 'login_success'
          AND created_at < (CURRENT_TIMESTAMP - INTERVAL '14 days')
    ");
    $stmt1->execute();
    $deleted_success = $stmt1->affected_rows ?? 0;

    // 2) Failed login logs: keep 7 days
    $stmt2 = $db->prepare("
        DELETE FROM activity_log
        WHERE action_type = 'login_failed'
          AND created_at < (CURRENT_TIMESTAMP - INTERVAL '7 days')
    ");
    $stmt2->execute();
    $deleted_failed = $stmt2->affected_rows ?? 0;

    // 3) Critical actions: keep 1 month
    // Includes appointment status changes, reschedules, and generic user/patient/profile updates.
    $stmt3 = $db->prepare("
        DELETE FROM activity_log
        WHERE action_type IN (
            'appointment_status_changed',
            'appointment_rescheduled',
            'patient_profile_updated',
            'user_profile_updated',
            'patient_contact_updated',
            'user_account_updated'
        )
          AND created_at < (CURRENT_TIMESTAMP - INTERVAL '1 month')
    ");
    $stmt3->execute();
    $deleted_critical = $stmt3->affected_rows ?? 0;

    $result = [
        'success' => true,
        'deleted' => [
            'login_success' => $deleted_success,
            'login_failed' => $deleted_failed,
            'critical' => $deleted_critical,
        ],
    ];

    if (php_sapi_name() === 'cli') {
        echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        echo json_encode($result);
    }
} catch (Throwable $e) {
    error_log('cleanup_logs.php error: ' . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Log cleanup failed']);
    }
}
