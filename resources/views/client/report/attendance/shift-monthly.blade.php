@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ALL-BLUE THEME (same as the other Reports pages) ==================== */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .6rem; margin-bottom: .8rem; }
    .stats-card {
        background: white; border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px;
        display: flex; align-items: center; box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
    }
    .stats-icon { width: 30px; height: 30px; background: #e3edfe; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 8px; flex: none; }
    .stats-icon i { font-size: 13px; color: #1e3a8a; }
    .stats-info h3 { font-size: 15px; font-weight: 700; margin: 0 0 1px 0; color: #1a2236; line-height: 1.2; }
    .stats-info p { font-size: 9.5px; color: #6b7385; margin: 0; }

    .filter-section { background: #fff; border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
    .filter-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .form-control-sm-custom { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 10px; font-size: 11px; height: 32px; background-color: #fff; }
    .form-control-sm-custom:focus { border-color: #1e3a8a; box-shadow: 0 0 0 .15rem rgba(30, 58, 138, .12); outline: none; }
    .btn-sm-custom { background: #1e3a8a; color: #fff; border: none; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; }
    .btn-sm-custom:hover { background: #2563eb; color: #fff; }
    .btn-sm-custom-outline { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; text-decoration: none; }
    .btn-sm-custom-outline:hover { background: #e3edfe; color: #1e3a8a; }

    /* legend */
    .shift-legend { display: flex; flex-wrap: wrap; gap: 6px 14px; align-items: center; margin-bottom: 10px; font-size: 10.5px; color: #475569; }
    .shift-legend .lg-item { display: inline-flex; align-items: center; gap: 5px; }
    .shift-legend .lg-time { color: #94a3b8; }

    /* matrix */
    .shift-matrix-wrap { overflow-x: auto; }
    #shiftMatrix { font-size: 10.5px; border-collapse: separate; border-spacing: 0; width: max-content; min-width: 100%; }
    #shiftMatrix th, #shiftMatrix td { padding: 4px 3px; vertical-align: middle; text-align: center; border-bottom: 1px solid #eef1f7; }
    #shiftMatrix thead th { font-size: 9.5px; font-weight: 700; color: #6b7385; background: #f7faff; border-bottom: 1px solid #e3edfe; line-height: 1.2; }
    #shiftMatrix thead th .dow { display: block; font-weight: 600; color: #94a3b8; font-size: 8.5px; text-transform: uppercase; }
    #shiftMatrix thead th.is-weekend { background: #eef3fd; }
    #shiftMatrix .col-emp { position: sticky; left: 0; z-index: 2; background: #fff; text-align: left; min-width: 190px; max-width: 190px; padding: 5px 10px; border-right: 1px solid #eef1f7; }
    #shiftMatrix thead .col-emp { background: #f7faff; z-index: 3; text-transform: uppercase; }
    #shiftMatrix .col-sum { min-width: 44px; font-weight: 700; color: #1a2236; background: #fbfcff; }
    #shiftMatrix thead .col-sum { text-transform: uppercase; }
    #shiftMatrix tbody tr:hover td { background: #fafcff; }
    #shiftMatrix tbody tr:hover td.col-emp { background: #fafcff; }
    .emp-name { font-weight: 600; color: #1a2236; font-size: 11px; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .emp-sub { font-size: 9.5px; color: #6b7385; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .shift-chip { display: inline-block; min-width: 30px; padding: 2px 4px; border-radius: 6px; font-size: 9px; font-weight: 700; letter-spacing: .2px; line-height: 1.3; cursor: default; }
    .shift-chip.wo { background: #eef1f7; color: #64748b; }
    .shift-chip.none { background: transparent; color: #cbd5e1; font-weight: 400; }
</style>
@endsection

@section('content-area')
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">Shift Report (Monthly)</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('report.attendance.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Shift Monthly</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <a href="{{ route('report.attendance.shift-monthly.export', request()->query()) }}" class="btn btn-sm btn-primary">
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
                <div class="stats-icon"><i class="feather-users"></i></div>
                <div class="stats-info"><h3>{{ $stats['employees'] }}</h3><p>Employees</p></div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-clock"></i></div>
                <div class="stats-info"><h3>{{ number_format($stats['shift_days']) }}</h3><p>Shift days</p></div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-sun"></i></div>
                <div class="stats-info"><h3>{{ number_format($stats['weekoffs']) }}</h3><p>Week-off days</p></div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-alert-circle"></i></div>
                <div class="stats-info"><h3>{{ number_format($stats['unassigned']) }}</h3><p>Unassigned days</p></div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-layers"></i></div>
                <div class="stats-info"><h3>{{ $stats['shifts_in_use'] }}</h3><p>Shifts in use</p></div>
            </div>
        </div>

        {{-- Filters (one row, no captions — same as the other reports) --}}
        <div class="filter-section">
            <form action="{{ route('report.attendance.shift-monthly.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <input type="month" name="month" value="{{ $month }}" class="form-control-sm-custom" title="Month" aria-label="Month">
                    </div>
                    <div class="filter-item">
                        <input type="text" name="search" value="{{ $filters['search'] }}" class="form-control-sm-custom"
                            placeholder="Search name or employee ID" aria-label="Search employee" style="min-width:200px" autocomplete="off">
                    </div>
                    <div class="filter-item">
                        <select name="department_id" class="form-control-sm-custom auto-submit" aria-label="Department">
                            <option value="">-- All Departments --</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id }}" {{ (string) $filters['department_id'] === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="branch_id" class="form-control-sm-custom auto-submit" aria-label="Branch">
                            <option value="">-- All Branches --</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" {{ (string) $filters['branch_id'] === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="shift_id" class="form-control-sm-custom auto-submit" aria-label="Shift">
                            <option value="">-- All Shifts --</option>
                            @foreach ($shiftOptions as $s)
                                <option value="{{ $s->id }}" {{ (string) $filters['shift_id'] === (string) $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item"><button type="submit" class="btn-sm-custom"><i class="feather-eye"></i> View</button></div>
                    <div class="filter-item"><a href="{{ route('report.attendance.shift-monthly.index') }}" class="btn-sm-custom-outline"><i class="feather-refresh-cw"></i> Reset</a></div>
                </div>
            </form>
        </div>

        @unless ($customShifts)
            <div class="alert alert-info py-2" style="font-size:10.5px;border-radius:10px;">
                <i class="feather-info me-1"></i>
                This company runs on one fixed shift{{ $defaultShift ? ' (' . $defaultShift->name . ')' : '' }} — every working day shows it and
                week-offs are shown as WO. Per-employee shifts appear here once custom shifts are switched on.
            </div>
        @endunless

        {{-- Legend --}}
        <div class="shift-legend">
            <strong style="color:#1a2236">{{ $monthLabel }}</strong>
            @foreach ($legend as $l)
                <span class="lg-item">
                    <span class="shift-chip" style="background: {{ $l['color'] }}22; color: {{ $l['color'] }}">{{ $l['short'] }}</span>
                    {{ $l['name'] }} <span class="lg-time">{{ $l['start'] }}–{{ $l['end'] }}</span>
                </span>
            @endforeach
            <span class="lg-item"><span class="shift-chip wo">WO</span> Week off</span>
            <span class="lg-item"><span class="shift-chip none">–</span> Unassigned</span>
        </div>

        <div class="card stretch stretch-full">
            <div class="card-body p-0">
                <div class="shift-matrix-wrap">
                    <table id="shiftMatrix">
                        <thead>
                            <tr>
                                <th class="col-emp">Employee</th>
                                @foreach ($dates as $d)
                                    <th class="{{ $d->isWeekend() ? 'is-weekend' : '' }}" title="{{ $d->format('l, d M Y') }}">
                                        {{ $d->format('d') }}<span class="dow">{{ $d->format('D') }}</span>
                                    </th>
                                @endforeach
                                <th class="col-sum" title="Days on a shift">Shift</th>
                                <th class="col-sum" title="Week-off days">WO</th>
                                <th class="col-sum" title="Days with no shift">Unasg.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pagedRows as $row)
                                <tr>
                                    <td class="col-emp">
                                        <div class="emp-name" title="{{ $row['name'] }}">{{ $row['name'] }}</div>
                                        <div class="emp-sub">{{ $row['employee_id'] }}{{ $row['department'] ? ' · ' . $row['department'] : '' }}{{ $row['branch'] ? ' · ' . $row['branch'] : '' }}</div>
                                    </td>
                                    @foreach ($dates as $d)
                                        @php $c = $row['cells'][$d->format('Y-m-d')]; @endphp
                                        <td>
                                            @if ($c['type'] === 'shift')
                                                <span class="shift-chip" style="background: {{ $c['color'] }}22; color: {{ $c['color'] }}"
                                                    title="{{ $c['name'] }} ({{ $c['start'] }}–{{ $c['end'] }}) — {{ $d->format('d M Y') }}">{{ $c['short'] }}</span>
                                            @elseif ($c['type'] === 'weekoff')
                                                <span class="shift-chip wo" title="Week off — {{ $d->format('d M Y') }}">WO</span>
                                            @else
                                                <span class="shift-chip none" title="No shift assigned — {{ $d->format('d M Y') }}">–</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="col-sum">{{ $row['shift_days'] }}</td>
                                    <td class="col-sum">{{ $row['weekoffs'] }}</td>
                                    <td class="col-sum">{{ $row['unassigned'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $dates->count() + 4 }}" class="text-center py-5 text-muted" style="font-size:11px;">
                                        No employees match these filters for {{ $monthLabel }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($pagedRows->hasPages())
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Showing {{ $pagedRows->firstItem() }} to {{ $pagedRows->lastItem() }} of {{ $pagedRows->total() }} employees
                    </div>
                    <div>{{ $pagedRows->links('pagination::bootstrap-4') }}</div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(function() {
            const form = $('#filterForm');
            form.find('select.auto-submit').on('change', () => form.submit());
            form.find('input[name="month"]').on('change', () => form.submit());

            // search: submit shortly after typing stops, then put the cursor back after the reload
            const key = 'shiftReportSearchFocus';
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
