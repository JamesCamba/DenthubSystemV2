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

// Format datetime for display (assumes input is in app local time)
function formatDateTime($datetime) {
    if (empty($datetime)) return '-';
    return date(DISPLAY_DATE_FORMAT . ' ' . DISPLAY_TIME_FORMAT, strtotime($datetime));
}

/**
 * Format datetime stored in UTC (e.g. from Neon/PostgreSQL) for display in app timezone (Asia/Manila).
 * Use for created_at, updated_at from database.
 */
function formatDateTimeUtcToApp($datetime) {
    if (empty($datetime)) return '-';
    $tz = defined('TIMEZONE') ? TIMEZONE : 'Asia/Manila';
    try {
        $utc = new DateTimeZone('UTC');
        $appTz = new DateTimeZone($tz);
        $dt = new DateTime($datetime, $utc);
        $dt->setTimezone($appTz);
        return $dt->format(DISPLAY_DATE_FORMAT . ' ' . DISPLAY_TIME_FORMAT);
    } catch (Exception $e) {
        return date(DISPLAY_DATE_FORMAT . ' ' . DISPLAY_TIME_FORMAT, strtotime($datetime));
    }
}

/** Current datetime in app timezone for filenames (e.g. backup_2026-02-08_18-50-55.sql) */
function appNow($format = 'Y-m-d_H-i-s') {
    $tz = defined('TIMEZONE') ? TIMEZONE : 'Asia/Manila';
    try {
        $dt = new DateTime('now', new DateTimeZone($tz));
        return $dt->format($format);
    } catch (Exception $e) {
        return date($format);
    }
}

/**
 * Tables that store backups. They must not be included in a backup,
 * or each new dump copies every previous dump and the size doubles.
 */
function databaseBackupExcludedTables() {
    return ['database_backups'];
}

function buildPgDumpCommand($host, $port, $user, $dbname) {
    $parts = [
        'pg_dump',
        '-h', escapeshellarg($host),
        '-p', (string)(int)$port,
        '-U', escapeshellarg($user),
        '-d', escapeshellarg($dbname),
        '--no-owner',
        '--no-acl',
    ];
    foreach (databaseBackupExcludedTables() as $table) {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            continue;
        }
        // Schema-qualified and plain names, both the table and its rows.
        // Leaving this table in the dump copies every older dump into the new one.
        foreach (['public.' . $table, $table] as $pattern) {
            $quoted = escapeshellarg($pattern);
            $parts[] = '--exclude-table=' . $quoted;
            $parts[] = '--exclude-table-data=' . $quoted;
        }
    }
    return implode(' ', $parts) . ' 2>/dev/null';
}

/**
 * True when a dump still contains the backup archive table.
 * Line-anchored so text stored in other tables is not treated as a match.
 */
function backupDumpIncludesExcludedTables($sql) {
    if (!is_string($sql) || $sql === '') {
        return false;
    }
    foreach (databaseBackupExcludedTables() as $table) {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            continue;
        }
        $q = preg_quote($table, '/');
        $name = '(?:(?:"public"|public)\s*\.\s*)?(?:"' . $q . '"|' . $q . ')';
        $patterns = [
            '/^CREATE TABLE\s+(?:ONLY\s+)?' . $name . '\b/mi',
            '/^COPY\s+' . $name . '\b/mi',
            '/^INSERT INTO\s+' . $name . '\b/mi',
            '/^-- Data for Name:\s+' . $q . ';/mi',
            '/^-- Name:\s+' . $q . '; Type: TABLE;/mi',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Full backup of current clinic data. The database_backups table is omitted
 * so stored archives are not copied into the new archive.
 */
function generateDatabaseBackupContent() {
    $host = DB_HOST;
    $port = (int)DB_PORT;
    $dbname = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
    $ssl = defined('DB_SSLMODE') ? DB_SSLMODE : 'require';

    $previousPassword = getenv('PGPASSWORD');
    $previousSsl = getenv('PGSSLMODE');
    putenv('PGPASSWORD=' . $pass);
    putenv('PGSSLMODE=' . $ssl);
    $content = @shell_exec(buildPgDumpCommand($host, $port, $user, $dbname));
    if ($previousPassword === false) {
        putenv('PGPASSWORD');
    } else {
        putenv('PGPASSWORD=' . $previousPassword);
    }
    if ($previousSsl === false) {
        putenv('PGSSLMODE');
    } else {
        putenv('PGSSLMODE=' . $previousSsl);
    }

    if (!is_string($content) || strlen($content) < 100 || backupDumpIncludesExcludedTables($content)) {
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s', $host, $port, $dbname, $ssl),
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $content = buildPhpDatabaseBackup($pdo);
        if (backupDumpIncludesExcludedTables($content)) {
            throw new RuntimeException('Backup still includes the database backup table.');
        }
    }

    return $content;
}

function buildPhpDatabaseBackup($pdo) {
    $excluded = array_fill_keys(array_map('strtolower', databaseBackupExcludedTables()), true);
    $content = "-- Denthub Backup (PHP fallback)\n-- " . appNow('Y-m-d H:i:s') . " (" . (defined('TIMEZONE') ? TIMEZONE : 'Asia/Manila') . ")\n";
    $content .= "-- Excluded tables: " . implode(', ', databaseBackupExcludedTables()) . "\n\n";
    $tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if (isset($excluded[strtolower($table)])) {
            continue;
        }
        $content .= "\n-- Table: $table\n";
        $rows = $pdo->query('SELECT * FROM "' . str_replace('"', '""', $table) . '"')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $cols = array_keys($row);
            $vals = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote($v);
            }, array_values($row));
            $content .= 'INSERT INTO "' . $table . '" ("' . implode('","', $cols) . '") VALUES (' . implode(',', $vals) . ");\n";
        }
    }
    return $content;
}

/**
 * Mask email for display: first char + *** + last char of local + @domain (e.g. j***n@example.com)
 */
function maskEmail($email) {
    if (empty($email) || !is_string($email)) return '-';
    $at = strpos($email, '@');
    if ($at === false) return substr($email, 0, 1) . '***';
    $local = substr($email, 0, $at);
    $domain = substr($email, $at);
    $len = strlen($local);
    if ($len <= 1) return $local . '***' . $domain;
    return substr($local, 0, 1) . '***' . substr($local, -1) . $domain;
}

/**
 * Mask phone for display: first 2 digits + asterisks + last digit (e.g. 09********3)
 */
function maskPhone($phone) {
    if (empty($phone) || !is_string($phone)) return '-';
    $digits = preg_replace('/\D/', '', $phone);
    $len = strlen($digits);
    if ($len <= 3) return str_repeat('*', $len);
    return substr($digits, 0, 2) . str_repeat('*', $len - 3) . substr($digits, -1);
}

/**
 * Log activity for admin audit (who did what). Uses activity_log table.
 * @param string $action_type e.g. login_success, login_failed, password_changed, email_changed, phone_changed, appointment_status_changed
 * @param string $details Optional description (e.g. "Appointment APT000001 → confirmed")
 * @param int|null $actor_user_id Staff/dentist/admin user_id (fetches username, full_name, role if not provided)
 * @param int|null $actor_patient_id Patient id (stores as Patient role)
 * @param string|null $actor_username Override username (e.g. for failed login we have identifier)
 * @param string|null $actor_full_name Override full name
 * @param string|null $actor_role Override role (e.g. 'Patient', 'guest')
 */
function logActivity($action_type, $details = '', $actor_user_id = null, $actor_patient_id = null, $actor_username = null, $actor_full_name = null, $actor_role = null) {
    try {
        $db = getDB();
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if (is_string($ip) && strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ($actor_user_id && $actor_username === null) {
            $stmt = $db->prepare("SELECT username, full_name, role FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $actor_user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $actor_username = $row['username'];
                $actor_full_name = $row['full_name'];
                $actor_role = $row['role'];
            }
        }
        if ($actor_patient_id && $actor_full_name === null) {
            $stmt = $db->prepare("SELECT first_name, last_name FROM patients WHERE patient_id = ?");
            $stmt->bind_param("i", $actor_patient_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $actor_full_name = trim($row['first_name'] . ' ' . $row['last_name']);
                $actor_role = $actor_role ?: 'Patient';
            }
        }

        $actor_type = $actor_patient_id ? 'patient' : ($actor_user_id ? 'user' : 'guest');
        $aid = $actor_patient_id ?: $actor_user_id;
        $aid = $aid !== null ? (int)$aid : null;

        $stmt = $db->prepare("INSERT INTO activity_log (action_type, actor_type, actor_id, username, full_name, role, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissssss",
            $action_type,
            $actor_type,
            $aid,
            $actor_username ?? '',
            $actor_full_name ?? '',
            $actor_role ?? '',
            $details,
            substr($ip, 0, 45),
            substr($ua, 0, 500)
        );
        $stmt->execute();
    } catch (Exception $e) {
        error_log('logActivity failed: ' . $e->getMessage());
    }
}

// Clinic bookable window: Monday–Sunday 10:00–18:00, 30-minute starts, last slot 17:30.
function ensureClinicBookingWindow() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $db = getDB();

    // Slot rows are start times. 18:00 is closing time, so the last bookable start is 17:30.
    $db->query("
        INSERT INTO time_slots (slot_time, is_active)
        SELECT gs::time, TRUE
        FROM generate_series(
            TIMESTAMP '2000-01-01 10:00:00',
            TIMESTAMP '2000-01-01 17:30:00',
            INTERVAL '30 minutes'
        ) AS gs
        ON CONFLICT (slot_time) DO UPDATE SET is_active = TRUE
    ");
    $db->query("
        UPDATE time_slots
        SET is_active = FALSE
        WHERE slot_time < TIME '10:00:00'
           OR slot_time > TIME '17:30:00'
    ");
    $db->query("
        INSERT INTO clinic_operating_hours (branch_id, open_time, close_time)
        SELECT b.branch_id, TIME '10:00:00', TIME '18:00:00'
        FROM branches b
        ON CONFLICT (branch_id) DO UPDATE
        SET open_time = EXCLUDED.open_time,
            close_time = EXCLUDED.close_time,
            updated_at = CURRENT_TIMESTAMP
    ");
    // Legacy weekday template was 09:00–17:00 (last slot 16:30). Align it to 10:00–18:00.
    $db->query("
        UPDATE dentist_schedules
        SET start_time = TIME '10:00:00',
            end_time = TIME '18:00:00',
            is_available = TRUE
        WHERE start_time = TIME '09:00:00'
          AND end_time = TIME '17:00:00'
    ");
    $db->query("
        INSERT INTO dentist_schedules (dentist_id, day_of_week, start_time, end_time, is_available)
        SELECT d.dentist_id, dow.day_of_week, TIME '10:00:00', TIME '18:00:00', TRUE
        FROM dentists d
        CROSS JOIN (SELECT generate_series(0, 6) AS day_of_week) dow
        WHERE d.is_active = TRUE
          AND NOT EXISTS (
              SELECT 1
              FROM dentist_schedules ds
              WHERE ds.dentist_id = d.dentist_id
                AND ds.day_of_week = dow.day_of_week
          )
    ");
}

// Fetch clinic operating hours for a branch (falls back to system defaults if not configured)
function getClinicOperatingHours($branch_id) {
    ensureClinicBookingWindow();
    $db = getDB();
    $stmt = $db->prepare("SELECT open_time, close_time FROM clinic_operating_hours WHERE branch_id = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    // System-level default base schedule (Level 1)
    $defaultOpen = '10:00:00';
    $defaultClose = '18:00:00';

    if ($row) {
        // Ensure stored hours are sensible
        $open = $row['open_time'] ?? $defaultOpen;
        $close = $row['close_time'] ?? $defaultClose;
        if ($open >= $close) {
            $open = $defaultOpen;
            $close = $defaultClose;
        }
        return [$open, $close];
    }

    return [$defaultOpen, $defaultClose];
}

function dentistIsAssignedToBranch($dentist_id, $branch_id) {
    $dentist_id = (int)$dentist_id;
    $branch_id = (int)$branch_id;
    if ($dentist_id <= 0 || $branch_id <= 0) {
        return false;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT 1 FROM dentists WHERE dentist_id = ? AND branch_id = ? AND is_active = TRUE LIMIT 1");
    $stmt->bind_param("ii", $dentist_id, $branch_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function denthubPagination($page, $totalPages, array $query = [], $pageParam = 'page', $id = '') {
    $page = max(1, (int)$page);
    $totalPages = max(1, (int)$totalPages);
    $page = min($page, $totalPages);
    unset($query['ajax']);

    $prev = $query;
    $prev[$pageParam] = $page - 1;
    $next = $query;
    $next[$pageParam] = $page + 1;

    $idAttr = $id !== '' ? ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"' : '';
    $html = '<div class="denthub-table-pagination"' . $idAttr . '>';
    $html .= '<span class="page-info">Page ' . $page . ' of ' . $totalPages . '</span>';
    if ($page <= 1) {
        $html .= '<span class="btn btn-sm btn-outline-primary denthub-page-arrow disabled" aria-disabled="true" aria-label="Previous page"><i class="bi bi-chevron-left"></i></span>';
    } else {
        $html .= '<a class="btn btn-sm btn-outline-primary denthub-page-arrow" href="?' . htmlspecialchars(http_build_query($prev), ENT_QUOTES, 'UTF-8') . '" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>';
    }
    if ($page >= $totalPages) {
        $html .= '<span class="btn btn-sm btn-outline-primary denthub-page-arrow disabled" aria-disabled="true" aria-label="Next page"><i class="bi bi-chevron-right"></i></span>';
    } else {
        $html .= '<a class="btn btn-sm btn-outline-primary denthub-page-arrow" href="?' . htmlspecialchars(http_build_query($next), ENT_QUOTES, 'UTF-8') . '" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>';
    }
    $html .= '</div>';
    return $html;
}

// Compute the effective working window for a doctor on a given date and branch,
// respecting Level 2 (monthly schedule), weekly schedule, and Level 1 defaults,
// all clamped inside clinic operating hours.
function getDoctorWorkingWindow($date, $dentist_id, $branch_id) {
    if (!dentistIsAssignedToBranch($dentist_id, $branch_id)) {
        return [null, null];
    }
    $db = getDB();
    [$clinicOpen, $clinicClose] = getClinicOperatingHours($branch_id);

    // Level 2 - monthly override
    $stmt = $db->prepare("
        SELECT start_time, end_time
        FROM doctor_monthly_schedules
        WHERE doctor_id = ?
          AND schedule_date = ?
          AND branch_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("isi", $dentist_id, $date, $branch_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $start = max($clinicOpen, $row['start_time']);
        $end = min($clinicClose, $row['end_time']);
        if ($start < $end) {
            return [$start, $end];
        }
        // If invalid after clamping, treat as no availability
        return [null, null];
    }

    // Weekly schedule (existing table) - used as a template
    $day_of_week = date('w', strtotime($date));
    $stmt = $db->prepare("
        SELECT start_time, end_time
        FROM dentist_schedules
        WHERE dentist_id = ?
          AND day_of_week = ?
          AND is_available = TRUE
        LIMIT 1
    ");
    $stmt->bind_param("ii", $dentist_id, $day_of_week);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $start = max($clinicOpen, $row['start_time']);
        $end = min($clinicClose, $row['end_time']);
        if ($start < $end) {
            return [$start, $end];
        }
        return [null, null];
    }

    // Level 1 - system default (Monday–Sunday 10:00–18:00), clamped to clinic hours
    $defaultStart = '10:00:00';
    $defaultEnd = '18:00:00';
    $start = max($clinicOpen, $defaultStart);
    $end = min($clinicClose, $defaultEnd);
    if ($start < $end) {
        return [$start, $end];
    }

    return [null, null];
}

// Get available time slots for a date using the hierarchical schedule model.
// $exclude_appointment_id: when rescheduling, exclude this appointment so its current slot shows as available.
function getAvailableTimeSlots($date, $dentist_id = null, $service_id = null, $branch_id = 1, $exclude_appointment_id = null) {
    $db = getDB();

    if (isDateBlocked($date, $branch_id)) {
        return [];
    }

    // If a specific dentist is selected, compute their working window first.
    $windowStart = null;
    $windowEnd = null;
    if ($dentist_id !== null) {
        [$windowStart, $windowEnd] = getDoctorWorkingWindow($date, $dentist_id, $branch_id);
        if ($windowStart === null || $windowEnd === null) {
            return [];
        }
    } else {
        // No specific dentist: just use clinic operating hours as coarse filter.
        [$windowStart, $windowEnd] = getClinicOperatingHours($branch_id);
    }

    $sql = "
        SELECT ts.slot_id, ts.slot_time
        FROM time_slots ts
        WHERE ts.is_active = TRUE
          AND ts.slot_time >= ?
          AND ts.slot_time < ?
          AND NOT EXISTS (
              SELECT 1
              FROM appointments a
              WHERE a.appointment_date = ?
                AND a.appointment_time = ts.slot_time
                AND a.branch_id = ?
                AND a.status IN ('pending', 'confirmed')
    ";

    $params = [$windowStart, $windowEnd, $date, $branch_id];
    $types  = "sssi";

    if ($dentist_id !== null) {
        $sql .= " AND a.dentist_id = ?";
        $params[] = $dentist_id;
        $types   .= "i";
    }

    if ($exclude_appointment_id !== null) {
        $sql .= " AND a.appointment_id != ?";
        $params[] = $exclude_appointment_id;
        $types   .= "i";
    }

    $sql .= "
          )
        ORDER BY ts.slot_time
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $availableSlots = [];
    while ($row = $result->fetch_assoc()) {
        $availableSlots[] = $row;
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

/**
 * Optional helper: get block reason for a date/branch (used for calendar tooltips).
 */
function getBlockReason($date, $branch_id = 1) {
    $db = getDB();
    $stmt = $db->prepare("SELECT reason FROM blocked_dates WHERE block_date = ? AND branch_id = ? AND is_active = TRUE LIMIT 1");
    $stmt->bind_param("si", $date, $branch_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['reason'] ?? '';
}

/**
 * Get total available bookable slots for a date and branch.
 * A "slot" is a time_slots entry where:
 * - there is at least one active dentist scheduled at that time for that branch
 * - AND there is no pending/confirmed appointment for that dentist at that time
 * - AND the date is not blocked for that branch.
 *
 * When $dentist_id is provided, only that dentist's slots are counted.
 */
function getAvailableSlotCountForDateAndBranch($date, $branch_id, $dentist_id = null) {
    $db = getDB();

    if (isDateBlocked($date, $branch_id)) {
        return 0;
    }

    // Resolve clinic operating hours once (with PHP fallback defaults).
    [$open_time, $close_time] = getClinicOperatingHours($branch_id);

    // Count distinct (dentist, slot_time) pairs that are inside each doctor's working window
    // and not already booked. Let PostgreSQL handle the heavy lifting with a set-based query.
    $sql = "
        WITH active_dentists AS (
            SELECT d.dentist_id
            FROM dentists d
            JOIN users u ON u.user_id = d.user_id
            WHERE d.is_active = TRUE
              AND u.is_active = TRUE
              AND d.branch_id = ?
    ";

    $params = [$branch_id];
    $types  = "i";

    if ($dentist_id !== null) {
        $sql .= " AND d.dentist_id = ? ";
        $params[] = $dentist_id;
        $types   .= "i";
    }

    $sql .= "
        ),
        windows AS (
            SELECT
                ad.dentist_id,
                GREATEST(
                    COALESCE(ms.start_time, ds.start_time, TIME '10:00:00'),
                    GREATEST(?::time, TIME '10:00:00')
                ) AS start_time,
                LEAST(
                    COALESCE(ms.end_time, ds.end_time, TIME '18:00:00'),
                    LEAST(?::time, TIME '18:00:00')
                ) AS end_time
            FROM active_dentists ad
            LEFT JOIN doctor_monthly_schedules ms
              ON ms.doctor_id = ad.dentist_id
             AND ms.schedule_date = ?
             AND ms.branch_id = ?
            LEFT JOIN dentist_schedules ds
              ON ds.dentist_id = ad.dentist_id
             AND ds.day_of_week = EXTRACT(DOW FROM ?::date)
             AND ds.is_available = TRUE
        )
        SELECT COUNT(*) AS slots_count
        FROM time_slots ts
        JOIN windows w
          ON ts.slot_time >= w.start_time
         AND ts.slot_time <  w.end_time
        WHERE ts.is_active = TRUE
          AND NOT EXISTS (
                SELECT 1
                FROM appointments a
                WHERE a.appointment_date = ?
                  AND a.appointment_time = ts.slot_time
                  AND a.branch_id = ?
                  AND a.status IN ('pending', 'confirmed')
                  AND a.dentist_id = w.dentist_id
          )
    ";

    $params[] = $open_time;   // GREATEST(open_time, '10:00')
    $params[] = $close_time;  // LEAST(close_time, '18:00')
    $params[] = $date;        // monthly schedule date
    $params[] = $branch_id;   // monthly schedule branch
    $params[] = $date;        // day of week
    $params[] = $date;        // appointment date
    $params[] = $branch_id;   // appointment branch
    $types   .= "sssissi";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (int)($row['slots_count'] ?? 0);
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

// Get dentists list (optionally by branch and/or service)
function getDentists($active_only = true, $service_id = null, $branch_id = null) {
    $db = getDB();
    $sql = "SELECT DISTINCT d.*, u.full_name, u.email, u.phone 
            FROM dentists d 
            JOIN users u ON d.user_id = u.user_id";
    
    $where = [];
    if ($active_only) {
        $where[] = "d.is_active = TRUE AND u.is_active = TRUE";
    }
    if ($branch_id !== null && $branch_id > 0) {
        $where[] = "d.branch_id = " . intval($branch_id);
    }
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

    // Fetch current appointment with full details for email (patient_email from patients or patient_accounts)
    $stmt = $db->prepare("
        SELECT a.appointment_id, a.patient_id, a.status, a.appointment_date, a.appointment_time, 
               a.appointment_number, p.first_name, p.last_name, 
               COALESCE(p.email, pa.email) as patient_email,
               s.service_name, u.full_name as dentist_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN patient_accounts pa ON p.patient_id = pa.patient_id
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

    logActivity('appointment_status_changed', "Appointment {$apt['appointment_number']} → " . $new_status, $changed_by_user_id, null);

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
 * Reschedule an appointment (change date and/or time).
 * Only for pending/confirmed appointments. Sends reschedule confirmation email.
 */
function rescheduleAppointment($appointment_id, $new_date, $new_time, $rescheduled_by_user_id = null) {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT a.*, p.first_name, p.last_name, p.email as patient_email,
               s.service_name, u.full_name as dentist_name, a.branch_id
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

    if (!$apt || !in_array($apt['status'], ['pending', 'confirmed'], true)) {
        return false;
    }

    // Normalize time to H:i:s (e.g. "09:00" -> "09:00:00")
    $new_time_str = is_string($new_time) ? $new_time : date('H:i:s', strtotime($new_time));
    if (strlen($new_time_str) <= 5) {
        $new_time_str .= ':00';
    }
    $new_time_str = substr($new_time_str, 0, 8);

    // Validate new slot is available
    $slots = getAvailableTimeSlots($new_date, $apt['dentist_id'], null, $apt['branch_id'], $appointment_id);
    $valid_times = array_map(function ($s) {
        $t = $s['slot_time'];
        return strlen($t) <= 5 ? $t . ':00' : substr($t, 0, 8);
    }, $slots);
    if (!in_array($new_time_str, $valid_times)) {
        return false;
    }

    // Set status back to pending so it can be changed again if needed; email still sends below
    $stmt = $db->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'pending', updated_at = NOW() WHERE appointment_id = ?");
    $stmt->bind_param("ssi", $new_date, $new_time_str, $appointment_id);
    if (!$stmt->execute()) {
        return false;
    }

    logActivity('appointment_rescheduled', "Appointment {$apt['appointment_number']} → {$new_date} {$new_time_str}", $rescheduled_by_user_id, $apt['patient_id']);

    if (!empty($apt['patient_email'])) {
        require_once __DIR__ . '/mailer.php';
        $mailer = getMailer();
        $patient_name = trim($apt['first_name'] . ' ' . $apt['last_name']);
        $appointment_data = [
            'appointment_number' => $apt['appointment_number'],
            'service' => $apt['service_name'],
            'date' => formatDate($new_date),
            'time' => formatTime($new_time_str),
            'dentist' => $apt['dentist_name'] ?? 'TBD'
        ];
        try {
            $mailer->sendAppointmentRescheduled($apt['patient_email'], $patient_name, $appointment_data);
        } catch (Exception $e) {
            error_log("Failed to send reschedule email: " . $e->getMessage());
        }
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

/**
 * Format bytes into human-readable size.
 * - &lt; 1 MB  → KB
 * - 1–999 MB → MB
 * - ≥ 1 GB   → GB
 */
function formatBytes($bytes) {
    $bytes = (float)$bytes;
    if ($bytes <= 0) {
        return '0 KB';
    }

    $kb = $bytes / 1024;
    $mb = $kb / 1024;
    $gb = $mb / 1024;

    if ($mb < 1) {
        return number_format(max($kb, 1), 0) . ' KB';
    }

    if ($gb < 1) {
        return number_format($mb, 1) . ' MB';
    }

    return number_format($gb, 2) . ' GB';
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

/**
 * Validate allowed email provider.
 * Only allow common providers we explicitly support (e.g. Gmail, Yahoo).
 */
function validateAllowedEmailDomain($email) {
    if (!validateEmail($email)) {
        return false;
    }
    $atPos = strrpos($email, '@');
    if ($atPos === false) {
        return false;
    }
    $domain = strtolower(substr($email, $atPos + 1));
    $allowed = [
        'gmail.com',
        'yahoo.com',
        'yahoo.com.ph'
    ];
    return in_array($domain, $allowed, true);
}

// Validate phone
function validatePhone($phone) {
    return preg_match('/^[0-9+\-\s()]+$/', $phone);
}

/**
 * Validate password strength.
 * Rules:
 * - 8 to 12 characters
 * - At least 1 uppercase letter
 * - At least 1 digit
 * - At least 1 special character
 */
function validatePasswordStrength($password) {
    if (!is_string($password)) return false;
    $len = strlen($password);
    if ($len < 8 || $len > 12) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    if (!preg_match('/[\W_]/', $password)) return false;
    return true;
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
 * Check if patient already has an upcoming appointment with status pending or confirmed.
 * Upcoming = appointment_date in the future, or today with time in the future.
 */
function hasUpcomingAppointment($patient_id) {
    $db = getDB();
    $sql = "
        SELECT 1
        FROM appointments
        WHERE patient_id = ?
          AND status IN ('pending', 'confirmed')
          AND (
                appointment_date > CURRENT_DATE
             OR (appointment_date = CURRENT_DATE AND appointment_time >= CURRENT_TIME)
          )
        LIMIT 1
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->num_rows > 0;
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

