<?php
/**
 * Appointment booking, overlap, and slot validation.
 * Times and "today" are always evaluated in TIMEZONE (Asia/Manila).
 */

function appTimezone() {
    return new DateTimeZone(defined('TIMEZONE') ? TIMEZONE : 'Asia/Manila');
}

function appNowImmutable() {
    return new DateTimeImmutable('now', appTimezone());
}

function appTodayImmutable() {
    return appNowImmutable()->setTime(0, 0, 0);
}

function bookingWindowMonths() {
    return defined('BOOKING_WINDOW_MONTHS') ? (int)BOOKING_WINDOW_MONTHS : 3;
}

function defaultServiceDuration() {
    return defined('BOOKING_DEFAULT_DURATION') ? (int)BOOKING_DEFAULT_DURATION : 30;
}

function normalizeTimeString($time) {
    if ($time === null || $time === '') {
        return '';
    }
    $t = trim((string)$time);
    if (preg_match('/^\d{2}:\d{2}$/', $t)) {
        return $t . ':00';
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}/', $t)) {
        return substr($t, 0, 8);
    }
    $ts = strtotime($t);
    return $ts ? date('H:i:s', $ts) : '';
}

function timeToMinutes($time) {
    $t = normalizeTimeString($time);
    if ($t === '') {
        return null;
    }
    [$h, $m] = array_map('intval', explode(':', $t));
    return ($h * 60) + $m;
}

function minutesToTime($minutes) {
    $minutes = max(0, (int)$minutes);
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return sprintf('%02d:%02d:00', $h, $m);
}

function getBookingDateBounds($allowToday = false) {
    $today = appTodayImmutable();
    $min = $allowToday ? $today : $today->modify('+1 day');
    $max = $today->modify('+' . bookingWindowMonths() . ' months');
    return [$min, $max];
}

function bookingMinDateHtml($allowToday = false) {
    return getBookingDateBounds($allowToday)[0]->format('Y-m-d');
}

function bookingMaxDateHtml($allowToday = false) {
    return getBookingDateBounds($allowToday)[1]->format('Y-m-d');
}

function ensureAppointmentSlotIndex() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $db = getDB();
    try {
        $db->query("ALTER TABLE appointments ADD COLUMN IF NOT EXISTS duration_minutes INTEGER DEFAULT 30");
    } catch (Throwable $e) {
        error_log('ensureAppointmentSlotIndex duration: ' . $e->getMessage());
    }
    try {
        $db->query("
            CREATE UNIQUE INDEX IF NOT EXISTS appointments_unique_active_slot
            ON appointments (dentist_id, appointment_date, appointment_time)
            WHERE status IN ('pending', 'confirmed') AND dentist_id IS NOT NULL
        ");
    } catch (Throwable $e) {
        error_log('ensureAppointmentSlotIndex unique: ' . $e->getMessage());
    }
    try {
        $db->query("CREATE EXTENSION IF NOT EXISTS btree_gist");
        $db->query("ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_no_overlap");
        $db->query("
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_no_overlap
            EXCLUDE USING gist (
                dentist_id WITH =,
                appointment_date WITH =,
                tsrange(
                    ('2000-01-01'::date + appointment_time),
                    ('2000-01-01'::date + appointment_time + make_interval(mins => GREATEST(COALESCE(duration_minutes, 30), 1))),
                    '[)'
                ) WITH &&
            )
            WHERE (status IN ('pending', 'confirmed') AND dentist_id IS NOT NULL)
        ");
    } catch (Throwable $e) {
        error_log('ensureAppointmentSlotIndex overlap constraint: ' . $e->getMessage());
    }
}

function parseBookingDate($date) {
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, appTimezone());
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        return null;
    }
    return $dt->setTime(0, 0, 0);
}

function isDateInBookingWindow($date, $allowToday = false) {
    $appt = parseBookingDate($date);
    if (!$appt) {
        return false;
    }
    [$min, $max] = getBookingDateBounds($allowToday);
    return $appt >= $min && $appt <= $max;
}

function getServiceDurationMinutes($service_id) {
    $service_id = (int)$service_id;
    if ($service_id <= 0) {
        return defaultServiceDuration();
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT duration_minutes FROM services WHERE service_id = ? AND is_active = TRUE");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $mins = (int)($row['duration_minutes'] ?? 0);
    return $mins > 0 ? $mins : defaultServiceDuration();
}

function dentistOffersService($dentist_id, $service_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT 1 FROM dentist_service_mastery WHERE dentist_id = ? AND service_id = ? LIMIT 1");
    $stmt->bind_param("ii", $dentist_id, $service_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function getActiveDentist($dentist_id) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT d.dentist_id, d.branch_id, d.is_active
        FROM dentists d
        JOIN users u ON u.user_id = d.user_id
        WHERE d.dentist_id = ? AND d.is_active = TRUE AND u.is_active = TRUE
        LIMIT 1
    ");
    $stmt->bind_param("i", $dentist_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getMonthlyScheduleForDate($dentist_id, $date) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT branch_id, start_time, end_time
        FROM doctor_monthly_schedules
        WHERE doctor_id = ? AND schedule_date = ?
        LIMIT 1
    ");
    $stmt->bind_param("is", $dentist_id, $date);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Dentist is at one branch per day: monthly override wins, otherwise home branch.
 */
function dentistWorksAtBranchOnDate($dentist_id, $branch_id, $date) {
    $dentist = getActiveDentist($dentist_id);
    if (!$dentist) {
        return false;
    }
    $monthly = getMonthlyScheduleForDate($dentist_id, $date);
    if ($monthly) {
        return (int)$monthly['branch_id'] === (int)$branch_id;
    }
    return (int)$dentist['branch_id'] === (int)$branch_id;
}

function rangesOverlap($startA, $endA, $startB, $endB) {
    return $startA < $endB && $startB < $endA;
}

function getExistingAppointmentsForDentistDate($dentist_id, $date, $exclude_appointment_id = null) {
    $db = getDB();
    $sql = "
        SELECT a.appointment_id, a.appointment_time, COALESCE(s.duration_minutes, ?) AS duration_minutes
        FROM appointments a
        JOIN services s ON s.service_id = a.service_id
        WHERE a.dentist_id = ?
          AND a.appointment_date = ?
          AND a.status IN ('pending', 'confirmed')
    ";
    $defaultDur = defaultServiceDuration();
    $params = [$defaultDur, $dentist_id, $date];
    $types = "iis";
    if ($exclude_appointment_id) {
        $sql .= " AND a.appointment_id != ?";
        $params[] = (int)$exclude_appointment_id;
        $types .= "i";
    }
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $start = timeToMinutes($row['appointment_time']);
        if ($start === null) {
            continue;
        }
        $dur = max((int)$row['duration_minutes'], defaultServiceDuration());
        $rows[] = [
            'appointment_id' => (int)$row['appointment_id'],
            'start' => $start,
            'end' => $start + $dur,
        ];
    }
    return $rows;
}

function slotOverlapsExisting($slotStartMin, $durationMin, $existing) {
    $slotEnd = $slotStartMin + $durationMin;
    foreach ($existing as $apt) {
        if (rangesOverlap($slotStartMin, $slotEnd, $apt['start'], $apt['end'])) {
            return true;
        }
    }
    return false;
}

function sqlAppointmentOverlaps($dentist_id, $date, $time, $durationMin, $exclude_appointment_id = null) {
    $db = getDB();
    $durationMin = max(1, (int)$durationMin);
    $defaultDur = defaultServiceDuration();
    $time = normalizeTimeString($time);
    $sql = "
        SELECT a.appointment_id
        FROM appointments a
        JOIN services s ON s.service_id = a.service_id
        WHERE a.dentist_id = ?
          AND a.appointment_date = ?
          AND a.status IN ('pending', 'confirmed')
          AND (
                a.appointment_time,
                a.appointment_time + (GREATEST(COALESCE(s.duration_minutes, ?), 1)::text || ' minutes')::interval
          ) OVERLAPS (
                CAST(? AS time),
                CAST(? AS time) + (?::text || ' minutes')::interval
          )
    ";
    $params = [(int)$dentist_id, $date, $defaultDur, $time, $time, $durationMin];
    $types = 'isissi';
    if ($exclude_appointment_id) {
        $sql .= " AND a.appointment_id <> ?";
        $params[] = (int)$exclude_appointment_id;
        $types .= 'i';
    }
    $sql .= " LIMIT 1";
    try {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_assoc();
    } catch (Throwable $e) {
        error_log('sqlAppointmentOverlaps: ' . $e->getMessage());
        return true;
    }
}

/**
 * Recompute working window without treating another branch's monthly day as weekly fallback.
 */
function getEffectiveDoctorWindow($date, $dentist_id, $branch_id) {
    if (!dentistWorksAtBranchOnDate($dentist_id, $branch_id, $date)) {
        return [null, null];
    }
    return getDoctorWorkingWindow($date, $dentist_id, $branch_id);
}

function isActiveTimeSlot($time) {
    $db = getDB();
    $norm = normalizeTimeString($time);
    $stmt = $db->prepare("SELECT 1 FROM time_slots WHERE slot_time = CAST(? AS TIME) AND is_active = TRUE LIMIT 1");
    $stmt->bind_param("s", $norm);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/**
 * @return array{ok:bool,error?:string}
 */
function validateBookableSlot($date, $time, $dentist_id, $service_id, $branch_id, $options = []) {
    ensureAppointmentSlotIndex();
    $allowToday = !empty($options['allow_today']);
    $excludeId = isset($options['exclude_appointment_id']) ? (int)$options['exclude_appointment_id'] : null;

    $dentist_id = (int)$dentist_id;
    $service_id = (int)$service_id;
    $branch_id = (int)$branch_id;
    $time = normalizeTimeString($time);

    if ($dentist_id <= 0) {
        return ['ok' => false, 'error' => 'Please select a dentist.'];
    }
    if ($service_id <= 0) {
        return ['ok' => false, 'error' => 'Please select a service.'];
    }
    if ($branch_id <= 0) {
        return ['ok' => false, 'error' => 'Please select a branch.'];
    }
    if ($time === '') {
        return ['ok' => false, 'error' => 'Please select a time.'];
    }
    if (!parseBookingDate($date)) {
        return ['ok' => false, 'error' => 'Invalid appointment date.'];
    }
    if (!isDateInBookingWindow($date, $allowToday)) {
        return ['ok' => false, 'error' => 'Selected date is outside the allowed booking window (tomorrow through 3 months).'];
    }
    if (isDateBlocked($date, $branch_id)) {
        return ['ok' => false, 'error' => 'Selected date is not available.'];
    }
    if (!getActiveDentist($dentist_id)) {
        return ['ok' => false, 'error' => 'Selected dentist is not available.'];
    }
    if (!dentistOffersService($dentist_id, $service_id)) {
        return ['ok' => false, 'error' => 'Selected dentist does not offer this service.'];
    }
    if (!dentistWorksAtBranchOnDate($dentist_id, $branch_id, $date)) {
        return ['ok' => false, 'error' => 'Selected dentist does not work at this branch on that date.'];
    }

    [$windowStart, $windowEnd] = getEffectiveDoctorWindow($date, $dentist_id, $branch_id);
    if ($windowStart === null || $windowEnd === null) {
        return ['ok' => false, 'error' => 'Selected dentist has no working hours on that date.'];
    }
    if (!isActiveTimeSlot($time)) {
        return ['ok' => false, 'error' => 'Selected time is not a valid clinic slot.'];
    }

    $startMin = timeToMinutes($time);
    $winStartMin = timeToMinutes($windowStart);
    $winEndMin = timeToMinutes($windowEnd);
    $duration = getServiceDurationMinutes($service_id);
    $endMin = $startMin + $duration;

    if ($startMin === null || $winStartMin === null || $winEndMin === null) {
        return ['ok' => false, 'error' => 'Invalid time values.'];
    }
    if ($startMin < $winStartMin || $startMin >= $winEndMin) {
        return ['ok' => false, 'error' => 'Selected time is outside the dentist\'s working hours.'];
    }
    if ($endMin > $winEndMin) {
        return ['ok' => false, 'error' => 'This service does not fit in the remaining time for that day.'];
    }

    if ($allowToday && $date === appTodayImmutable()->format('Y-m-d')) {
        $nowMin = (int)appNowImmutable()->format('G') * 60 + (int)appNowImmutable()->format('i');
        if ($startMin <= $nowMin) {
            return ['ok' => false, 'error' => 'Cannot book a time that has already passed.'];
        }
    }

    $existing = getExistingAppointmentsForDentistDate($dentist_id, $date, $excludeId);
    if (slotOverlapsExisting($startMin, $duration, $existing) || sqlAppointmentOverlaps($dentist_id, $date, $time, $duration, $excludeId)) {
        return ['ok' => false, 'error' => 'Selected time overlaps another appointment. Please choose another slot.'];
    }

    return ['ok' => true];
}

/**
 * Available start times for a dentist/date, filtered by service duration and overlap.
 */
function getActiveDentistsList($dentist_id = null) {
    $db = getDB();
    $sql = "
        SELECT d.dentist_id, d.branch_id, u.full_name, u.email, u.phone
        FROM dentists d
        JOIN users u ON u.user_id = d.user_id
        WHERE d.is_active = TRUE AND u.is_active = TRUE
    ";
    $params = [];
    $types = '';
    if ($dentist_id !== null) {
        $sql .= " AND d.dentist_id = ? ";
        $params[] = (int)$dentist_id;
        $types .= 'i';
    }
    $sql .= " ORDER BY u.full_name";
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function getDentistsWorkingAtBranchOnDate($branch_id, $date, $dentist_id = null, $service_id = null) {
    $out = [];
    foreach (getActiveDentistsList($dentist_id) as $dent) {
        $id = (int)$dent['dentist_id'];
        if ($service_id && !dentistOffersService($id, (int)$service_id)) {
            continue;
        }
        if (!dentistWorksAtBranchOnDate($id, $branch_id, $date)) {
            continue;
        }
        $out[] = $dent;
    }
    return $out;
}

function computeAvailableTimeSlots($date, $dentist_id = null, $service_id = null, $branch_id = 0, $exclude_appointment_id = null) {
    ensureAppointmentSlotIndex();
    $branch_id = (int)$branch_id;
    if ($branch_id <= 0 || $dentist_id === null || (int)$dentist_id <= 0) {
        return [];
    }
    if (!parseBookingDate($date)) {
        return [];
    }
    if (isDateBlocked($date, $branch_id)) {
        return [];
    }

    $duration = $service_id ? getServiceDurationMinutes($service_id) : defaultServiceDuration();
    $db = getDB();

    [$windowStart, $windowEnd] = getEffectiveDoctorWindow($date, (int)$dentist_id, $branch_id);
    if ($windowStart === null || $windowEnd === null) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT ts.slot_id, ts.slot_time
        FROM time_slots ts
        WHERE ts.is_active = TRUE
          AND ts.slot_time >= CAST(? AS TIME)
          AND ts.slot_time < CAST(? AS TIME)
        ORDER BY ts.slot_time
    ");
    $stmt->bind_param("ss", $windowStart, $windowEnd);
    $stmt->execute();
    $result = $stmt->get_result();

    $existing = getExistingAppointmentsForDentistDate((int)$dentist_id, $date, $exclude_appointment_id);
    $winEndMin = timeToMinutes($windowEnd);
    $todayStr = appTodayImmutable()->format('Y-m-d');
    $nowMin = (int)appNowImmutable()->format('G') * 60 + (int)appNowImmutable()->format('i');

    $available = [];
    while ($row = $result->fetch_assoc()) {
        $startMin = timeToMinutes($row['slot_time']);
        if ($startMin === null || $winEndMin === null) {
            continue;
        }
        if ($startMin + $duration > $winEndMin) {
            continue;
        }
        if ($date === $todayStr && $startMin <= $nowMin) {
            continue;
        }
        if (slotOverlapsExisting($startMin, $duration, $existing)) {
            continue;
        }
        $available[] = $row;
    }
    return $available;
}

/**
 * Create appointment with row lock + re-validation inside a transaction.
 * @return array{ok:bool,error?:string,appointment_id?:int,appointment_number?:string}
 */
function createAppointmentRecord($patient_id, $dentist_id, $service_id, $branch_id, $date, $time, $reason, $status = 'pending', $created_by = null, $options = []) {
    $db = getDB();
    $time = normalizeTimeString($time);
    $started = false;
    ensureAppointmentSlotIndex();

    try {
        $db->begin_transaction();
        $started = true;

        $db->query("SELECT pg_advisory_xact_lock(87234101)");
        $lockStmt = $db->prepare("SELECT dentist_id FROM dentists WHERE dentist_id = ? FOR UPDATE");
        $lockStmt->bind_param("i", $dentist_id);
        $lockStmt->execute();
        if (!$lockStmt->get_result()->fetch_assoc()) {
            $db->rollback();
            return ['ok' => false, 'error' => 'Selected dentist is not available.'];
        }
        $lockAppts = $db->prepare("
            SELECT appointment_id
            FROM appointments
            WHERE dentist_id = ? AND appointment_date = ? AND status IN ('pending', 'confirmed')
            FOR UPDATE
        ");
        $lockAppts->bind_param("is", $dentist_id, $date);
        $lockAppts->execute();
        $lockAppts->get_result();

        $check = validateBookableSlot($date, $time, $dentist_id, $service_id, $branch_id, $options);
        if (!$check['ok']) {
            $db->rollback();
            return $check;
        }

        $appointment_number = generateAppointmentNumber();
        $duration = getServiceDurationMinutes($service_id);
        if ($created_by !== null) {
            $created_by = (int)$created_by;
            $stmt = $db->prepare("
                INSERT INTO appointments (
                    appointment_number, patient_id, dentist_id, service_id, branch_id,
                    appointment_date, appointment_time, reason_for_visit, status, created_by, duration_minutes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "siiiissssii",
                $appointment_number,
                $patient_id,
                $dentist_id,
                $service_id,
                $branch_id,
                $date,
                $time,
                $reason,
                $status,
                $created_by,
                $duration
            );
        } else {
            $stmt = $db->prepare("
                INSERT INTO appointments (
                    appointment_number, patient_id, dentist_id, service_id, branch_id,
                    appointment_date, appointment_time, reason_for_visit, status, duration_minutes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "siiiissssi",
                $appointment_number,
                $patient_id,
                $dentist_id,
                $service_id,
                $branch_id,
                $date,
                $time,
                $reason,
                $status,
                $duration
            );
        }
        $stmt->execute();
        $appointment_id = (int)$db->insert_id;
        $db->commit();

        return [
            'ok' => true,
            'appointment_id' => $appointment_id,
            'appointment_number' => $appointment_number,
        ];
    } catch (Throwable $e) {
        if ($started) {
            try { $db->rollback(); } catch (Throwable $ignored) {}
        }
        error_log('createAppointmentRecord: ' . $e->getMessage());
        $msg = $e->getMessage();
        if (stripos($msg, 'unique') !== false || stripos($msg, 'overlap') !== false || stripos($msg, 'exclude') !== false) {
            return ['ok' => false, 'error' => 'Selected time overlaps another appointment. Please choose another slot.'];
        }
        return ['ok' => false, 'error' => 'We could not complete your booking. Please try again.'];
    }
}

function getCalendarAvailabilityDays($year, $month, $branch_id, $dentist_id = null, $allowToday = false) {
    $year = (int)$year;
    $month = (int)$month;
    $branch_id = (int)$branch_id;
    [$minDate, $maxDate] = getBookingDateBounds($allowToday);
    $minStr = $minDate->format('Y-m-d');
    $maxStr = $maxDate->format('Y-m-d');
    $todayStr = appTodayImmutable()->format('Y-m-d');
    $daysInMonth = (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), appTimezone()))->format('t');

    $days = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $blocked = isDateBlocked($dateStr, $branch_id);
        $past = $dateStr < $todayStr;
        $outside = $dateStr < $minStr || $dateStr > $maxStr;
        $slots = 0;
        if (!$blocked && !$past && !$outside) {
            $slots = getAvailableSlotCountForDateAndBranch($dateStr, $branch_id, $dentist_id);
        }
        $days[] = [
            'date' => $dateStr,
            'slots' => $slots,
            'blocked' => $blocked,
            'reason' => $blocked ? getBlockReason($dateStr, $branch_id) : '',
            'past' => $past,
            'outside_window' => $outside,
            'selectable' => !$blocked && !$past && !$outside && $slots > 0,
        ];
    }
    return [
        'year' => $year,
        'month' => $month,
        'min_date' => $minStr,
        'max_date' => $maxStr,
        'days' => $days,
    ];
}

function bookingDateInputAttributes($allowToday = false) {
    return 'min="' . htmlspecialchars(bookingMinDateHtml($allowToday), ENT_QUOTES, 'UTF-8')
        . '" max="' . htmlspecialchars(bookingMaxDateHtml($allowToday), ENT_QUOTES, 'UTF-8') . '"';
}

function clinicLetterhead() {
    $db = getDB();
    $row = null;
    try {
        $res = $db->query("SELECT branch_name, address, phone, email FROM branches WHERE is_active = TRUE ORDER BY branch_id LIMIT 1");
        $row = $res ? $res->fetch_assoc() : null;
    } catch (Throwable $e) {
        $row = null;
    }
    return [
        'name' => APP_NAME,
        'branch' => $row['branch_name'] ?? '',
        'address' => $row['address'] ?? '',
        'phone' => $row['phone'] ?? '',
        'email' => $row['email'] ?? '',
        'generated' => formatDateTime(appNowImmutable()->format('Y-m-d H:i:s')),
    ];
}
