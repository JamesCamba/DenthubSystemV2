<?php
/**
 * Denthub Dental Clinic - Dentist Calendar
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireRole('dentist');

$db = getDB();

// Load branches for selection
$branches = [];
$res = $db->query("SELECT branch_id, branch_name FROM branches WHERE is_active = TRUE ORDER BY branch_name");
while ($row = $res->fetch_assoc()) {
    $branches[] = $row;
}

// Determine initial branch from dentist profile or first active branch
$stmt = $db->prepare("SELECT branch_id FROM dentists WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$dent = $stmt->get_result()->fetch_assoc();
$branchId = $dent ? (int)$dent['branch_id'] : (count($branches) ? (int)$branches[0]['branch_id'] : 0);

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - <?php echo APP_NAME; ?></title>
    <?php $asset_base = '../'; require __DIR__ . '/../includes/head-assets.php'; ?>
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
    <?php require_once 'navbar.php'; ?>

    <main class="denthub-main">
        <div class="container-fluid py-4">
            <h1 class="denthub-page-title mb-3"><i class="bi bi-calendar3 me-2"></i> Calendar</h1>

            <div class="row g-3 mb-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Branch</label>
                    <select class="form-select" id="calendarBranch">
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo (int)$b['branch_id']; ?>" <?php echo $branchId === (int)$b['branch_id'] ? 'selected' : ''; ?>>
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
                    <p class="text-muted mb-0">Click a date in the calendar to see your available slots.</p>
                </div>
            </div>
        </div>
    </main>

    <script>
        function getBranchId() {
            const sel = document.getElementById('calendarBranch');
            return sel ? parseInt(sel.value || '0', 10) || 0 : 0;
        }
        let currentYear = <?php echo (int)$year; ?>;
        let currentMonth = <?php echo (int)$month; ?>;
        const minYear = 2026;
        const minMonth = 2;
        (function clampInitialMonth() {
            if (currentYear < minYear || (currentYear === minYear && currentMonth < minMonth)) {
                currentYear = minYear;
                currentMonth = minMonth;
            }
            const today = new Date();
            const maxDate = new Date(today.getFullYear(), today.getMonth() + 6, 1);
            const maxYear = maxDate.getFullYear();
            const maxMonth = maxDate.getMonth() + 1;
            if (currentYear > maxYear || (currentYear === maxYear && currentMonth > maxMonth)) {
                currentYear = maxYear;
                currentMonth = maxMonth;
            }
        })();

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
                        day++;
                    }
                    tr.appendChild(td);
                }
                body.appendChild(tr);
                if (day > daysInMonth) break;
            }
        }

        function loadDayDetails(dateStr) {
            const branchId = getBranchId();
            if (!branchId) return;
            const container = document.getElementById('dayDetails');
            container.innerHTML = '<p class="text-muted mb-0">Loading...</p>';
            fetch(`../api/get-day-slots.php?role=dentist&branch_id=${branchId}&date=${dateStr}`)
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
                    let html = `<h6 class="fw-semibold mb-3">Your available slots for ${dateStr}</h6>`;
                    data.dentists.forEach(d => {
                        html += `<div class="mb-2"><strong>${d.name}</strong><br>`;
                        html += d.slots.map(s => `<span class="badge bg-light text-dark me-1 mb-1">${s.display}</span>`).join(' ');
                        html += '</div>';
                    });
                    container.innerHTML = html;
                })
                .catch(() => {
                    container.innerHTML = '<p class="text-muted mb-0">Unable to load slots.</p>';
                });
        }

        document.getElementById('calendarBranch').addEventListener('change', () => {
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
            const maxDate = new Date(today.getFullYear(), today.getMonth() + 6, 1);
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

        loadCalendar();
    </script>
</body>
</html>

