@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ALL-BLUE THEME (same as the other Reports pages) ==================== */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: .6rem; margin-bottom: .8rem; }
    .stats-card {
        background: white; border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px;
        display: flex; align-items: center; box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
    }
    .stats-icon { width: 30px; height: 30px; background: #e3edfe; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 8px; flex: none; }
    .stats-icon i { font-size: 13px; color: #1e3a8a; }
    .stats-info h3 { font-size: 15px; font-weight: 700; margin: 0 0 1px 0; color: #1a2236; line-height: 1.2; }
    .stats-info p { font-size: 9.5px; color: #6b7385; margin: 0; }

    .filter-section { background: #fff; border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
    /* All filters on one line: items share the width; scrolls sideways only on narrow screens. */
    .filter-row { display: flex; flex-wrap: nowrap; gap: 6px; align-items: center; overflow-x: auto; }
    .filter-row .filter-item { flex: 1 1 0; min-width: 96px; }
    .filter-row .filter-item.fi-date { flex: 0 0 122px; min-width: 122px; }
    .filter-row .filter-item.fi-search { flex: 1.4 1 0; min-width: 140px; }
    .filter-row .filter-item.fi-btn { flex: 0 0 auto; min-width: 0; }
    .filter-row .form-control-sm-custom { width: 100%; }
    .form-control-sm-custom { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 8px; font-size: 11px; height: 32px; background-color: #fff; }
    .form-control-sm-custom:focus { border-color: #1e3a8a; box-shadow: 0 0 0 .15rem rgba(30, 58, 138, .12); outline: none; }
    .btn-sm-custom { background: #1e3a8a; color: #fff; border: none; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; }
    .btn-sm-custom:hover { background: #2563eb; color: #fff; }
    .btn-sm-custom-outline { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; text-decoration: none; }
    .btn-sm-custom-outline:hover { background: #e3edfe; color: #1e3a8a; }
    .btn-sm-custom, .btn-sm-custom-outline { white-space: nowrap; height: 32px; display: inline-flex; align-items: center; gap: 4px; }
    .date-sep { font-size: 11px; color: #6b7385; flex: none; }

    .punch-table-wrap { overflow-x: auto; }
    #punchTable { font-size: 11px; width: max-content; min-width: 100%; border-collapse: separate; border-spacing: 0; }
    #punchTable th, #punchTable td { padding: 7px 10px; vertical-align: top; border-bottom: 1px solid #eef1f7; white-space: nowrap; }
    #punchTable thead th { font-size: 9.5px; font-weight: 700; color: #6b7385; background: #f7faff; text-transform: uppercase; letter-spacing: .3px; border-bottom: 1px solid #e3edfe; }
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
    .chip { display: inline-block; padding: 1px 6px; border-radius: 6px; font-size: 9.5px; font-weight: 600; background: #eef3fd; color: #1e3a8a; }
    .chip.ok { background: #dcfce7; color: #15803d; }
    .chip.warn { background: #fef3c7; color: #b45309; }
    .chip.bad { background: #fee2e2; color: #b91c1c; }
    .muted { color: #cbd5e1; }
</style>
@endsection

@section('content-area')
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">Clock In/Out Log</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('report.attendance.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Clock In/Out Log</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <a href="{{ route('report.attendance.punches.export', request()->query()) }}" class="btn btn-sm btn-primary">
                <i class="feather-download me-1"></i> Export CSV
            </a>
        </div>
    </div>

    <div class="content-area-body" style="padding: 20px !important;">
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-list"></i></div>
                <div class="stats-info"><h3>{{ number_format((int) $stats->total) }}</h3><p>Total punches</p></div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-log-in"></i></div>
                <div class="stats-info"><h3>{{ number_format((int) $stats->ins) }}</h3><p>Clock in</p></div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-log-out"></i></div>
                <div class="stats-info"><h3>{{ number_format((int) $stats->outs) }}</h3><p>Clock out</p></div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-users"></i></div>
                <div class="stats-info"><h3>{{ number_format((int) $stats->employees) }}</h3><p>Employees</p></div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-map-pin"></i></div>
                <div class="stats-info"><h3>{{ number_format((int) $stats->out_of_bounds) }}</h3><p>Outside office radius</p></div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="filter-section">
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
                    @feature('branches')
                    <div class="filter-item">
                        <select name="branch_id" class="form-control-sm-custom auto-submit" aria-label="Branch">
                            <option value="">-- All Branches --</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" @selected($filters['branch_id'] === $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endfeature
                    <div class="filter-item">
                        <select name="user_id" class="form-control-sm-custom auto-submit" aria-label="Employee">
                            <option value="">-- All Employees --</option>
                            @foreach ($employees as $e)
                                <option value="{{ $e->id }}" @selected($filters['user_id'] === $e->id)>{{ $e->name }}{{ $e->employee_id ? ' (' . $e->employee_id . ')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="department_id" class="form-control-sm-custom auto-submit" aria-label="Department">
                            <option value="">-- All Departments --</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id }}" @selected($filters['department_id'] === $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="designation_id" class="form-control-sm-custom auto-submit" aria-label="Designation">
                            <option value="">-- All Designations --</option>
                            @foreach ($designations as $d)
                                <option value="{{ $d->id }}" @selected($filters['designation_id'] === $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
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
                    <div class="filter-item fi-btn"><a href="{{ route('report.attendance.punches.index') }}" class="btn-sm-custom-outline" title="Reset filters"><i class="feather-refresh-cw"></i> Reset</a></div>
                </div>
            </form>
        </div>

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
                                    <td colspan="23" class="text-center py-5 text-muted" style="font-size:11px;">
                                        No clock in/out found for these filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($rows->hasPages())
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} punches
                    </div>
                    <div>{{ $rows->links('pagination::bootstrap-4') }}</div>
                </div>
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
