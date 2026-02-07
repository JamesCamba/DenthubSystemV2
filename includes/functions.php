<?php
/**
 * Denthub Dental Clinic - Helper Functions
 */

require_once __DIR__ . '/database.php';

// Optional external libraries (loaded via Composer in config.php)
use voku\helper\PhoneticAlgorithms;

// Generate patient number
function generatePatientNumber() {
    $db = getDB();
    // PostgreSQL syntax: SUBSTRING(string FROM start) and INTEGER instead of UNSIGNED
    $result = $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(patient_number FROM 4) AS INTEGER)), 0) + 1 AS next_num FROM patients");
    $row = $result->fetch_assoc();
    return 'PAT' . str_pad($row['next_num'], 6, '0', STR_PAD_LEFT);
}

// Generate appointment number
function generateAppointmentNumber() {
    $db = getDB();
    // PostgreSQL syntax: SUBSTRING(string FROM start) and INTEGER instead of UNSIGNED
    $result = $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(appointment_number FROM 4) AS INTEGER)), 0) + 1 AS next_num FROM appointments");
    $row = $result->fetch_assoc();
    return 'APT' . str_pad($row['next_num'], 6, '0', STR_PAD_LEFT);
}

// Generate lab case number
function generateCaseNumber() {
    $db = getDB();
    // PostgreSQL syntax: SUBSTRING(string FROM start) and INTEGER instead of UNSIGNED
    $result = $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(case_number FROM 4) AS INTEGER)), 0) + 1 AS next_num FROM lab_cases");
    $row = $result->fetch_assoc();
    return 'LAB' . str_pad($row['next_num'], 6, '0', STR_PAD_LEFT);
}

// Format date for display
function formatDate($date, $format = DISPLAY_DATE_FORMAT) {
    if (empty($date) || $date === '0000-00-00') return '-';
    return date($format, strtotime($date));
}

// Format time for display
function formatTime($time, $format = DISPLAY_TIME_FORMAT) {
    if (empty($time)) return '-';
    return date($format, strtotime($time));
}

// Format datetime for display
function formatDateTime($datetime) {
    if (empty($datetime)) return '-';
    return date(DISPLAY_DATE_FORMAT . ' ' . DISPLAY_TIME_FORMAT, strtotime($datetime));
}

/**
 * Mask email for display (e.g. j***@example.com) - protects sensitive info on profiles.
 */
function maskEmail($email) {
    if (empty($email) || !is_string($email)) return '-';
    $at = strpos($email, '@');
    if ($at === false) return substr($email, 0, 2) . '***';
    $local = substr($email, 0, $at);
    $domain = substr($email, $at);
    $len = strlen($local);
    if ($len <= 2) return $local . '***' . $domain;
    return substr($local, 0, 1) . str_repeat('*', min($len - 1, 5)) . substr($local, -1) . $domain;
}

/**
 * Mask phone for display (e.g. 09***123) - Philippines style; protects sensitive info.
 */
function maskPhone($phone) {
    if (empty($phone) || !is_string($phone)) return '-';
    $digits = preg_replace('/\D/', '', $phone);
    $len = strlen($digits);
    if ($len <= 4) return str_repeat('*', $len);
    return substr($digits, 0, 2) . '***' . substr($digits, -3);
}

// Get available time slots for a date ////////////////////////////////////////
function getAvailableTimeSlots($date, $dentist_id = null, $service_id = null) {
    $db = getDB();

    // Get day of week (0 = Sunday)
    $day_of_week = date('w', strtotime($date));

    // Get all active time slots
    $slots = $db->query("
        SELECT slot_id, slot_time
        FROM time_slots
        WHERE is_active = TRUE
        ORDER BY slot_time
    ");

    $availableSlots = [];

    while ($slot = $slots->fetch_assoc()) {

        /* ============================
           Check dentist availability
        ============================ */
        if ($dentist_id !== null) {
            $scheduleSql = "
                SELECT COUNT(*) AS count
                FROM dentist_schedules
                WHERE dentist_id = ?
                  AND day_of_week = ?
                  AND start_time <= ?
                  AND end_time > ?
                  AND is_available = TRUE
            ";

            $scheduleStmt = $db->prepare($scheduleSql);
            $scheduleStmt->bind_param(
                "iiss",
                $dentist_id,
                $day_of_week,
                $slot['slot_time'],
                $slot['slot_time']
            );
            $scheduleStmt->execute();
            $scheduleResult = $scheduleStmt->get_result()->fetch_assoc();

            if ($scheduleResult['count'] == 0) {
                continue; // Dentist not working at this time
            }
        }

        /* ============================
           Check if slot is booked
        ============================ */
        $sql = "
            SELECT COUNT(*) AS count
            FROM appointments
            WHERE appointment_date = ?
              AND appointment_time = ?
              AND status IN ('pending', 'confirmed')
        ";

        $params = [$date, $slot['slot_time']];
        $types  = "ss";

        if ($dentist_id !== null) {
            $sql .= " AND dentist_id = ?";
            $params[] = $dentist_id;
            $types   .= "i";
        }

        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['count'] == 0) {
            $availableSlots[] = $slot;
        }
    }

    return $availableSlots;
}

// Check if date is blocked
function isDateBlocked($date, $branch_id = 1) {
    $db = getDB();
    // PostgreSQL: is_active is a boolean
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM blocked_dates WHERE block_date = ? AND branch_id = ? AND is_active = TRUE");
    $stmt->bind_param("si", $date, $branch_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'] > 0;
}

// Get services list
function getServices($active_only = true) {
    $db = getDB();
    $sql = "SELECT * FROM services";
    if ($active_only) {
        // PostgreSQL: is_active is a boolean
        $sql .= " WHERE is_active = TRUE";
    }
    $sql .= " ORDER BY service_name";
    return $db->query($sql);
}

// Get dentists list
function getDentists($active_only = true, $service_id = null) {
    $db = getDB();
    $sql = "SELECT DISTINCT d.*, u.full_name, u.email, u.phone 
            FROM dentists d 
            JOIN users u ON d.user_id = u.user_id";
    
    $where = [];
    if ($active_only) {
        // PostgreSQL: is_active is a boolean on both tables
        $where[] = "d.is_active = TRUE AND u.is_active = TRUE";
    }
    
    // Filter by service mastery if service_id is provided
    if ($service_id) {
        $sql .= " JOIN dentist_service_mastery dsm ON d.dentist_id = dsm.dentist_id";
        $where[] = "dsm.service_id = " . intval($service_id);
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY u.full_name";
    return $db->query($sql);
}

// Get appointment status badge class
function getStatusBadge($status) {
    $badges = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger',
        'no_show' => 'dark'
    ];
    return $badges[$status] ?? 'secondary';
}

/**
 * Centralized appointment status update with business rules.
 *
 * Rules:
 * - New appointments start as 'pending'.
 * - From 'pending' you can go to 'confirmed' or 'cancelled'.
 * - From 'confirmed' you can go to 'completed', 'cancelled' or 'no_show'.
 * - Once 'completed', 'cancelled', or 'no_show', the record is locked.
 * - When moving to 'no_show', patient's no_show_count / last_no_show_date are updated.
 */
function updateAppointmentStatus($appointment_id, $new_status, $notes = null, $changed_by_user_id = null) {
    $db = getDB();

    // Fetch current appointment with full details for email
    $stmt = $db->prepare("
        SELECT a.appointment_id, a.patient_id, a.status, a.appointment_date, a.appointment_time, 
               a.appointment_number, p.first_name, p.last_name, p.email as patient_email,
               s.service_name, u.full_name as dentist_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN services s ON a.service_id = s.service_id
        LEFT JOIN dentists d ON a.dentist_id = d.dentist_id
        LEFT JOIN users u ON d.user_id = u.user_id
        WHERE a.appointment_id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $apt = $stmt->get_result()->fetch_assoc();

    if (!$apt) {
        return false;
    }

    $current = $apt['status'];
    $allowed = [];

    switch ($current) {
        case 'pending':
            $allowed = ['pending', 'confirmed', 'cancelled'];
            break;
        case 'confirmed':
            $allowed = ['confirmed', 'completed', 'cancelled', 'no_show'];
            break;
        case 'completed':
        case 'cancelled':
        case 'no_show':
            // Locked – cannot change
            $allowed = [$current];
            break;
        default:
            $allowed = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
    }

    if (!in_array($new_status, $allowed, true)) {
        // Invalid transition
        return false;
    }

    // If already locked, do nothing
    if (in_array($current, ['completed', 'cancelled', 'no_show'], true)) {
        return $current === $new_status;
    }

    $notesSql = $notes !== null ? ", notes = ?" : "";
    $params = [$new_status, $appointment_id];
    $types  = "si";

    if ($notes !== null) {
        $params = [$new_status, $notes, $appointment_id];
        $types  = "ssi";
    }

    $sql = "UPDATE appointments SET status = ?{$notesSql}, updated_at = NOW() WHERE appointment_id = ?";
    $updateStmt = $db->prepare($sql);
    $updateStmt->bind_param($types, ...$params);
    $ok = $updateStmt->execute();

    if (!$ok) {
        return false;
    }

    // Send confirmation email when status changes to 'confirmed'
    if ($new_status === 'confirmed' && $current !== 'confirmed' && !empty($apt['patient_email'])) {
        // Only send email if transitioning TO confirmed (not if already confirmed)
        require_once __DIR__ . '/mailer.php';
        $mailer = getMailer();
        
        $patient_name = trim($apt['first_name'] . ' ' . $apt['last_name']);
        $appointment_data = [
            'appointment_number' => $apt['appointment_number'],
            'service' => $apt['service_name'],
            'date' => formatDate($apt['appointment_date']),
            'time' => formatTime($apt['appointment_time']),
            'dentist' => $apt['dentist_name'] ?? 'TBD'
        ];
        
        // Send email (fail silently if email sending fails - don't block status update)
        try {
            $mailer->sendAppointmentConfirmation($apt['patient_email'], $patient_name, $appointment_data);
        } catch (Exception $e) {
            error_log("Failed to send appointment confirmation email: " . $e->getMessage());
        }
    }

    // Notify patient when appointment is cancelled (by staff/dentist)
    if ($new_status === 'cancelled' && $current !== 'cancelled' && !empty($apt['patient_email'])) {
        require_once __DIR__ . '/mailer.php';
        $mailer = getMailer();
        $patient_name = trim($apt['first_name'] . ' ' . $apt['last_name']);
        $appointment_data = [
            'appointment_number' => $apt['appointment_number'],
            'service' => $apt['service_name'],
            'date' => formatDate($apt['appointment_date']),
            'time' => formatTime($apt['appointment_time']),
            'dentist' => $apt['dentist_name'] ?? 'TBD'
        ];
        try {
            $mailer->sendAppointmentCancellation($apt['patient_email'], $patient_name, $appointment_data);
        } catch (Exception $e) {
            error_log("Failed to send appointment cancellation email: " . $e->getMessage());
        }
    }

    // Handle no-show tracking when transitioning to no_show
    if ($new_status === 'no_show' && $apt['patient_id']) {
        $patient_id = (int)$apt['patient_id'];
        $date = $apt['appointment_date'] ?: date('Y-m-d');

        // Increment no_show_count and set last_no_show_date
        $nsStmt = $db->prepare("
            UPDATE patients
            SET no_show_count = COALESCE(no_show_count, 0) + 1,
                last_no_show_date = GREATEST(COALESCE(last_no_show_date, '1900-01-01'), ?)
            WHERE patient_id = ?
        ");
        $nsStmt->bind_param("si", $date, $patient_id);
        $nsStmt->execute();
    }

    return true;
}

/**
 * Auto-mark overdue appointments (past date) that are still pending/confirmed as no_show.
 * This helps keep the UI consistent without a separate cron.
 */
function autoUpdateOverdueAppointments() {
    $db = getDB();
    $today = date('Y-m-d');

    // Find affected appointments first so we can update patients individually.
    $stmt = $db->prepare("
        SELECT appointment_id
        FROM appointments
        WHERE appointment_date < ?
          AND status IN ('pending', 'confirmed')
        LIMIT 200
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        updateAppointmentStatus((int)$row['appointment_id'], 'no_show');
    }
}

// Get lab case status badge class
function getLabStatusBadge($status) {
    $badges = [
        'pending' => 'warning',
        'in_progress' => 'info',
        'ready_for_pickup' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $badges[$status] ?? 'secondary';
}

// Get available status options based on current status and appointment date
function getAvailableStatusOptions($current_status, $appointment_date = null) {
    $options = [];
    $today = date('Y-m-d');
    
    // Determine if appointment is today, past, or future
    $is_today = false;
    $is_past = false;
    $is_future = false;
    
    if ($appointment_date) {
        $appt_date = date('Y-m-d', strtotime($appointment_date));
        if ($appt_date === $today) {
            $is_today = true;
        } elseif ($appt_date < $today) {
            $is_past = true;
        } else {
            $is_future = true;
        }
    }
    
    switch ($current_status) {
        case 'pending':
            if ($is_today) {
                // Today's pending appointments can only be cancelled or marked as no show
                // (Too late to confirm on the same day)
                $options = [
                    'pending' => 'Pending',
                    'cancelled' => 'Cancelled',
                    'no_show' => 'No Show'
                ];
            } elseif ($is_past) {
                // Past pending appointments can be completed, cancelled, or no show
                $options = [
                    'pending' => 'Pending',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    'no_show' => 'No Show'
                ];
            } else {
                // Future pending appointments can be confirmed or cancelled (no no_show yet, no completed)
                $options = [
                    'pending' => 'Pending',
                    'confirmed' => 'Confirmed',
                    'cancelled' => 'Cancelled'
                ];
            }
            break;
            
        case 'confirmed':
            if ($is_today) {
                // Today's confirmed appointments can be completed, cancelled, or no show
                $options = [
                    'confirmed' => 'Confirmed',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    'no_show' => 'No Show'
                ];
            } elseif ($is_past) {
                // Past confirmed appointments can be completed, cancelled, or no show
                $options = [
                    'confirmed' => 'Confirmed',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    'no_show' => 'No Show'
                ];
            } else {
                // Future confirmed appointments can only be cancelled (can't complete or no_show future appointments)
                $options = [
                    'confirmed' => 'Confirmed',
                    'cancelled' => 'Cancelled'
                ];
            }
            break;
            
        case 'completed':
        case 'cancelled':
        case 'no_show':
            // Locked - no changes allowed
            $options = [
                $current_status => ucfirst(str_replace('_', ' ', $current_status))
            ];
            break;
            
        default:
            // Default: allow all transitions based on date
            if ($is_today) {
                $options = [
                    'pending' => 'Pending',
                    'confirmed' => 'Confirmed',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    'no_show' => 'No Show'
                ];
            } elseif ($is_future) {
                $options = [
                    'pending' => 'Pending',
                    'confirmed' => 'Confirmed',
                    'cancelled' => 'Cancelled'
                ];
            } else {
                $options = [
                    'pending' => 'Pending',
                    'confirmed' => 'Confirmed',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    'no_show' => 'No Show'
                ];
            }
    }
    
    return $options;
}

// Sanitize input
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validate phone
function validatePhone($phone) {
    return preg_match('/^[0-9+\-\s()]+$/', $phone);
}

// Can this patient book online based on recent no-shows?
// Returns true if allowed, false if patient must call the clinic.
function canBookOnline($patient_id) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT no_show_count, last_no_show_date
        FROM patients
        WHERE patient_id = ?
          AND no_show_count >= 2
          AND last_no_show_date >= (CURRENT_DATE - INTERVAL '30 days')
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->num_rows === 0;
}

/**
 * Log changes to patient contact information.
 * Expects patient_audit_log table to exist in the database.
 */
function logPatientChange($patient_id, $field, $old_value, $new_value, $changed_by = null) {
    if ($old_value === $new_value) {
        return;
    }
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO patient_audit_log (patient_id, changed_field, old_value, new_value, changed_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssi", $patient_id, $field, $old_value, $new_value, $changed_by);
    $stmt->execute();
}

/**
 * Compute phonetic keys for a name using Metaphone 3 / Double Metaphone
 * via voku/phonetic-algorithms.
 */
function getMetaphone3Keys($first_name, $last_name) {
    if (!class_exists(PhoneticAlgorithms::class)) {
        // Fallback to built-in metaphone if library is not available
        return [
            'first' => metaphone($first_name),
            'last'  => metaphone($last_name),
        ];
    }

    $phonetic = new PhoneticAlgorithms();
    $first = $phonetic->doubleMetaphone($first_name);
    $last  = $phonetic->doubleMetaphone($last_name);

    // Use primary codes, trimmed to 20 chars as per schema
    return [
        'first' => substr($first['primary'] ?? '', 0, 20),
        'last'  => substr($last['primary'] ?? '', 0, 20),
    ];
}

