window.Denthub = window.Denthub || {};

Denthub.todayParts = function () {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).formatToParts(new Date());
    const map = {};
    parts.forEach(p => { map[p.type] = p.value; });
    return { y: parseInt(map.year, 10), m: parseInt(map.month, 10), d: parseInt(map.day, 10) };
};

Denthub.todayStr = function () {
    const t = Denthub.todayParts();
    return t.y + '-' + String(t.m).padStart(2, '0') + '-' + String(t.d).padStart(2, '0');
};

Denthub.addMonths = function (year, month, delta) {
    const d = new Date(year, month - 1 + delta, 1);
    return { y: d.getFullYear(), m: d.getMonth() + 1 };
};

Denthub.monthInRange = function (year, month, minDate, maxDate) {
    const ym = year * 100 + month;
    if (minDate) {
        const parts = String(minDate).split('-');
        if (ym < (parseInt(parts[0], 10) * 100 + parseInt(parts[1], 10))) return false;
    }
    if (maxDate) {
        const parts = String(maxDate).split('-');
        if (ym > (parseInt(parts[0], 10) * 100 + parseInt(parts[1], 10))) return false;
    }
    return true;
};

Denthub.chartColors = function () {
    const css = getComputedStyle(document.documentElement);
    const text = (css.getPropertyValue('--denthub-text') || '#0f172a').trim();
    const muted = (css.getPropertyValue('--denthub-muted') || '#64748b').trim();
    const primary = (css.getPropertyValue('--denthub-primary') || '#004AAD').trim();
    const grid = (css.getPropertyValue('--denthub-border') || '#e2e8f0').trim();
    return { text, muted, primary, grid };
};

Denthub.applyChartDefaults = function () {
    if (!window.Chart) return;
    const c = Denthub.chartColors();
    Chart.defaults.color = c.text;
    Chart.defaults.borderColor = c.grid;
    Chart.defaults.font.family = "Inter, 'Segoe UI', system-ui, sans-serif";
};

Denthub.bindRescheduleSlots = function (opts) {
    const dateEl = document.getElementById(opts.dateId || 'reschedule_date');
    const timeEl = document.getElementById(opts.timeId || 'reschedule_time');
    if (!dateEl || !timeEl) return;
    const load = async function () {
        const date = dateEl.value;
        timeEl.innerHTML = '<option value="">Loading...</option>';
        if (!date) {
            timeEl.innerHTML = '<option value="">Select time...</option>';
            return;
        }
        const params = new URLSearchParams({
            date: date,
            branch_id: String(opts.branchId || ''),
            exclude_appointment_id: String(opts.appointmentId || '')
        });
        if (opts.dentistId) params.set('dentist_id', String(opts.dentistId));
        if (opts.serviceId) params.set('service_id', String(opts.serviceId));
        try {
            const res = await fetch('../api/get-available-slots.php?' + params.toString());
            const data = await res.json();
            timeEl.innerHTML = '<option value="">Select time...</option>';
            if (data.success && data.slots && data.slots.length) {
                data.slots.forEach(function (s) {
                    const opt = document.createElement('option');
                    opt.value = s.slot_time;
                    opt.textContent = s.display_time;
                    timeEl.appendChild(opt);
                });
            } else {
                const opt = document.createElement('option');
                opt.value = '';
                opt.disabled = true;
                opt.textContent = data.message || 'No available slots';
                timeEl.appendChild(opt);
            }
        } catch (e) {
            timeEl.innerHTML = '<option value="">Error loading slots</option>';
        }
    };
    dateEl.addEventListener('change', load);
};

Denthub.csrfToken = function () {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
};

document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
    if (form.querySelector('input[name="csrf_token"]')) return;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'csrf_token';
    input.value = Denthub.csrfToken();
    form.appendChild(input);
}, true);

(function () {
    const originalFetch = window.fetch;
    window.fetch = function (input, init) {
        init = init || {};
        const method = String(init.method || 'GET').toUpperCase();
        if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1) {
            const token = Denthub.csrfToken();
            const headers = new Headers(init.headers || {});
            if (token && !headers.has('X-CSRF-Token')) {
                headers.set('X-CSRF-Token', token);
            }
            init.headers = headers;
            if (token) {
                if (typeof URLSearchParams !== 'undefined' && init.body instanceof URLSearchParams && !init.body.has('csrf_token')) {
                    init.body.append('csrf_token', token);
                } else if (typeof FormData !== 'undefined' && init.body instanceof FormData && !init.body.has('csrf_token')) {
                    init.body.append('csrf_token', token);
                } else if (typeof init.body === 'string' && init.body.indexOf('csrf_token=') === -1) {
                    init.body += (init.body.length ? '&' : '') + 'csrf_token=' + encodeURIComponent(token);
                }
            }
        }
        return originalFetch.call(this, input, init);
    };
})();
