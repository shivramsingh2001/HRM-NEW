@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ALL-BLUE THEME (same as the other Reports pages) ==================== */
    /* All filters on one line: items share the width; scrolls sideways only on narrow screens. */
    .filter-row { display: flex; flex-wrap: nowrap; gap: 6px; align-items: center; overflow-x: auto; }





    .form-control-sm-custom { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 8px; font-size: 11px; height: 32px; background-color: #fff; }
    .form-control-sm-custom:focus { border-color: #0D6EFD; box-shadow: 0 0 0 .15rem rgba(13, 110, 253, .12); outline: none; }
    .btn-sm-custom { background: #0D6EFD; color: #fff; border: none; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; }
    .btn-sm-custom:hover { background: #0D6EFD; color: #fff; }
    .btn-sm-custom-outline { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; text-decoration: none; }
    .btn-sm-custom-outline:hover { background: #EFF6FF; color: #0D6EFD; }
    .btn-sm-custom, .btn-sm-custom-outline { white-space: nowrap; height: 32px; display: inline-flex; align-items: center; gap: 4px; }
    .date-sep { font-size: 11px; color: #6b7385; flex: none; }

    .punch-table-wrap { overflow-x: auto; }
    #punchTable { font-size: 11px; width: max-content; min-width: 100%; border-collapse: separate; border-spacing: 0; }
    #punchTable th, #punchTable td { padding: 7px 10px; vertical-align: top; border-bottom: 1px solid #eef1f7; white-space: nowrap; }
    #punchTable thead th { font-size: 9.5px; font-weight: 700; color: #6b7385; background: #f7faff; text-transform: uppercase; letter-spacing: .3px; border-bottom: 1px solid #EFF6FF; }
    #punchTable tbody tr:hover td { background: #fafcff; }
    #punchTable .col-emp { position: sticky; left: 0; background: #fff; z-index: 1; border-right: 1px solid #eef1f7; }
    #punchTable thead .col-emp { background: #f7faff; z-index: 2; }
    #punchTable tbody tr:hover td.col-emp { background: #fafcff; }
    #punchTable td.col-emp { vertical-align: middle; }
    #punchTable .employee-avatar { flex: none; }
    .sub { font-size: 9.5px; color: #6b7385; }
    .addr { white-space: normal !important; min-width: 220px; max-width: 280px; }

    .dir { display: inline-block; min-width: 36px; text-align: center; padding: 2px 6px; border-radius: 6px; font-size: 9.5px; font-weight: 700; }
    .dir-in { background: #dcfce7; color: #15803d; }
    .dir-out { background: #fee2e2; color: #b91c1c; }
    .chip { display: inline-block; padding: 1px 6px; border-radius: 6px; font-size: 9.5px; font-weight: 600; background: #eef3fd; color: #0D6EFD; }
    .chip.ok { background: #dcfce7; color: #15803d; }
    .chip.warn { background: #fef3c7; color: #b45309; }
    .chip.bad { background: #fee2e2; color: #b91c1c; }
    .muted { color: #cbd5e1; }
</style>
@endsection

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Clock In/Out Log" :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')]]">
        <x-slot:actions>
            <a href="{{ route('report.attendance.punches.export', request()->query()) }}" class="btn btn-sm btn-primary">
                <i class="feather-download me-1"></i> Export CSV
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body" style="padding: 20px !important;">
        @include('client.report.partials.report-subnav', ['group' => 'attendance'])
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- Filters --}}
        <x-ui.filter-card title="Filter Report">
            <form action="{{ route('report.attendance.punches.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item fi-date">
                        <input type="date" name="start_date" value="{{ $filters['start'] }}" class="form-control-sm-custom auto-submit" title="Start date" aria-label="Start date">
                    </div>
                    <span class="date-sep">to</span>
                    <div class="filter-item fi-date">
                        <input type="date" name="end_date" value="{{ $filters['end'] }}" class="form-control-sm-custom auto-submit" title="End date" aria-label="End date">
                    </div>
                    <div class="filter-item fi-search">
                        <input type="text" name="search" value="{{ $filters['search'] }}" class="form-control-sm-custom"
                            placeholder="Search name / ID" aria-label="Search employee" autocomplete="off">
                    </div>
                    <div class="filter-item">
                        <select name="user_id" class="form-control-sm-custom auto-submit" aria-label="Employee">
                            <option value="">-- All Employees --</option>
                            @foreach ($employees as $e)
                                <option value="{{ $e->id }}" @selected($filters['user_id'] === $e->id)>{{ $e->name }}{{ $e->employee_id ? ' (' . $e->employee_id . ')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('client.report.partials.employee-filters', ['selectClass' => 'form-control-sm-custom', 'deptParam' => 'department_id', 'desigParam' => 'designation_id'])
                    <div class="filter-item">
                        <select name="direction" class="form-control-sm-custom auto-submit" aria-label="Direction">
                            <option value="">-- In & Out --</option>
                            <option value="in" @selected($filters['direction'] === 'in')>In only</option>
                            <option value="out" @selected($filters['direction'] === 'out')>Out only</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="source" class="form-control-sm-custom auto-submit" aria-label="Source">
                            <option value="">-- All Sources --</option>
                            @foreach (['mobile_app', 'biometric', 'web', 'manual', 'kiosk', 'api', 'backfill'] as $src)
                                <option value="{{ $src }}" @selected($filters['source'] === $src)>{{ \App\Http\Controllers\Report\PunchReportController::sourceLabel($src) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item fi-btn"><a href="{{ route('report.attendance.punches.index') }}" class="btn-sm-custom-outline" title="Reset filters"><i class="feather-refresh-cw"></i></a></div>
                </div>
            </form>
        </x-ui.filter-card>

        <div class="card stretch stretch-full">
            <div class="card-body p-0">
                <div class="punch-table-wrap">
                    <table id="punchTable">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th class="col-emp">Employee</th>
                                <th>Department</th>
                                <th>Designation</th>
                                @feature('branches')<th>Branch</th>@endfeature
                                <th>Attendance Location</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Dir</th>
                                <th>Session</th>
                                <th>Source</th>
                                <th>Location / Address</th>
                                <th>Lat, Long</th>
                                <th>Office location</th>
                                <th>Radius</th>
                                <th>Distance</th>
                                <th>Location check</th>
                                <th>GPS accuracy</th>
                                <th>Device</th>
                                <th>Network</th>
                                <th>IP</th>
                                <th>Battery</th>
                                <th>Recorded by</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                @php
                                    $at = \Carbon\Carbon::parse($r->punched_at);
                                    $hasGps = $r->lat !== null && $r->lng !== null && ((float) $r->lat !== 0.0 || (float) $r->lng !== 0.0);
                                    $check = $r->location_verification;
                                @endphp
                                <tr>
                                    <td>{{ $rows->firstItem() + $loop->index }}</td>
                                    <td class="col-emp">
                                        {{-- Same employee cell as the Team page --}}
                                        <div class="employee-info">
                                            <div class="employee-avatar">
                                                {{ strtoupper(substr($r->employee_name ?? 'N/A', 0, 2)) }}
                                            </div>
                                            <div class="employee-details">
                                                <div class="employee-name-text">{{ $r->employee_name ?? 'N/A' }}
                                                    <small>( {{ $r->employee_code ?? 'N/A' }} )</small>
                                                </div>
                                                <div class="employee-email-text">{{ $r->employee_email ?? 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $r->department_name ?: '—' }}</td>
                                    <td>{{ $r->designation_name ?: '—' }}</td>
                                    @feature('branches')<td>{{ $r->branch_name ?: '—' }}</td>@endfeature
                                    <td>{{ \App\Models\AttendanceLocation::labelFor($r->office_branch, $r->assigned_location_name) }}</td>
                                    <td>{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}<div class="sub">{{ \Carbon\Carbon::parse($r->date)->format('l') }}</div></td>
                                    <td><strong>{{ $at->format('h:i:s A') }}</strong>
                                        @if ($at->toDateString() !== \Carbon\Carbon::parse($r->date)->toDateString())
                                            <div class="sub">{{ $at->format('d M') }}</div>
                                        @endif
                                    </td>
                                    <td><span class="dir {{ $r->direction === 'in' ? 'dir-in' : 'dir-out' }}">{{ strtoupper($r->direction) }}</span></td>
                                    <td>{{ $r->session_seq ? '#' . $r->session_seq : '—' }}</td>
                                    <td>
                                        <span class="chip">{{ \App\Http\Controllers\Report\PunchReportController::sourceLabel($r->source) }}</span>
                                        <div class="sub">{{ $r->method ? ucfirst(str_replace('_', ' ', $r->method)) : '' }}{{ $r->terminal_name ? ' · ' . $r->terminal_name : '' }}</div>
                                        @if ($r->is_regularized)<div class="sub">Regularized</div>@endif
                                    </td>
                                    <td class="addr">{{ $r->address ?: '—' }}</td>
                                    <td>
                                        @if ($hasGps)
                                            <a href="https://www.google.com/maps?q={{ $r->lat }},{{ $r->lng }}" target="_blank" rel="noopener">{{ round((float) $r->lat, 6) }}, {{ round((float) $r->lng, 6) }}</a>
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $r->location_name ?: '—' }}</td>
                                    <td>{{ $r->radius !== null ? number_format((float) $r->radius) . ' m' : '—' }}</td>
                                    <td>{{ $r->distance_meters !== null ? number_format((float) $r->distance_meters, 1) . ' m' : '—' }}</td>
                                    <td>
                                        @if ($check === 'verified')
                                            <span class="chip ok">Inside radius</span>
                                        @elseif ($check === 'out_of_bounds')
                                            <span class="chip bad">Outside radius</span>
                                        @elseif ($check === 'unverified')
                                            <span class="chip warn">Not verified</span>
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $r->accuracy_meters !== null ? '±' . number_format((float) $r->accuracy_meters) . ' m' : '—' }}</td>
                                    <td>{{ $r->device_id ?: '—' }}</td>
                                    <td>{{ $r->network_type ?: '' }}{{ $r->wifi_ssid ? ($r->network_type ? ' · ' : '') . $r->wifi_ssid : '' }}{{ !$r->network_type && !$r->wifi_ssid ? '—' : '' }}</td>
                                    <td>{{ $r->ip_address ?: '—' }}</td>
                                    <td>{{ $r->battery_percent !== null ? $r->battery_percent . '%' : '—' }}</td>
                                    <td>{{ $r->actor_name ?: '—' }}</td>
                                    <td class="addr">{{ $r->reason ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="24" class="text-center py-5 text-muted" style="font-size:11px;">
                                        No clock in/out found for these filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($rows->hasPages())
                <x-ui.pagination-footer :paginator="$rows" label="punches" />
            @endif
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(function() {
            const form = $('#filterForm');
            form.find('.auto-submit').on('change', () => form.submit());

            // search: submit shortly after typing stops, then put the cursor back after the reload
            const key = 'punchReportSearchFocus';
            const box = form.find('input[name="search"]');
            let last = box.val(), timer;
            box.on('input', function() {
                clearTimeout(timer);
                timer = setTimeout(function() {
                    if (box.val() === last) return;
                    try { sessionStorage.setItem(key, '1'); } catch (e) {}
                    form.submit();
                }, 600);
            });
            try {
                if (sessionStorage.getItem(key)) {
                    sessionStorage.removeItem(key);
                    const el = box.get(0);
                    if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }
                }
            } catch (e) {}
        });
    </script>
@endsection
