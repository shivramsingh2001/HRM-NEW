{{--
    Hover card for the attendance calendars (/attendance and the Team member profile).
    Hover any day → its status, shift + timing, clock-in / clock-out with location,
    worked hours, late / early / overtime, regularized / marked-by, leave / holiday, tasks.

    @include('client.partials.calendar-day-hover')
    initCalendarDayHover(() => calendar, document.getElementById('calendarId'));

    Reads the day's events at hover time (extendedProps + extendedProps.details from
    App\Services\Attendance\CalendarDayDetails), so it follows month changes / AJAX reloads.
--}}
<style>
    #calDayHover {
        position: fixed;
        z-index: 2000;
        width: 330px;
        max-width: calc(100vw - 24px);
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .18);
        font-size: 12px;
        color: #1f2937;
        pointer-events: none;
        display: none;
        overflow: hidden;
    }
    /* Date on the left, status badge(s) on the right of the same line */
    #calDayHover .cdh-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 9px 12px; background: #f8fafc; border-bottom: 1px solid #eef2f7; }
    #calDayHover .cdh-date { font-weight: 700; font-size: 12.5px; color: #0f172a; white-space: nowrap; }
    #calDayHover .cdh-badges { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 4px; }
    #calDayHover .cdh-badge { padding: 2px 8px; border-radius: 4px; font-size: 10.5px; font-weight: 600; color: #fff; }
    #calDayHover .cdh-body { padding: 8px 12px 10px; }
    #calDayHover .cdh-row { display: flex; gap: 8px; padding: 4px 0; border-bottom: 1px dashed #f1f5f9; }
    #calDayHover .cdh-row:last-child { border-bottom: none; }
    #calDayHover .cdh-row i { color: #0D6EFD; font-size: 12px; margin-top: 2px; width: 14px; flex-shrink: 0; }
    #calDayHover .cdh-label { color: #64748b; min-width: 74px; }
    #calDayHover .cdh-value { font-weight: 600; color: #0f172a; word-break: break-word; }
    #calDayHover .cdh-sub { display: block; font-weight: 400; color: #64748b; font-size: 11px; margin-top: 1px; }
    #calDayHover .cdh-flag { display: inline-block; margin-left: 4px; padding: 0 6px; border-radius: 4px; font-size: 10px; font-weight: 600; }
    #calDayHover .cdh-flag.late { background: #fee2e2; color: #b91c1c; }
    #calDayHover .cdh-flag.ot { background: #dcfce7; color: #15803d; }
    #calDayHover .cdh-empty { color: #64748b; padding: 2px 0; }
</style>

<script>
    function initCalendarDayHover(getCalendar, containerEl) {
        if (!containerEl) return;
        let card = document.getElementById('calDayHover');
        if (!card) {
            card = document.createElement('div');
            card.id = 'calDayHover';
            document.body.appendChild(card);
        }

        const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const isDateTime = v => /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/.test(String(v || ''));
        // Raw "2026-10-21 09:05:00" → "09:05 AM" (+ "(+1 day)" when it is after the attendance date); "09:05 AM" stays.
        const time = (v, date) => {
            if (!v || v === '--:--') return '';
            if (!isDateTime(v)) return String(v);
            const m = moment(v), days = m.clone().startOf('day').diff(moment(date).startOf('day'), 'days');
            return m.format('hh:mm A') + (days > 0 ? ' (+' + days + ' day' + (days > 1 ? 's' : '') + ')' : '');
        };
        const mins = n => { n = parseInt(n, 10) || 0; const h = Math.floor(n / 60), m = n % 60; return (h ? h + 'h' : '') + (m ? (h ? ' ' : '') + m + 'm' : '') || '0m'; };
        const hours = v => {
            if (!v || v === '--') return '';
            const s = String(v);
            const total = s.indexOf(':') !== -1 ? (parseInt(s.split(':')[0], 10) * 60 + parseInt(s.split(':')[1] || 0, 10)) : Math.round(parseFloat(s) * 60);
            return total ? mins(total) : '';
        };
        const row = (icon, label, value, sub) => value
            ? `<div class="cdh-row"><i class="feather-${icon}"></i><span class="cdh-label">${label}</span><span class="cdh-value">${value}${sub ? `<span class="cdh-sub">${sub}</span>` : ''}</span></div>`
            : '';
        const where = (addr, dist) => {
            const parts = [];
            if (addr) parts.push(esc(addr));
            if (dist !== null && dist !== undefined && dist !== '') parts.push(esc(Math.round(parseFloat(dist))) + ' m from office');
            return parts.join(' · ');
        };

        function content(date) {
            const cal = getCalendar();
            if (!cal) return '';
            const events = cal.getEvents().filter(e => e.extendedProps.kind !== 'work'
                && moment(e.start).format('YYYY-MM-DD') === date);
            const p = Object.assign({}, ...events.map(e => {
                const x = e.extendedProps, o = {};
                Object.keys(x).forEach(k => { if (x[k] !== null && x[k] !== undefined && x[k] !== '') o[k] = x[k]; });
                return o;
            }));
            const d = p.details || {};
            const status = events.map(e => `<span class="cdh-badge" style="background:${esc(e.backgroundColor || '#94a3b8')};color:${esc(e.textColor || '#fff')}">${esc(e.extendedProps.status || e.extendedProps.day_status || e.title)}</span>`).join('');

            const inTime = time(p.clock_in, date), outTime = time(p.clock_out, date);
            const late = d.late_minutes > 0 ? `<span class="cdh-flag late">Late ${mins(d.late_minutes)}</span>` : '';
            const early = d.early_minutes > 0 ? `<span class="cdh-flag late">Early ${mins(d.early_minutes)}</span>` : '';
            const regularized = d.is_regularized
                ? 'Yes' + (d.regularized_by ? ' — by ' + esc(d.regularized_by) : '') + (d.regularized_at ? ' on ' + moment(d.regularized_at).format('DD MMM, hh:mm A') : '')
                : '';
            const leave = p.leave_type || p.leave_reason ? esc([p.leave_type, p.leave_reason].filter(Boolean).join(' — ')) : '';

            const body = [
                row('clock', 'Shift', d.shift_name || d.shift_time ? esc(d.shift_name || 'Shift') : '', d.shift_time ? esc(d.shift_time) : ''),
                row('log-in', 'Clock in', inTime ? esc(inTime) + late : '', where(d.clock_in_address, d.check_in_distance)),
                row('log-out', 'Clock out', outTime ? esc(outTime) + early : (inTime ? 'Not clocked out' : ''), where(d.clock_out_address, d.check_out_distance)),
                row('watch', 'Worked', esc(hours(p.total_hours))),
                row('trending-up', 'Overtime', d.overtime_minutes > 0 ? `<span class="cdh-flag ot">${mins(d.overtime_minutes)}</span>` : ''),
                row('map-pin', 'Location', d.location_verification ? esc(String(d.location_verification).replace(/_/g, ' ')) : ''),
                row('edit-3', 'Regularized', regularized),
                row('user-check', 'Marked by', d.marked_by ? esc(d.marked_by) + (d.source ? ' (' + esc(d.source) + ')' : '') : ''),
                row('star', 'Holiday', p.holiday_name ? esc(p.holiday_name) : ''),
                row('calendar', 'Leave', leave),
                row('check-square', 'Tasks', p.task_count > 0 ? esc(p.task_count) : ''),
                row('message-square', 'Remarks', d.remarks ? esc(d.remarks) : ''),
            ].join('');

            return `<div class="cdh-head"><div class="cdh-date">${moment(date).format('dddd, DD MMM YYYY')}</div>`
                + (status ? `<div class="cdh-badges">${status}</div>` : '') + '</div>'
                + `<div class="cdh-body">${body || '<div class="cdh-empty">No attendance details for this day.</div>'}</div>`;
        }

        function place(e) {
            const pad = 14, w = card.offsetWidth, h = card.offsetHeight;
            let x = e.clientX + pad, y = e.clientY + pad;
            if (x + w > window.innerWidth - 8) x = e.clientX - w - pad;
            if (y + h > window.innerHeight - 8) y = Math.max(8, window.innerHeight - h - 8);
            card.style.left = Math.max(8, x) + 'px';
            card.style.top = y + 'px';
        }

        let current = null;
        containerEl.addEventListener('mousemove', function(e) {
            // Day cell (month), day column / header (week, day), or an event / list row tagged with data-date.
            const el = e.target.closest('[data-date]');
            const date = el ? String(el.getAttribute('data-date')).slice(0, 10) : null;
            if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
                card.style.display = 'none';
                current = null;
                return;
            }
            if (date !== current) {
                current = date;
                card.innerHTML = content(date);
            }
            card.style.display = 'block';
            place(e);
        });
        containerEl.addEventListener('mouseleave', function() {
            card.style.display = 'none';
            current = null;
        });
    }
</script>
