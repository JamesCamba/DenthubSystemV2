<?php
/**
 * Denthub Dental Clinic - Helper Functions
 */

require_once __DIR__ . '/database.php';

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

// Get available time slots for a date
function getAvailableTimeSlots($date, $dentist_id = null, $service_id = null) {
    $db = getDB();
    
    // Get day of week (0=Sunday, 1=Monday, etc.)
    $day_of_week = date('w', strtotime($date));
    
    // Get all active time slots
    $slots = $db->query("SELECT slot_id, slot_time FROM time_slots WHERE is_active = TRUE ORDER BY slot_time");
    $availableSlots = [];
    
    while ($slot = $slots->fetch_assoc()) {
        // If dentist is specified, check their schedule
        if ($dentist_id) {
            // PostgreSQL: is_available is a boolean
            $scheduleStmt = $db->prepare("SELECT COUNT(*) as count FROM dentist_schedules 
                                         WHERE dentist_id = ? AND day_of_week = ? 
                                         AND start_time <= ? AND end_time > ? AND is_available = TRUE");
            $scheduleStmt->bind_param("iiss", $dentist_id, $day_of_week, $slot['slot_time'], $slot['slot_time']);
            $scheduleStmt->execute();
            $scheduleResult = $scheduleStmt->get_result()->fetch_assoc();
            
            if ($scheduleResult['count'] == 0) {
                continue; // Dentist not available at this time
            }
        }
        
        // Check if slot is already booked
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments 
                             WHERE appointment_date = ? AND appointment_time = ? 
                             AND status IN ('pending', 'confirmed') 
                             AND (dentist_id = ? OR ? IS NULL)");
        $stmt->bind_param("ssii", $date, $slot['slot_time'], $dentist_id, $dentist_id);
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

