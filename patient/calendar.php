<?php
/**
 * Denthub Dental Clinic - Patient Calendar
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requirePatientLogin();

$db = getDB();

$branches = [];
$res = $db->query("SELECT branch_id, branch_name FROM branches WHERE is_active = TRUE ORDER BY branch_name");
while ($row = $res->fetch_assoc()) {
    $branches[] = $row;
}

$currentBranchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : (count($branches) ? (int)$branches[0]['branch_id'] : 0);

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - <?php echo APP_NAME; ?></title>
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
        .cal-day-green { background-color: rgba(25,135,84,0.18) !important; }
        .cal-day-orange { background-color: rgba(255,193,7,0.25) !important; }
        .cal-day-red { background-color: rgba(220,53,69,0.22) !important; }
        .cal-day-blocked { background-color: rgba(108,117,125,0.25) !important; color: #6c757d !important; }
    </style>
</head>
<body>
    <?php require_once '../includes/patient-sidebar.php'; ?>

    <main class="denthub-main">
        <div class="container py-5">
            <h2 class="denthub-page-title mb-3"><i class="bi bi-calendar3 me-2"></i>Calendar</h2>

            <div class="row g-3 mb-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Branch</label>
                    <select class="form-select" id="calendarBranch">
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo (int)$b['branch_id']; ?>" <?php echo $currentBranchId === (int)$b['branch_id'] ? 'selected' : ''; ?>>
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
                <div class="col-md-4 text-md-end">
                    <div class="small">
                        <span class="badge bg-success me-1">&nbsp;</span> ≥10 slots
                        <span class="badge bg-warning text-dark ms-2 me-1">&nbsp;</span> 3–9 slots
                        <span class="badge bg-danger ms-2 me-1">&nbsp;</span> ≤2 slots
                        <span class="badge bg-secondary ms-2 me-1">&nbsp;</span> Blocked
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 text-center align-middle" id="calendarTable">
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

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock me-2"></i>Day Details</h5>
                </div>
                <div class="card-body" id="dayDetails">
                    <p class="text-muted mb-0">Click a date in the calendar to see available slots grouped by dentist.</p>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentYear = <?php echo (int)$year; ?>;
        let currentMonth = <?php echo (int)$month; ?>;

        function loadCalendar() {
            const branchId = document.getElementById('calendarBranch').value || 0;
            if (!branchId) return;
            fetch(`../api/get-calendar-availability.php?role=patient&branch_id=${branchId}&year=${currentYear}&month=${currentMonth}`)
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

            const today = new Date();
            const todayStr = today.toISOString().slice(0, 10);

            let day = 1;
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
                        const isPastDate = dateStr <= todayStr;
                        let cls = '';
                        if (isPastDate) {
                            cls = 'cal-day-blocked';
                            td.classList.add('text-muted');
                            td.dataset.date = '';
                            td.innerHTML = `<div class="fw-semibold">${day}</div><small>Past</small>`;
                        } else {
                            if (info.blocked) {
                                cls = 'cal-day-blocked';
                            } else if (info.slots >= 10) {
                                cls = 'cal-day-green';
                            } else if (info.slots >= 3) {
                                cls = 'cal-day-orange';
                            } else {
                                cls = 'cal-day-red';
                            }
                            td.classList.add(cls);
                            td.dataset.date = dateStr;
                            td.innerHTML = `<div class="fw-semibold">${day}</div><small>${info.blocked ? 'Blocked' : (info.slots + ' slots')}</small>`;
                            if (info.blocked && info.reason) {
                                td.title = info.reason;
                            }
                            td.addEventListener('click', () => loadDayDetails(dateStr));
                        }
                        day++;
                    }
                    tr.appendChild(td);
                }
                body.appendChild(tr);
                if (day > daysInMonth) break;
            }
        }

        function loadDayDetails(dateStr) {
            const branchId = document.getElementById('calendarBranch').value || 0;
            if (!branchId) return;
            const container = document.getElementById('dayDetails');
            container.innerHTML = '<p class="text-muted mb-0">Loading...</p>';
            fetch(`../api/get-day-slots.php?role=patient&branch_id=${branchId}&date=${dateStr}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        container.innerHTML = '<p class="text-muted mb-0">Unable to load slots.</p>';
                        return;
                    }
                    if (data.blocked) {
                        container.innerHTML = `<p class="text-muted mb-0">This date is blocked. ${data.reason ? 'Reason: ' + data.reason : ''}</p>`;
                        return;
                    }
                    if (!data.dentists.length) {
                        container.innerHTML = '<p class="text-muted mb-0">No available slots for this date.</p>';
                        return;
                    }
                    let html = `<h6 class="fw-semibold mb-3">Available slots for ${dateStr}</h6>`;
                    data.dentists.forEach(d => {
                        html += `<div class="mb-2"><strong>${d.name}</strong><br>`;
                        html += d.slots.map(s => {
                            const url = `../book-appointment.php?branch_id=${encodeURIComponent(branchId)}&date=${encodeURIComponent(dateStr)}`;
                            return `<span class="badge bg-light text-dark me-1 mb-1">${s.display}</span>`;
                        }).join(' ');
                        html += `<div class="mt-1"><a href="../book-appointment.php?branch_id=${encodeURIComponent(branchId)}&date=${encodeURIComponent(dateStr)}" class="btn btn-sm btn-primary mt-1">Book on ${dateStr}</a></div>`;
                        html += '</div>';
                    });
                    container.innerHTML = html;
                })
                .catch(() => {
                    container.innerHTML = '<p class="text-muted mb-0">Unable to load slots.</p>';
                });
        }

        document.getElementById('calendarBranch').addEventListener('change', () => loadCalendar());
        document.getElementById('prevMonthBtn').addEventListener('click', () => {
            currentMonth--;
            if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            }
            loadCalendar();
        });
        document.getElementById('nextMonthBtn').addEventListener('click', () => {
            currentMonth++;
            if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            }
            loadCalendar();
        });

        loadCalendar();
    </script>
</body>
</html>

