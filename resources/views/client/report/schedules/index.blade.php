@extends('client.layout.master')

{{-- Reports → Scheduled Reports (2026-11-10): email a report's export on a schedule. Admin + HR only. --}}

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .rs-stats { grid-template-columns: repeat(4, 1fr); }
        @media (max-width: 768px) { .rs-stats { grid-template-columns: repeat(2, 1fr); } }

        .rs-table th, .rs-table td { font-size: 12px; padding: 8px 10px; vertical-align: middle; }
        .rs-table .rs-name { font-weight: 600; color: #0f172a; }
        .rs-table .rs-sub { font-size: 11px; color: #64748b; }
        .rs-table .rs-error { font-size: 11px; color: #b91c1c; max-width: 260px; white-space: normal; }
        .rs-actions { display: inline-flex; flex-wrap: nowrap; gap: 4px; }
        .rs-actions .action-btn { width: 28px; height: 28px; }

        .rs-runs th, .rs-runs td { font-size: 11.5px; padding: 6px 8px; vertical-align: top; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Scheduled Reports"
        :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')]]" :back="route('report.attendance.index')">
        <x-slot:actions>
            <button type="button" class="btn btn-primary btn-sm" onclick="openScheduleForm()" @disabled($options['reports'] === [])>
                <i class="feather-plus me-1"></i> New Schedule
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body">
        <div class="alert alert-light border mb-3" style="font-size: 12px;">
            <i class="feather-info icon-primary me-1"></i>
            Each schedule emails the same file as the report's <strong>Export</strong> button, at the time you choose (India time).
            The report is built with the access of the person who last saved the schedule.
        </div>

        <div class="stats-grid rs-stats">
            <div class="stats-card">
                <div class="stats-icon-wrapper"><i class="feather-clock"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $summary['total'] }}</div>
                    <div class="stats-label">Schedules</div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon-wrapper"><i class="feather-play-circle"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $summary['active'] }}</div>
                    <div class="stats-label">Active</div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon-wrapper"><i class="feather-pause-circle"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $summary['paused'] }}</div>
                    <div class="stats-label">Paused</div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon-wrapper"><i class="feather-alert-triangle"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $summary['failed'] }}</div>
                    <div class="stats-label">Last Send Failed</div>
                </div>
            </div>
        </div>

        <div class="card stretch stretch-full">
            <div class="card-body p-0">
                <x-ui.data-table class="rs-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:56px;">Sr. No.</th>
                            <th>Schedule / Report</th>
                            <th>Period</th>
                            <th>When</th>
                            <th>Recipients</th>
                            <th>Next Send</th>
                            <th>Last Send</th>
                            <th>Status</th>
                            <th class="text-center" style="width:1%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($schedules as $s)
                            @php
                                $json = [
                                    'id' => $s->id, 'name' => $s->name, 'report_key' => $s->report_key, 'period' => $s->period,
                                    'branch_id' => $s->filters['branch_id'] ?? '', 'department_id' => $s->filters['department_id'] ?? '',
                                    'frequency' => $s->frequency, 'weekdays' => $s->weekdays ?? [], 'day_of_month' => $s->day_of_month,
                                    'send_time' => substr($s->send_time, 0, 5), 'recipients' => $s->recipients,
                                    'subject' => $s->subject, 'message' => $s->message,
                                ];
                                $recipients = (array) $s->recipients;
                            @endphp
                            <tr>
                                <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="rs-name">{{ $s->name }}</div>
                                    <div class="rs-sub">{{ \App\Support\ReportCatalog::label($s->report_key) }}</div>
                                </td>
                                <td class="text-nowrap">{{ $s->period ? ($periodLabels[$s->period] ?? $s->period) : 'Current data' }}</td>
                                <td class="text-nowrap">{{ $describe($s) }}</td>
                                <td>
                                    <div class="text-truncate" style="max-width:200px;" title="{{ implode(', ', $recipients) }}">{{ $recipients[0] ?? '—' }}</div>
                                    @if (count($recipients) > 1)
                                        <div class="rs-sub">+{{ count($recipients) - 1 }} more</div>
                                    @endif
                                </td>
                                <td class="text-nowrap">{{ $s->is_active && $s->next_run_at ? $s->next_run_at->format('d M Y, h:i A') : '—' }}</td>
                                <td>
                                    @if ($s->last_run_at)
                                        <x-ui.status-badge :status="$s->last_status === 'sent' ? 'success' : 'failed'" :label="$s->last_status === 'sent' ? 'Sent' : 'Failed'" />
                                        <div class="rs-sub text-nowrap">{{ $s->last_run_at->format('d M, h:i A') }}</div>
                                        @if ($s->last_status === 'failed' && $s->last_error)
                                            <div class="rs-error">{{ \Illuminate\Support\Str::limit($s->last_error, 120) }}</div>
                                        @endif
                                    @else
                                        <span class="rs-sub">Not sent yet</span>
                                    @endif
                                    @if ($s->run_requested_at)
                                        <div class="rs-sub"><i class="feather-loader"></i> Sending…</div>
                                    @endif
                                </td>
                                <td>
                                    <x-ui.status-badge :status="$s->is_active ? 'active' : 'inactive'" :label="$s->is_active ? 'Active' : 'Paused'" />
                                    @if (! $s->is_active && $s->paused_reason)
                                        <div class="rs-error">{{ $s->paused_reason }}</div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="rs-actions">
                                        <button type="button" class="action-btn" title="Send now"
                                            onclick="sendScheduleNow({{ $s->id }})"><i class="feather-send"></i></button>
                                        <button type="button" class="action-btn" title="Run log"
                                            onclick="openRunLog({{ $s->id }}, {{ json_encode($s->name) }})"><i class="feather-list"></i></button>
                                        <button type="button" class="action-btn" title="Edit"
                                            data-schedule="{{ json_encode($json) }}" onclick="openScheduleForm(JSON.parse(this.dataset.schedule))"><i class="feather-edit-2"></i></button>
                                        <button type="button" class="action-btn {{ $s->is_active ? 'warning' : 'success' }}"
                                            title="{{ $s->is_active ? 'Pause' : 'Resume' }}"
                                            onclick="toggleSchedule({{ $s->id }}, {{ $s->is_active ? 'false' : 'true' }})">
                                            <i class="feather-{{ $s->is_active ? 'pause-circle' : 'play-circle' }}"></i></button>
                                        <button type="button" class="action-btn delete" title="Delete"
                                            onclick="deleteSchedule({{ $s->id }}, {{ json_encode($s->name) }})"><i class="feather-trash-2"></i></button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <x-ui.empty-state icon="clock" title="No scheduled reports yet"
                                        :subtitle="$options['reports'] === [] ? 'No reports are available to schedule for your company plan.' : 'Click New Schedule to email a report automatically.'" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    @include('client.report.schedules.partials.form-drawer')

    <x-ui.drawer id="runLogDrawer" title="Run Log" subtitle="The last 50 sends of this schedule." width="760px">
        <div id="runLogBody" class="table-responsive"></div>
    </x-ui.drawer>
@endsection

@section('script-area')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
        const scheduleUrl = "{{ url('report/schedules') }}";

        function scheduleCall(url, type, data, done) {
            $.ajax({
                url: url, type: type, data: data,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
                success: r => { toastr.success(r.message); if (done) setTimeout(done, 800); },
                error: x => toastr.error(x.responseJSON?.message || 'Something went wrong. Please try again.'),
            });
        }

        function sendScheduleNow(id) {
            if (!confirm('Send this report to all its recipients now?')) return;
            scheduleCall(scheduleUrl + '/' + id + '/send-now', 'POST', {}, () => location.reload());
        }

        function toggleSchedule(id, active) {
            scheduleCall(scheduleUrl + '/' + id + '/toggle', 'POST', { active: active ? 1 : 0 }, () => location.reload());
        }

        function deleteSchedule(id, name) {
            if (!confirm('Delete the schedule "' + name + '"? Its run log is deleted too.')) return;
            scheduleCall(scheduleUrl + '/' + id, 'DELETE', {}, () => location.reload());
        }

        function openRunLog(id, name) {
            const esc = s => $('<span>').text(s ?? '').html();
            $('#runLogDrawerLabel').text('Run Log — ' + name);
            $('#runLogBody').html('<div class="text-center text-muted py-4" style="font-size:12px;">Loading…</div>');
            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('runLogDrawer')).show();
            $.get(scheduleUrl + '/' + id + '/runs', r => {
                if (!r.runs.length) {
                    $('#runLogBody').html('<div class="text-center text-muted py-4" style="font-size:12px;">This schedule has not been sent yet.</div>');
                    return;
                }
                const rows = r.runs.map(x => '<tr>'
                    + '<td class="text-nowrap">' + esc(x.at) + '<div class="text-muted">' + esc(x.trigger) + '</div></td>'
                    + '<td><span class="status-badge" data-status="' + (x.status === 'sent' ? 'success' : 'failed') + '">' + (x.status === 'sent' ? 'Sent' : 'Failed') + '</span></td>'
                    + '<td>' + esc(x.period || '—') + '</td>'
                    + '<td>' + (x.file ? esc(x.file) + '<div class="text-muted">' + esc(x.size) + '</div>' : '—') + '</td>'
                    + '<td class="text-center">' + x.recipients + '</td>'
                    + '<td class="text-danger" style="max-width:260px;">' + esc(x.error || '') + '</td>'
                    + '</tr>').join('');
                $('#runLogBody').html('<table class="table rs-runs mb-0"><thead><tr><th>When</th><th>Status</th><th>Period</th><th>File</th><th>To</th><th>Problem</th></tr></thead><tbody>' + rows + '</tbody></table>');
            }).fail(() => $('#runLogBody').html('<div class="text-danger py-3" style="font-size:12px;">Could not load the run log.</div>'));
        }
    </script>
    @stack('schedule-scripts')
@endsection
