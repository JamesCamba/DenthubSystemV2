<?php
/**
 * Denthub Dental Clinic - Dentist Schedule Manager (Monthly)
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireRole('dentist');

$db = getDB();

// Load branches
$branches = [];
$res = $db->query("SELECT branch_id, branch_name FROM branches WHERE is_active = TRUE ORDER BY branch_name");
while ($row = $res->fetch_assoc()) {
    $branches[] = $row;
}

// Dentist info (for display)
$stmt = $db->prepare("SELECT d.dentist_id, d.branch_id, u.full_name FROM dentists d JOIN users u ON d.user_id = u.user_id WHERE d.user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$dentist = $stmt->get_result()->fetch_assoc();
if (!$dentist) {
    die('Dentist record not found.');
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php echo denthubFaviconTags(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Manager - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .denthub-calendar-day {
            cursor: pointer;
            min-height: 80px;
        }
        .denthub-calendar-day small {
            display: block;
            font-size: 0.75rem;
        }
        .cal-day-has-schedule { background-color: rgba(25,135,84,0.18) !important; }
        .cal-day-no-schedule { background-color: rgba(255,193,7,0.18) !important; }
        .cal-day-blocked { background-color: rgba(108,117,125,0.25) !important; color: #6c757d !important; }
    </style>
</head>
<body>
    <?php require_once 'navbar.php'; ?>

    <main class="denthub-main">
        <div class="container-fluid py-4">
            <h1 class="denthub-page-title mb-3"><i class="bi bi-calendar-range me-2"></i> Schedule Manager</h1>

            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Configure your working hours for specific dates. If you do not set a custom schedule for a date,
                the system will use your weekly schedule (if any) or the clinic default hours (10:00–18:00) within clinic operating hours.
            </div>

            <div class="row g-3 mb-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Branch for selected date</label>
                    <select class="form-select" id="scheduleBranch">
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo (int)$b['branch_id']; ?>" <?php echo ((int)$b['branch_id'] === (int)$dentist['branch_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Month</label>
                    <div class="d-flex align-items-center">
                        <button class="btn btn-outline-secondary me-2" id="prevMonthBtn"><i class="bi bi-chevron-left"></i></button>
                        <div id="monthLabel" class="fw-semibold"></div>
                        <button class="btn btn-outline-secondary ms-2" id="nextMonthBtn"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0 text-center align-middle">
                                    <thead>
                                        <tr>
                                            <th>Sun</th>
                                            <th>Mon</th>
                                            <th>Tue</th>
                                            <th>Wed</th>
                                            <th>Thu</th>
                                            <th>Fri</th>
                                            <th>Sat</th>
                                        </tr>
                                    </thead>
                                    <tbody id="calendarBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-clock me-2"></i>Day Configuration</h5>
                        </div>
                        <div class="card-body">
                            <form id="dayForm">
                                <div class="mb-3">
                                    <label class="form-label">Selected Date</label>
                                    <input type="text" class="form-control" id="selectedDateDisplay" disabled>
                                    <input type="hidden" id="selectedDate" name="date">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Branch</label>
                                    <select class="form-select" id="formBranch">
                                        <?php foreach ($branches as $b): ?>
                                            <option value="<?php echo (int)$b['branch_id']; ?>">
                                                <?php echo htmlspecialchars($b['branch_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Start Time</label>
                                    <select class="form-select" id="formStart">
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">End Time</label>
                                    <select class="form-select" id="formEnd">
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <div class="form-text">
                                        Times are restricted by clinic operating hours. You cannot set schedules for past dates.
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Save Schedule for Date</button>
                                </div>
                                <div id="dayFormMessage" class="mt-3 small"></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentYear = <?php echo (int)$year; ?>;
        let currentMonth = <?php echo (int)$month; ?>;
        const minYear = 2026;
        const minMonth = 2;
        (function clampInitialMonth() {
            if (currentYear < minYear || (currentYear === minYear && currentMonth < minMonth)) {
                currentYear = minYear;
                currentMonth = minMonth;
            }
        })();

        function getBranchId() {
            const sel = document.getElementById('scheduleBranch');
            return sel ? parseInt(sel.value || '0', 10) || 0 : 0;
        }

        function buildTimeOptions() {
            const startSel = document.getElementById('formStart');
            const endSel = document.getElementById('formEnd');
            startSel.innerHTML = '';
            endSel.innerHTML = '';
            // 10:00 to 18:00 in hourly increments
            for (let h = 10; h <= 18; h++) {
                const hh = String(h).padStart(2, '0');
                const label = (h <= 12 ? h : h - 12) + ':00 ' + (h < 12 ? 'AM' : 'PM');
                const opt1 = document.createElement('option');
                opt1.value = `${hh}:00`;
                opt1.textContent = label;
                startSel.appendChild(opt1.cloneNode(true));
                if (h > 10) {
                    const opt2 = document.createElement('option');
                    opt2.value = `${hh}:00`;
                    opt2.textContent = label;
                    endSel.appendChild(opt2);
                }
            }
        }

        function loadCalendar() {
            const branchId = getBranchId();
            if (!branchId) return;
            fetch(`../api/get-calendar-availability.php?role=dentist&branch_id=${branchId}&year=${currentYear}&month=${currentMonth}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    renderCalendar(data);
                })
                .catch(() => {});
        }

        function renderCalendar(data) {
            const body = document.getElementById('calendarBody');
            body.innerHTML = '';
            const year = data.year;
            const month = data.month;
            const firstDay = new Date(year, month - 1, 1);
            const startWeekDay = firstDay.getDay();
            const daysInMonth = new Date(year, month, 0).getDate();
            const dayMap = {};
            data.days.forEach(d => { dayMap[d.date] = d; });

            const monthLabel = new Date(year, month - 1, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' });
            document.getElementById('monthLabel').textContent = monthLabel;

            let day = 1;
            const todayStr = new Date().toISOString().slice(0, 10);

            for (let week = 0; week < 6; week++) {
                const tr = document.createElement('tr');
                for (let dow = 0; dow < 7; dow++) {
                    const td = document.createElement('td');
                    td.className = 'denthub-calendar-day';
                    if (week === 0 && dow < startWeekDay || day > daysInMonth) {
                        td.innerHTML = '&nbsp;';
                    } else {
                        const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
                        const info = dayMap[dateStr] || { slots: 0, blocked: false };
                        let cls = '';
                        if (info.blocked) {
                            cls = 'cal-day-blocked';
                        } else if (info.slots > 0) {
                            cls = 'cal-day-has-schedule';
                        } else {
                            cls = 'cal-day-no-schedule';
                        }
                        td.classList.add(cls);
                        td.dataset.date = dateStr;
                        td.innerHTML = `<div class="fw-semibold">${day}</div><small>${info.blocked ? 'Blocked' : (info.slots + ' slots')}</small>`;
                        if (info.blocked && info.reason) {
                            td.title = info.reason;
                        }
                        if (dateStr >= todayStr && !info.blocked) {
                            td.addEventListener('click', () => selectDate(dateStr));
                        }
                        day++;
                    }
                    tr.appendChild(td);
                }
                body.appendChild(tr);
                if (day > daysInMonth) break;
            }
        }

        function selectDate(dateStr) {
            document.getElementById('selectedDate').value = dateStr;
            document.getElementById('selectedDateDisplay').value = dateStr;
            // Default branch in form to current selection
            document.getElementById('formBranch').value = String(getBranchId());
        }

        document.getElementById('scheduleBranch').addEventListener('change', () => {
            loadCalendar();
        });
        document.getElementById('prevMonthBtn').addEventListener('click', () => {
            let y = currentYear;
            let m = currentMonth - 1;
            if (m < 1) {
                m = 12;
                y--;
            }
            if (y < minYear || (y === minYear && m < minMonth)) {
                return;
            }
            currentYear = y;
            currentMonth = m;
            loadCalendar();
        });
        document.getElementById('nextMonthBtn').addEventListener('click', () => {
            const today = new Date();
            const maxDate = new Date(today.getFullYear(), today.getMonth() + 12, 1);
            const maxYear = maxDate.getFullYear();
            const maxMonth = maxDate.getMonth() + 1;
            let y = currentYear;
            let m = currentMonth + 1;
            if (m > 12) {
                m = 1;
                y++;
            }
            if (y > maxYear || (y === maxYear && m > maxMonth)) {
                return;
            }
            currentYear = y;
            currentMonth = m;
            loadCalendar();
        });

        document.getElementById('dayForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const msg = document.getElementById('dayFormMessage');
            msg.textContent = '';
            msg.className = 'mt-3 small';

            const date = document.getElementById('selectedDate').value;
            const branchId = document.getElementById('formBranch').value;
            const start = document.getElementById('formStart').value;
            const end = document.getElementById('formEnd').value;

            if (!date) {
                msg.classList.add('text-danger');
                msg.textContent = 'Please select a date from the calendar.';
                return;
            }

            const body = new URLSearchParams();
            body.set('date', date);
            body.set('branch_id', branchId);
            body.set('start_time', start);
            body.set('end_time', end);

            fetch('api-save-monthly-schedule.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    msg.classList.add('text-danger');
                    msg.textContent = data.message || 'Failed to save schedule.';
                } else {
                    msg.classList.add('text-success');
                    msg.textContent = 'Schedule saved.';
                    loadCalendar();
                }
            })
            .catch(() => {
                msg.classList.add('text-danger');
                msg.textContent = 'Failed to save schedule.';
            });
        });

        buildTimeOptions();
        loadCalendar();
    </script>
</body>
</html>

