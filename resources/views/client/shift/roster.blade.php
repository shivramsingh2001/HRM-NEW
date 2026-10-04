@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        /* --- compact page --- */
        .shift-roster .content-area-body { padding: 10px 14px !important; }
        .shift-roster .card { margin-bottom: 0; }
        .shift-roster .card-header { padding: 8px 12px; }
        .shift-roster .card-body { padding: 10px 12px; }
        .shift-roster .nav-tabs .nav-link { padding: 8px 14px; font-size: 13px; font-weight: 600; }
        .shift-roster label.form-label { font-size: 11px; margin-bottom: 2px; color: #64748b; }
        .shift-roster .form-control-sm { font-size: 12px; padding: 3px 8px; height: auto; }
        .shift-roster .btn-sm { font-size: 12px; padding: 3px 9px; }
        .rfilter { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .rfilter > div { display: flex; flex-direction: column; }
        /* .view-toggle/.quick-range/.search-box are also direct .rfilter children —
           these two-class selectors outrank ".rfilter > div" so their own
           display/flex-direction wins instead of being forced into a column. */
        .rfilter .view-toggle, .rfilter .quick-range, .rfilter .search-box { flex-direction: row; }

        /* Existing Assignments filter — one row, no wrapping; scrolls
           horizontally instead of dropping to a second line on narrow screens. */
        #listFilter { flex-wrap: nowrap; overflow-x: auto; padding-bottom: 2px; }
        #listFilter > div, #listFilter .search-box, #listFilter .quick-range { flex-shrink: 0; }

        /* --- grid --- */
        .roster-wrap { overflow: auto; border: 1px solid #edf2f7; border-radius: 10px; }
        table.roster-table { border-collapse: separate; border-spacing: 0; width: 100%; margin: 0; font-size: 11px; }
        .roster-table th, .roster-table td { vertical-align: middle; white-space: nowrap; padding: 4px 8px; border-bottom: 1px solid #f1f5f9; }
        .roster-table thead th { position: sticky; top: 0; z-index: 3; background: #f8fafc; font-size: 10px;
            text-transform: uppercase; letter-spacing: .02em; color: #64748b; text-align: center; }
        .roster-table th:first-child, .roster-table td:first-child {
            position: sticky; left: 0; z-index: 2; background: #fff; box-shadow: 1px 0 0 #edf2f7; text-align: left;
        }
        .roster-table thead th:first-child { z-index: 4; background: #f8fafc; }
        .roster-table th.wknd, .roster-table td.wknd { background: #fafafb; }
        /* .employee-info/.employee-avatar/.employee-details/.employee-name/
           .employee-email are centralized in client.layout.head (Team Leave's
           pattern) — no local .emp-cell/.emp-avatar/.emp-name/.emp-sub copy. */
        .shift-chip { display: inline-block; padding: 2px 7px; border-radius: 6px; font-size: 10px; font-weight: 600;
            color: #1e293b; border-left: 3px solid #4f46e5; background: #4f46e522; line-height: 1.25; }
        .shift-chip .t { display: block; font-weight: 500; font-size: 9px; color: #475569; }
        .chip { display: inline-block; padding: 2px 7px; border-radius: 6px; font-size: 10px; font-weight: 600; }
        .chip-weekoff { background: #f1f5f9; color: #64748b; }
        .chip-unassigned { color: #cbd5e1; font-weight: 400; }
        .chip-muted { opacity: .3; }
        /* month view: tiny square */
        .mday { display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: #e2e8f0; }
        .mday.off { background: #f1f5f9; border: 1px solid #e2e8f0; }
        .mday.mday-extra { width: 8px; margin-left: 2px; }
        .shift-chip.shift-chip-extra { margin-top: 3px; border-left-style: dashed; }
        .clockdot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #22c55e; margin-left: 4px; vertical-align: middle; }

        /* ============ single blue-gradient theme ============ */
        .grad-blue { background: linear-gradient(135deg, #0D6EFD, #0D6EFD); }
        .btn-grad {
            display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color: #fff; border: none;
            font-size: 12.5px; font-weight: 500; padding: 6px 16px; border-radius: 999px;
            cursor: pointer; transition: all .15s ease; text-decoration: none;
        }
        .btn-grad:hover { color: #fff; filter: brightness(1.08); box-shadow: 0 3px 10px rgba(13, 110, 253, 0.35); }

        .view-toggle { display: inline-flex; border: 1px solid #e2e8f0; border-radius: 999px; padding: 2px; background: #fff; }
        .view-toggle .btn {
            font-size: 12px; font-weight: 500; padding: 4px 16px; border: none !important; border-radius: 999px !important;
            background: transparent; color: #64748b;
        }
        .view-toggle .btn.active { background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color: #fff; box-shadow: 0 2px 6px rgba(13, 110, 253, .25); }
        .view-toggle .btn:not(.active):hover { background: #f1f5f9; color: #1e293b; }

        .quick-range { display: inline-flex; border: 1px solid #e2e8f0; border-radius: 999px; padding: 2px; background: #fff; }
        .quick-range .btn {
            font-size: 11.5px; font-weight: 500; padding: 4px 13px; border: none !important; border-radius: 999px !important;
            background: transparent; color: #64748b;
        }
        .quick-range .btn.active { background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color: #fff; }
        .quick-range .btn:not(.active):hover { background: #f1f5f9; color: #1e293b; }

        .btn-reset {
            display: inline-flex; align-items: center; gap: 5px;
            background: #fff; color: #64748b; border: 1px solid #e2e8f0;
            font-size: 12px; font-weight: 500; padding: 5px 14px; border-radius: 999px;
            cursor: pointer; transition: all .15s ease; text-decoration: none;
        }
        .btn-reset:hover { border-color: #cbd5e1; background: #f8fafc; color: #1e293b; }

        .rfilter input.form-control-sm, .rfilter select.form-control-sm {
            border-radius: 8px;
        }
        .rfilter input.form-control-sm:focus, .rfilter select.form-control-sm:focus {
            border-color: #0D6EFD; box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.08);
        }
        .search-box { position: relative; }
        .search-box i {
            position: absolute; left: 9px; top: 50%; transform: translateY(-50%);
            font-size: 12px; color: #94a3b8; pointer-events: none;
        }
        .search-box input { padding-left: 26px !important; }

        .legend { font-size: 10px; color: #64748b; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; padding: 6px 2px; }
        .legend .sw { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 4px; vertical-align: middle; }
        .rpager { padding: 6px 2px 0; }
        .rpager .pagination { margin: 0; }
        .rpager .page-link { font-size: 12px; padding: 3px 8px; }

        /* --- assign modal / list --- */
        .assign-section { border-top: 1px solid #edf2f7; padding-top: 12px; margin-top: 12px; }
        .assign-section:first-of-type { border-top: 0; padding-top: 0; margin-top: 0; }
        .assign-section h6 { font-weight: 700; color: #1e293b; margin-bottom: 3px; font-size: 12px; }
        .assign-section .hint { font-size: 11px; color: #94a3b8; margin-bottom: 8px; }
        /* Pill segmented control — same look as the Roster tab's Day/Week/Month toggle */
        .seg { display: inline-flex; flex-wrap: wrap; gap: 2px; border: 1px solid #e2e8f0; border-radius: 999px; padding: 2px; background: #fff; }
        .seg label { margin: 0; cursor: pointer; }
        .seg input { display: none; }
        .seg span {
            display: inline-block; padding: 5px 14px; border-radius: 999px;
            font-size: 11px; font-weight: 500; color: #64748b; transition: all .15s ease;
        }
        .seg label:hover span { background: #f1f5f9; color: #1e293b; }
        .seg input:checked + span, .seg input:checked + span:hover {
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color: #fff;
            box-shadow: 0 2px 6px rgba(13, 110, 253, .25);
        }
        .preview-line { background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; border-radius: 9px; padding: 7px 11px; font-size: 11px; }

        /* ============ Existing Assignments row actions — single blue theme, one row ============ */
        .row-actions { display: flex; align-items: center; justify-content: flex-end; gap: 4px; flex-wrap: nowrap; }
        .row-action-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; border-radius: 7px; flex-shrink: 0;
            border: 1px solid #e2e8f0; background: #fff; color: #64748b;
            transition: all .15s ease; cursor: pointer; text-decoration: none;
        }
        .row-action-btn i { font-size: 12px; }
        .row-action-btn:hover { border-color: #0D6EFD; color: #0D6EFD; background: var(--primary-light, #EFF6FF); }
        .row-action-btn.danger:hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }

        /* Status/Type badges — theme blue, not Bootstrap's default bg-dark/bg-info/etc. */
        .badge-theme { font-size: 10px; font-weight: 600; padding: 3px 9px; border-radius: 6px; display: inline-block; }
        .badge-theme.sm { font-size: 9px; padding: 2px 7px; }
        .badge-theme.upcoming { background: var(--primary-light, #EFF6FF); color: var(--primary, #0D6EFD); }
        .badge-theme.ongoing, .badge-theme.permanent, .badge-theme.active { background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color: #fff; }
        .badge-theme.complete, .badge-theme.flexible { background: var(--primary-light, #EFF6FF); color: var(--primary, #0D6EFD); }
        .badge-theme.superseded, .badge-theme.ended, .badge-theme.cancelled { background: #f1f5f9; color: #64748b; }
        .badge-theme.additional { background: #fef3c7; color: #92400e; }
        .error-text { display: block; font-size: 11px; }
        .wk-date-row { display: flex; gap: 6px; margin-bottom: 6px; }
        .shift-swatch { display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-right: 5px; }
        #assignList table { font-size: 12px; }
        #assignList th, #assignList td { padding: 5px 8px; }
    </style>
@endsection

@php
    $qs = fn($extra) => array_merge(request()->only(['date', 'department_id', 'shift_id', 'search']), $extra);
    $canManageShifts = in_array(Auth::user()->role, ['admin', 'hr'], true);
@endphp

@section('content-area')
    <div class="shift-roster">
        <x-ui.page-header class="content-area-header sticky-top" title="Shift Roster" current="Roster" :crumbs="[['label' => 'Shift']]">
            <x-slot:actions>
                <div class="hstack gap-2">
                    <a href="{{ route('shift.index') }}" class="btn-reset"><i class="feather-clock"></i>Manage Shifts</a>
                    <a href="#" class="btn-grad" data-bs-toggle="modal" data-bs-target="#assignShiftModal">
                        <i class="feather-user-check"></i>Assign Shift
                    </a>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="content-area-body">
            @if (session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif

            <div class="card stretch stretch-full">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs card-header-tabs m-0 px-2" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-roster">Roster</a></li>
                        @if ($canManageShifts)
                            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-assign" id="assignTabBtn">Existing Assignments</a></li>
                        @endif
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">

                        {{-- ============ TAB 1: Roster grid ============ --}}
                        <div class="tab-pane fade show active" id="tab-roster">
                            <form method="GET" action="{{ route('shift.roster') }}" class="rfilter mb-2" id="rosterFilterForm">
                                <div class="view-toggle" role="group">
                                    <a href="{{ route('shift.roster', $qs(['view' => 'day'])) }}" class="btn {{ $view === 'day' ? 'active' : '' }}">Day</a>
                                    <a href="{{ route('shift.roster', $qs(['view' => 'week'])) }}" class="btn {{ $view === 'week' ? 'active' : '' }}">Week</a>
                                    <a href="{{ route('shift.roster', $qs(['view' => 'month'])) }}" class="btn {{ $view === 'month' ? 'active' : '' }}">Month</a>
                                </div>
                                <input type="hidden" name="view" value="{{ $view }}">
                                <div>
                                    <input type="{{ $view === 'month' ? 'month' : 'date' }}" name="date" class="form-control form-control-sm"
                                           aria-label="{{ $view === 'day' ? 'Date' : ($view === 'week' ? 'Week of' : 'Month') }}"
                                           title="{{ $view === 'day' ? 'Date' : ($view === 'week' ? 'Week of' : 'Month') }}"
                                           value="{{ $view === 'month' ? $anchor->format('Y-m') : $anchor->format('Y-m-d') }}" onchange="this.form.submit()">
                                </div>
                                <div>
                                    <select name="department_id" class="form-control form-control-sm" aria-label="Department" onchange="this.form.submit()">
                                        <option value="">All Departments</option>
                                        @foreach ($departments as $d)
                                            <option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <select name="shift_id" class="form-control form-control-sm" aria-label="Shift" onchange="this.form.submit()">
                                        <option value="">All Shifts</option>
                                        @foreach ($shifts as $s)
                                            <option value="{{ $s->id }}" @selected($shiftFilter == $s->id)>{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="search-box">
                                    <i class="feather-search"></i>
                                    <input type="text" name="search" id="rosterSearch" class="form-control form-control-sm" style="width:170px" placeholder="Search name / ID" value="{{ request('search') }}">
                                </div>
                                <a href="{{ route('shift.roster', ['view' => $view]) }}" class="btn-reset">Reset</a>
                            </form>

                            {{-- <div class="legend">
                                @foreach ($shifts as $s)
                                    <span><span class="sw" style="background: {{ $s->color_code ?: '#4f46e5' }}"></span>{{ $s->name }}</span>
                                @endforeach
                                <span><span class="sw" style="background:#f1f5f9"></span>Week Off</span>
                                <span><span class="sw" style="background:#e2e8f0"></span>Unassigned</span>
                                <span><span class="clockdot"></span>Clocked in</span>
                            </div> --}}

                            <div class="roster-wrap">
                                <table class="roster-table">
                                    <thead>
                                        <tr>
                                            <th style="width:36px">#</th>
                                            <th>Employee</th>
                                            @foreach ($dates as $d)
                                                <th class="{{ $d->isWeekend() ? 'wknd' : '' }}">
                                                    @if ($view === 'month')
                                                        {{ $d->format('j') }}<br><span class="fw-normal" style="font-size:9px">{{ $d->format('D')[0] }}</span>
                                                    @else
                                                        {{ $d->format('D') }}<br><span class="fw-normal">{{ $d->format('d M') }}</span>
                                                    @endif
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($users as $user)
                                            @php
                                                $initials = strtoupper(mb_substr($user->name ?? 'NA', 0, 2));
                                                $desig = $user->jobDetails->designation->name ?? null;
                                                $dept = $user->jobDetails->department->name ?? null;
                                            @endphp
                                            <tr>
                                                <td class="text-muted">{{ ($users->currentPage() - 1) * $users->perPage() + $loop->iteration }}</td>
                                                <td>
                                                    <div class="employee-info">
                                                        <div class="employee-avatar"
                                                            style="background:linear-gradient(135deg, #0D6EFD, #0D6EFD);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">
                                                            {{ $initials }}</div>
                                                        <div class="employee-details">
                                                            <div class="employee-name">{{ $user->name }}</div>
                                                            <div class="employee-email">{{ $user->employee_id }}{{ $desig ? ' · ' . $desig : '' }}{{ $dept ? ' · ' . $dept : '' }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                @foreach ($dates as $d)
                                                    @php $cell = $cells[$user->id][$d->format('Y-m-d')] ?? ['type' => 'unassigned']; @endphp
                                                    <td class="text-center {{ $d->isWeekend() ? 'wknd' : '' }}">
                                                        @if ($view === 'month')
                                                            @if ($cell['type'] === 'shift')
                                                                <span class="mday" style="background: {{ $cell['color'] }}"
                                                                      title="{{ $cell['name'] }} {{ \Carbon\Carbon::parse($cell['start'])->format('h:i A') }}–{{ \Carbon\Carbon::parse($cell['end'])->format('h:i A') }}"></span>
                                                                @foreach ($cell['extra'] ?? [] as $x)
                                                                    <span class="mday mday-extra" style="background: {{ $x['color'] }}"
                                                                          title="+ {{ $x['name'] }} {{ \Carbon\Carbon::parse($x['start'])->format('h:i A') }}–{{ \Carbon\Carbon::parse($x['end'])->format('h:i A') }}"></span>
                                                                @endforeach
                                                            @elseif ($cell['type'] === 'weekoff')
                                                                <span class="mday off" title="Week Off"></span>
                                                            @else
                                                                <span class="mday" style="background:transparent"></span>
                                                            @endif
                                                        @else
                                                            @if ($cell['type'] === 'shift')
                                                                @php $muted = $shiftFilter && $shiftFilter != $cell['shift_id']; @endphp
                                                                <span class="shift-chip {{ $muted ? 'chip-muted' : '' }}" style="border-left-color: {{ $cell['color'] }}; background: {{ $cell['color'] }}22;">
                                                                    {{ $cell['name'] }}
                                                                    <span class="t">{{ \Carbon\Carbon::parse($cell['start'])->format('h:i A') }}&ndash;{{ \Carbon\Carbon::parse($cell['end'])->format('h:i A') }}</span>
                                                                </span>
                                                                @foreach ($cell['extra'] ?? [] as $x)
                                                                    @php $xMuted = $shiftFilter && $shiftFilter != $x['shift_id']; @endphp
                                                                    <span class="shift-chip shift-chip-extra {{ $xMuted ? 'chip-muted' : '' }}" title="Additional shift" style="border-left-color: {{ $x['color'] }}; background: {{ $x['color'] }}22;">
                                                                        + {{ $x['name'] }}
                                                                        <span class="t">{{ \Carbon\Carbon::parse($x['start'])->format('h:i A') }}&ndash;{{ \Carbon\Carbon::parse($x['end'])->format('h:i A') }}</span>
                                                                    </span>
                                                                @endforeach
                                                            @elseif ($cell['type'] === 'weekoff')
                                                                <span class="chip chip-weekoff">Week Off</span>
                                                            @else
                                                                <span class="chip chip-unassigned">&mdash;</span>
                                                            @endif
                                                            @if (!empty($cell['clocked_in']))<span class="clockdot" title="Clocked in"></span>@endif
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ $dates->count() + 2 }}" class="text-center py-4">
                                                    <span class="text-muted">No employees match the filters.</span>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if ($users->hasPages())
                                <div class="rpager d-flex justify-content-between align-items-center">
                                    <span class="text-muted" style="font-size:11px">Showing {{ $users->firstItem() }}&ndash;{{ $users->lastItem() }} of {{ $users->total() }} employees</span>
                                    <div>{{ $users->appends(request()->query())->links() }}</div>
                                </div>
                            @endif
                        </div>

                        {{-- ============ TAB 2: Existing Assignments (admin/hr) ============ --}}
                        @if ($canManageShifts)
                        <div class="tab-pane fade" id="tab-assign">
                            <form class="rfilter mb-2" id="listFilter">
                                <div class="search-box">
                                    <i class="feather-search"></i>
                                    <input type="text" class="form-control form-control-sm" id="f_search" style="width:170px" placeholder="Search name / ID">
                                </div>
                                {{-- <div>
                                    <select class="form-control form-control-sm" id="f_user" style="min-width:150px" aria-label="Employee">
                                        <option value="">All Employees</option>
                                        @foreach ($allUsers as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->employee_id }})</option>@endforeach
                                    </select>
                                </div> --}}
                                <div><input type="date" class="form-control form-control-sm" id="f_from" aria-label="From" title="From" value="{{ date('Y-m-01') }}"></div>
                                <div><input type="date" class="form-control form-control-sm" id="f_to" aria-label="To" title="To" value="{{ date('Y-m-t') }}"></div>
                                <div>
                                    <select class="form-control form-control-sm" id="f_shift" aria-label="Shift">
                                        <option value="">All Shifts</option>
                                        @foreach ($shifts as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                                    </select>
                                </div>
                                <div>
                                    <select class="form-control form-control-sm" id="f_status" aria-label="Status">
                                        <option value="">All Statuses</option>
                                        <option value="upcoming">Upcoming</option>
                                        <option value="ongoing">Ongoing</option>
                                        <option value="complete">Complete</option>
                                    </select>
                                </div>
                                <div>
                                    <select class="form-control form-control-sm" id="f_type" aria-label="Type">
                                        <option value="">All Types</option>
                                        <option value="permanent">Permanent</option>
                                        <option value="flexible">Flexible</option>
                                    </select>
                                </div>
                                <a href="#" class="btn-reset" id="listReset">Reset</a>
                                <div class="quick-range ms-auto" role="group">
                                    <button type="button" class="btn" data-range="today">Today</button>
                                    <button type="button" class="btn" data-range="this-week">Week</button>
                                    <button type="button" class="btn" data-range="this-month">Month</button>
                                    <button type="button" class="btn" data-range="next-month">Next</button>
                                </div>
                            </form>

                            <div id="bulkBar" class="alert alert-secondary d-none d-flex align-items-center gap-2 py-1 px-2 flex-wrap" style="font-size:12px">
                                <span><strong id="bulkCount">0</strong> selected</span>
                                <select class="form-control form-control-sm" id="bulkShift" style="width:auto">
                                    <option value="">Change shift…</option>
                                    @foreach ($shifts as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                                </select>
                                <select class="form-control form-control-sm" id="bulkStatus" style="width:auto">
                                    <option value="">Change status…</option>
                                    <option value="upcoming">Upcoming</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="complete">Complete</option>
                                </select>
                                <button class="btn btn-sm btn-primary" id="applyBulk">Apply</button>
                                <button class="btn btn-sm btn-outline-danger" id="bulkDelete">Delete</button>
                                <button class="btn btn-sm btn-light ms-auto" id="clearSel">Clear</button>
                                <button class="btn btn-sm btn-light" id="exportBtn"><i class="feather-download me-1"></i>CSV</button>
                            </div>

                            <div id="assignList" class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width:28px"><input type="checkbox" id="selectAll"></th>
                                            <th style="width:36px">#</th>
                                            <th>Employee</th><th>Shift</th><th>Date</th><th>Time</th><th>Status</th><th>Type</th><th>By</th><th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="listBody"><tr><td colspan="10" class="text-center py-3 text-muted">Loading…</td></tr></tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-between align-items-center rpager">
                                <span class="text-muted" style="font-size:11px" id="listInfo"></span>
                                <ul class="pagination pagination-sm mb-0" id="listPagination"></ul>
                            </div>
                        </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
@php $canManageShifts = in_array(Auth::user()->role, ['admin', 'hr'], true); @endphp
@if ($canManageShifts)
    {{-- Assign Shift (md, centered) --}}
    <div class="modal fade modal-custom" id="assignShiftModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" style="font-size:15px">Assign Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none py-2" id="assignFormError" style="font-size:12px"></div>
                    <form id="assignShiftForm">
                        @csrf
                        <div class="assign-section">
                            <h6>1. Type</h6>
                            <div class="seg mb-1">
                                <label><input type="radio" name="type" value="flexible" checked><span>Flexible</span></label>
                                <label><input type="radio" name="type" value="permanent"><span>Permanent</span></label>
                            </div>
                            <div class="hint" id="typeHint">Assign for specific dates, as needed — today's normal behaviour.</div>
                            <div class="alert alert-warning py-1 px-2 d-none mt-1" id="permanentConflictNotice" style="font-size:12px"></div>
                            <div class="form-check form-switch mt-2">
                                <input type="checkbox" class="form-check-input" name="is_additional" value="1" id="is_additional">
                                <label class="form-check-label" for="is_additional" style="font-size:12px">Add as additional shift (2nd shift on the same days — keeps the current shift)</label>
                            </div>
                        </div>

                        <div class="assign-section">
                            <h6>2. Employees</h6>
                            <div class="seg mb-2">
                                <label><input type="radio" name="assign_type" value="user" checked><span>Selected</span></label>
                                <label><input type="radio" name="assign_type" value="department"><span>Department</span></label>
                                <label><input type="radio" name="assign_type" value="all"><span>Everyone</span></label>
                            </div>
                            <div id="userSection">
                                <select class="form-control select2" name="user_ids[]" id="user_ids" multiple>
                                    @foreach ($allUsers as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->employee_id }})</option>@endforeach
                                </select>
                                <small class="text-danger error-text user_ids_error"></small>
                            </div>
                            <div id="departmentSection" style="display:none">
                                <select class="form-control" name="department_id" id="department_id">
                                    <option value="">Select department</option>
                                    @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                                </select>
                                <small class="text-danger error-text department_id_error"></small>
                                <div class="hint mt-1" id="deptCount"></div>
                            </div>
                        </div>

                        <div class="assign-section">
                            <h6>3. Shift(s)</h6>
                            <select class="form-control" name="shift_ids[]" id="shift_id" multiple>
                                @foreach ($shifts as $s)
                                    <option value="{{ $s->id }}" data-color="{{ $s->color_code }}" data-start="{{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}">
                                        {{ $s->name }} ({{ \Carbon\Carbon::parse($s->start_time)->format('h:i A') }}&ndash;{{ \Carbon\Carbon::parse($s->end_time)->format('h:i A') }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-danger error-text shift_id_error"></small>
                            <small class="text-danger error-text shift_ids_error"></small>
                            <div class="hint mt-1" id="shiftPickHint">Pick one or more shifts. With two or more, the one that starts earliest is the main shift and the others are added as additional shifts on the same days.</div>
                        </div>

                        <div class="assign-section">
                            <h6>4. Dates <span class="hint" id="datesHint">(max 90 days)</span></h6>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label" id="startDateLabel">Start <span class="text-danger">*</span></label>
                                    <input type="date" name="start_date" id="start_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                    <small class="text-danger error-text start_date_error"></small>
                                </div>
                                <div class="col-6" id="endDateCol">
                                    <label class="form-label">End</label>
                                    <input type="date" name="end_date" id="end_date" class="form-control">
                                    <small class="text-danger error-text end_date_error"></small>
                                </div>
                            </div>
                            <div class="form-check mt-2" id="singleDayRow">
                                <input type="checkbox" class="form-check-input" id="singleDay">
                                <label class="form-check-label" for="singleDay">Single day only</label>
                            </div>
                        </div>

                        <div class="assign-section" id="weekOffSection">
                            <h6>5. Weekly offs <span class="hint">(optional)</span></h6>
                            <div class="seg mb-2">
                                <label><input type="radio" name="week_off_type" value="" checked><span>None</span></label>
                                <label><input type="radio" name="week_off_type" value="day_based"><span>Weekly days</span></label>
                                <label><input type="radio" name="week_off_type" value="date_based"><span>Dates</span></label>
                            </div>
                            <div id="dayBasedSection" style="display:none">
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($weekdays as $day)
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="week_off_days[]" value="{{ $day }}" id="wd_{{ $day }}">
                                            <label class="form-check-label" for="wd_{{ $day }}" style="font-size:12px">{{ \Illuminate\Support\Str::substr($day, 0, 3) }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div id="dateBasedSection" style="display:none">
                                <div id="dateBasedContainer">
                                    <div class="wk-date-row">
                                        <input type="date" name="week_off_dates[]" class="form-control">
                                        <button type="button" class="btn btn-outline-secondary btn-sm add-date"><i class="feather-plus"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="assign-section" id="optionsSection">
                            <h6>6. Options</h6>
                            <div class="form-check form-switch mb-1">
                                <input type="checkbox" class="form-check-input" name="override_existing" value="1" id="override_existing">
                                <label class="form-check-label" for="override_existing" style="font-size:12px">Replace shifts already assigned on these dates</label>
                            </div>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="apply_to_future_only" value="1" id="apply_to_future_only">
                                <label class="form-check-label" for="apply_to_future_only" style="font-size:12px">Only touch today &amp; future dates</label>
                            </div>
                        </div>

                        <div class="assign-section">
                            <div class="preview-line" id="previewLine">Fill the form to see a summary.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="assignShiftForm" class="btn-grad">Assign Shift</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit assignment --}}
    <div class="modal fade modal-custom" id="editAssignedModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2"><h5 class="modal-title" style="font-size:15px">Edit Assignment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="editAssignedForm">
                        @csrf
                        <input type="hidden" name="user_shift_id" id="ea_id">
                        <div class="mb-2"><label class="form-label">Shift</label>
                            <select class="form-control" name="shift_id" id="ea_shift">@foreach ($shifts as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
                        </div>
                        <div class="mb-2"><label class="form-label">Status</label>
                            <select class="form-control" name="status" id="ea_status">
                                <option value="upcoming">Upcoming</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="complete">Complete</option>
                            </select>
                        </div>
                        <div class="mb-1"><label class="form-label">Reason (optional)</label><textarea class="form-control" name="reason" id="ea_reason" rows="2"></textarea></div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editAssignedForm" class="btn btn-primary btn-sm">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete assignment --}}
    <div class="modal fade modal-custom" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2"><h5 class="modal-title" style="font-size:15px">Delete Assignment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><p class="mb-0">Delete this assignment?</p></div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    {{-- End a Permanent assignment --}}
    <div class="modal fade modal-custom" id="endPermanentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2"><h5 class="modal-title" style="font-size:15px">End Permanent Shift</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="mb-2" style="font-size:13px">The employee reverts to whatever shift (if any) applies after this date. The assignment stays in history.</p>
                    <label class="form-label">End date</label>
                    <input type="date" class="form-control" id="ep_end_date" value="{{ date('Y-m-d') }}">
                    <label class="form-label mt-2">Reason (optional)</label>
                    <textarea class="form-control" id="ep_reason" rows="2"></textarea>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning btn-sm" id="confirmEndPermanent">End Shift</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Assignment history for one employee --}}
    <div class="modal fade modal-custom" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2"><h5 class="modal-title" style="font-size:15px">Shift Assignment History</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Type</th><th>Shift</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
                            <tbody id="historyBody"><tr><td colspan="5" class="text-center text-muted py-3">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@section('script-area')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        $(function () {
            toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 3500 };
            @if (session('success')) toastr.success(@json(session('success'))); @endif
            const csrf = $('meta[name="csrf-token"]').attr('content');

            $('#user_ids').select2({ placeholder: 'Select employees', allowClear: true, width: '100%', dropdownParent: $('#assignShiftModal') });
            $('#shift_id').select2({ placeholder: 'Select one or more shifts', allowClear: true, width: '100%', closeOnSelect: false, dropdownParent: $('#assignShiftModal') });

            // Roster tab search box — no button; auto-submits the (real, page-navigating) filter form shortly after typing stops.
            let rosterSearchTimer = null;
            $('#rosterSearch').on('input', function () {
                clearTimeout(rosterSearchTimer);
                rosterSearchTimer = setTimeout(function () { $('#rosterFilterForm').trigger('submit'); }, 600);
            });

            /* ---------- assign modal ---------- */
            function syncAssignType() {
                const t = $('input[name="assign_type"]:checked').val();
                $('#userSection').toggle(t === 'user'); $('#departmentSection').toggle(t === 'department'); updatePreview();
            }
            $('input[name="assign_type"]').on('change', syncAssignType);

            function syncType() {
                const isPermanent = $('input[name="type"]:checked').val() === 'permanent';
                const isAdditional = $('#is_additional').is(':checked');
                $('#endDateCol,#singleDayRow').toggle(!isPermanent);
                // An additional shift never replaces anything and leaves week-offs alone.
                $('#optionsSection').toggle(!isPermanent && !isAdditional);
                $('#weekOffSection').toggle(!isAdditional);
                $('#datesHint').text(isPermanent ? '' : '(max 90 days)');
                $('#typeHint').text(isPermanent
                    ? 'The employee keeps this shift every day until it is changed or ended — no need to reassign it later.'
                    : 'Assign for specific dates, as needed — today\'s normal behaviour.');
                if (isPermanent) { $('#end_date').val('').prop('disabled', true); $('#singleDay').prop('checked', false); }
                else { $('#end_date').prop('disabled', $('#singleDay').is(':checked')); }
                checkPermanentConflicts();
                updatePreview();
            }
            $('input[name="type"]').on('change', syncType);
            $('#is_additional').on('change', syncType);

            let conflictCheckTimer = null;
            function checkPermanentConflicts() {
                const $notice = $('#permanentConflictNotice').addClass('d-none').empty();
                if ($('input[name="type"]:checked').val() !== 'permanent' || $('#is_additional').is(':checked')) return;

                const userIds = $('input[name="assign_type"]:checked').val() === 'user' ? ($('#user_ids').val() || []) : [];
                if (!userIds.length) return;

                clearTimeout(conflictCheckTimer);
                conflictCheckTimer = setTimeout(() => {
                    $.ajax({
                        url: "{{ route('shift.assignment-conflicts') }}", type: 'POST',
                        data: { user_ids: userIds }, headers: { 'X-CSRF-TOKEN': csrf },
                        success: r => {
                            if (!r.status || !r.data.length) return;
                            const lines = r.data.map(c => (c.shift_name || 'a shift') + ' (active since ' + c.active_since + ')');
                            $notice.removeClass('d-none').html('<i class="feather-alert-triangle me-1"></i>This will replace the current permanent shift for ' +
                                r.data.length + ' employee(s): ' + lines.slice(0, 3).join(', ') + (lines.length > 3 ? '…' : '') + '.');
                        }
                    });
                }, 300);
            }
            $('#user_ids').on('change', checkPermanentConflicts);
            $('#start_date').on('change', checkPermanentConflicts);
            $('#department_id').on('change', function () {
                const id = $(this).val(); $('#deptCount').text('');
                if (id) $.get("{{ route('shift.get-users-by-type') }}", { type: 'department', department_id: id }, r => {
                    if (r.status) $('#deptCount').text((r.data?.length || 0) + ' employees in this department');
                });
                updatePreview();
            });
            $('#singleDay').on('change', function () { $('#end_date').prop('disabled', this.checked); if (this.checked) $('#end_date').val(''); updatePreview(); });
            function syncWeekOff() {
                const t = $('input[name="week_off_type"]:checked').val();
                $('#dayBasedSection').toggle(t === 'day_based'); $('#dateBasedSection').toggle(t === 'date_based'); updatePreview();
            }
            $('input[name="week_off_type"]').on('change', syncWeekOff);
            $(document).on('click', '.add-date', function () {
                $('#dateBasedContainer').append('<div class="wk-date-row"><input type="date" name="week_off_dates[]" class="form-control">' +
                    '<button type="button" class="btn btn-outline-danger btn-sm remove-date"><i class="feather-x"></i></button></div>');
            });
            $(document).on('click', '.remove-date', function () { $(this).closest('.wk-date-row').remove(); updatePreview(); });
            function updatePreview() {
                // Several shifts: the earliest start is the main one (same order the server uses).
                const picked = $('#shift_id option:selected').map((i, el) => ({ name: $(el).text().trim(), start: $(el).data('start') || '' })).get()
                    .sort((a, b) => a.start.localeCompare(b.start));
                const extraAll = $('#is_additional').is(':checked');
                const shiftTxt = !picked.length ? '(no shift)'
                    : picked.length === 1 ? picked[0].name
                    : picked.map((p, i) => p.name + (extraAll || i > 0 ? ' [additional]' : ' [main]')).join(' + ');
                const t = $('input[name="assign_type"]:checked').val();
                let who = t === 'user' ? ($('#user_ids').val() || []).length + ' employee(s)'
                        : t === 'department' ? ($('#department_id option:selected').text() || 'department') + ' dept' : 'everyone';
                const start = $('#start_date').val() || '—';
                let offTxt = ''; const wt = $('input[name="week_off_type"]:checked').val();
                if (wt === 'day_based') { const d = $('input[name="week_off_days[]"]:checked').map((i, el) => el.value).get(); if (d.length) offTxt = ', skipping ' + d.join(', '); }
                else if (wt === 'date_based') { const n = $('input[name="week_off_dates[]"]').filter((i, el) => el.value).length; if (n) offTxt = ', skipping ' + n + ' date(s)'; }

                const extra = $('#is_additional').is(':checked');
                if (extra) offTxt = ' as an additional shift (current shift stays)';
                if ($('input[name="type"]:checked').val() === 'permanent') {
                    $('#previewLine').text('Assign ' + shiftTxt + ' to ' + who + ' permanently, starting ' + start + ' — ongoing until changed or ended' + offTxt + '.');
                    return;
                }
                const end = $('#singleDay').is(':checked') ? start : ($('#end_date').val() || start);
                $('#previewLine').text('Assign ' + shiftTxt + ' to ' + who + ', ' + start + ' → ' + end + offTxt + '.');
            }
            $('#assignShiftForm').on('input change', updatePreview);
            syncAssignType(); syncType(); syncWeekOff(); updatePreview();

            $('#assignShiftForm').on('submit', function (e) {
                e.preventDefault();
                $('.error-text').text(''); $('#assignFormError').addClass('d-none').text('');
                const $btn = $('button[form="assignShiftForm"]').prop('disabled', true).text('Assigning…');
                $.ajax({
                    url: "{{ route('shift.assign') }}", type: 'POST', data: $(this).serialize(), headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { toastr.success(r.message || 'Assigned'); setTimeout(() => location.reload(), 900); },
                    error: xhr => {
                        $btn.prop('disabled', false).text('Assign Shift');
                        if (xhr.status === 422 && xhr.responseJSON?.errors) $.each(xhr.responseJSON.errors, (k, v) => $('.' + k.split('.')[0] + '_error').text(v[0]));
                        else $('#assignFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Assignment failed.');
                    }
                });
            });

            /* ---------- assignments list (lazy) ---------- */
            let page = 1, perPage = 25, deleteId = null, listLoaded = false;

            function statusBadge(s) {
                return '<span class="badge-theme ' + (s || '') + '">' + (s || '—') + '</span>';
            }
            const fmtDate = d => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
            const fmtTime = t => { if (!t) return ''; const [h, m] = t.split(':'); const H = +h % 12 || 12; return H + ':' + m + ' ' + (+h < 12 ? 'AM' : 'PM'); };

            function loadList() {
                const params = {
                    from_date: $('#f_from').val(), to_date: $('#f_to').val(), user_id: $('#f_user').val() || '',
                    shift_id: $('#f_shift').val() || '', status: $('#f_status').val() || '', type: $('#f_type').val() || '',
                    search: $('#f_search').val() || '',
                    page, per_page: perPage
                };
                $('#listBody').html('<tr><td colspan="10" class="text-center py-3 text-muted">Loading…</td></tr>');
                $.get("{{ route('shift.user-shifts.data') }}", params, r => {
                    if (!r.status) return;
                    renderRows(r.data); renderPager(r.pagination);
                    $('#listInfo').text('Showing ' + (r.pagination.from || 0) + '–' + (r.pagination.to || 0) + ' of ' + r.pagination.total);
                }).fail(x => toastr.error(x.responseJSON?.message || 'Failed to load assignments'));
            }
            function typeBadge(sa) {
                if (!sa) return '<span class="text-muted small">—</span>';
                const label = sa.type === 'permanent' ? 'Permanent' : 'Flexible';
                return '<span class="badge-theme ' + sa.type + '">' + label + '</span> ' +
                    '<span class="badge-theme sm ' + (sa.status || '') + '">' + (sa.status || '') + '</span>';
            }
            function renderRows(rows) {
                if (!rows.length) { $('#listBody').html('<tr><td colspan="10" class="text-center py-3 text-muted">No assignments for these filters.</td></tr>'); return; }
                $('#listBody').html(rows.map((r, i) => {
                    const color = r.shift?.color_code || '#4f46e5';
                    const sa = r.shift_assignment;
                    const canEndPermanent = sa && sa.type === 'permanent' && sa.status === 'active';
                    const srNo = (page - 1) * perPage + i + 1;
                    return '<tr>' +
                        '<td><input type="checkbox" class="row-sel" value="' + r.id + '" data-shift="' + (r.shift_id || '') + '"></td>' +
                        '<td class="text-muted">' + srNo + '</td>' +
                        '<td>' + (r.user?.name || '—') + ' <span class="text-muted small">' + (r.user?.employee_id || '') + '</span></td>' +
                        '<td><span class="shift-swatch" style="background:' + color + '"></span>' + (r.shift?.name || '—') + '</td>' +
                        '<td>' + fmtDate(r.date) + '</td>' +
                        '<td>' + fmtTime(r.shift?.start_time) + ' – ' + fmtTime(r.shift?.end_time) + '</td>' +
                        '<td>' + statusBadge(r.status) + '</td>' +
                        '<td>' + typeBadge(sa) + (r.is_additional ? ' <span class="badge-theme sm additional" title="2nd+ shift on this day">Additional</span>' : '') + '</td>' +
                        '<td>' + (r.created_by ? 'Admin' : 'System') + '</td>' +
                        '<td class="text-end">' +
                            '<div class="row-actions">' +
                            (canEndPermanent ? '<a href="#" class="row-action-btn end-permanent" data-id="' + sa.id + '" title="End Permanent Shift"><i class="feather-square"></i></a>' : '') +
                            '<a href="#" class="row-action-btn view-history" data-user="' + (r.user_id || '') + '" title="History"><i class="feather-clock"></i></a>' +
                            '<a href="#" class="row-action-btn edit-assigned" data-id="' + r.id + '" data-shift="' + (r.shift_id || '') + '" data-status="' + (r.status || '') + '" title="Edit"><i class="feather-edit-2"></i></a>' +
                            '<a href="#" class="row-action-btn danger del-assigned" data-id="' + r.id + '" title="Delete"><i class="feather-trash-2"></i></a>' +
                            '</div>' +
                        '</td></tr>';
                }).join(''));
            }
            function renderPager(p) {
                const $ul = $('#listPagination').empty();
                if (!p || p.last_page <= 1) return;
                const li = (label, pg, dis, act) => '<li class="page-item ' + (dis ? 'disabled' : '') + ' ' + (act ? 'active' : '') + '"><a class="page-link" href="#" data-pg="' + pg + '">' + label + '</a></li>';
                $ul.append(li('«', p.current_page - 1, p.current_page === 1));
                for (let i = 1; i <= p.last_page; i++) {
                    if (i === 1 || i === p.last_page || Math.abs(i - p.current_page) <= 2) $ul.append(li(i, i, false, i === p.current_page));
                    else if (Math.abs(i - p.current_page) === 3) $ul.append('<li class="page-item disabled"><span class="page-link">…</span></li>');
                }
                $ul.append(li('»', p.current_page + 1, p.current_page === p.last_page));
            }
            $(document).on('click', '#listPagination .page-link', function (e) { e.preventDefault(); const pg = +$(this).data('pg'); if (pg >= 1) { page = pg; loadList(); } });

            // Filters auto-apply — no Apply button. Text search is debounced;
            // everything else (dropdowns, dates) applies immediately on change.
            $('#listFilter').on('submit', function (e) { e.preventDefault(); page = 1; loadList(); });
            $('#f_user,#f_shift,#f_status,#f_type,#f_from,#f_to').on('change', function () { page = 1; loadList(); });
            let listSearchTimer = null;
            $('#f_search').on('input', function () {
                clearTimeout(listSearchTimer);
                listSearchTimer = setTimeout(function () { page = 1; loadList(); }, 400);
            });

            $('#listReset').on('click', function (e) {
                e.preventDefault();
                $('#f_user,#f_shift,#f_status,#f_type,#f_search').val('');
                $('#f_from').val('{{ date('Y-m-01') }}'); $('#f_to').val('{{ date('Y-m-t') }}');
                $('.quick-range .btn').removeClass('active');
                page = 1; loadList();
            });
            $('#listFilter [data-range]').on('click', function () {
                const now = new Date(); let from, to; const iso = d => d.toISOString().slice(0, 10);
                switch ($(this).data('range')) {
                    case 'today': from = to = iso(now); break;
                    case 'this-week': { const s = new Date(now); s.setDate(now.getDate() - ((now.getDay() + 6) % 7)); const e = new Date(s); e.setDate(s.getDate() + 6); from = iso(s); to = iso(e); break; }
                    case 'this-month': from = iso(new Date(now.getFullYear(), now.getMonth(), 1)); to = iso(new Date(now.getFullYear(), now.getMonth() + 1, 0)); break;
                    case 'next-month': from = iso(new Date(now.getFullYear(), now.getMonth() + 1, 1)); to = iso(new Date(now.getFullYear(), now.getMonth() + 2, 0)); break;
                }
                $('#listFilter [data-range]').removeClass('active');
                $(this).addClass('active');
                $('#f_from').val(from); $('#f_to').val(to); page = 1; loadList();
            });
            const selectedIds = () => $('.row-sel:checked').map((i, el) => +el.value).get();
            function syncBulk() { const n = selectedIds().length; $('#bulkBar').toggleClass('d-none', n === 0); $('#bulkCount').text(n); }
            $(document).on('change', '.row-sel', syncBulk);
            $('#selectAll').on('change', function () { $('.row-sel').prop('checked', this.checked); syncBulk(); });
            $('#clearSel').on('click', function () { $('.row-sel,#selectAll').prop('checked', false); syncBulk(); });
            $('#applyBulk').on('click', function () {
                const ids = selectedIds(); if (!ids.length) return;
                const shift = $('#bulkShift').val(), status = $('#bulkStatus').val();
                if (!shift && !status) { toastr.warning('Pick a shift or status'); return; }
                const rows = ids.map(id => { const $c = $('.row-sel[value="' + id + '"]'); return { id, shift_id: shift || $c.data('shift'), status: status || 'upcoming' }; });
                $.ajax({ url: "{{ route('shift.user-shifts.bulk-update') }}", type: 'POST', data: { user_shifts: rows }, headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { toastr.success(r.message || 'Updated'); loadList(); }, error: x => toastr.error(x.responseJSON?.message || 'Bulk update failed') });
            });
            $('#bulkDelete').on('click', function () {
                const ids = selectedIds(); if (!ids.length) return;
                if (!confirm('Delete ' + ids.length + ' assignment(s)?')) return;
                Promise.all(ids.map(id => $.ajax({ url: "{{ route('shift.destroy-assigned', '') }}/" + id, type: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf } })))
                    .then(() => { toastr.success('Deleted'); loadList(); }).catch(() => { toastr.error('Some deletions failed'); loadList(); });
            });
            $('#exportBtn').on('click', function () {
                window.location = "{{ route('shift.user-shifts.export') }}?" + $.param({ from_date: $('#f_from').val(), to_date: $('#f_to').val(), user_id: $('#f_user').val() || '' });
            });
            $(document).on('click', '.edit-assigned', function (e) {
                e.preventDefault();
                $('#ea_id').val($(this).data('id')); $('#ea_shift').val($(this).data('shift'));
                $('#ea_status').val($(this).data('status') || 'upcoming'); $('#ea_reason').val('');
                new bootstrap.Modal('#editAssignedModal').show();
            });
            $('#editAssignedForm').on('submit', function (e) {
                e.preventDefault();
                $.ajax({ url: "{{ route('shift.update-user-shift') }}", type: 'POST', data: $(this).serialize(), headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { bootstrap.Modal.getInstance(document.getElementById('editAssignedModal')).hide(); toastr.success(r.message || 'Updated'); loadList(); },
                    error: x => toastr.error(x.responseJSON?.message || 'Update failed') });
            });
            $(document).on('click', '.del-assigned', function (e) { e.preventDefault(); deleteId = $(this).data('id'); new bootstrap.Modal('#deleteModal').show(); });
            $('#confirmDelete').on('click', function () {
                if (!deleteId) return;
                $.ajax({ url: "{{ route('shift.destroy-assigned', '') }}/" + deleteId, type: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide(); toastr.success(r.message || 'Deleted'); loadList(); },
                    error: x => toastr.error(x.responseJSON?.message || 'Delete failed') });
            });

            /* ---------- end permanent / history ---------- */
            let endPermanentId = null;
            $(document).on('click', '.end-permanent', function (e) {
                e.preventDefault(); endPermanentId = $(this).data('id');
                $('#ep_end_date').val(new Date().toISOString().slice(0, 10)); $('#ep_reason').val('');
                new bootstrap.Modal('#endPermanentModal').show();
            });
            $('#confirmEndPermanent').on('click', function () {
                if (!endPermanentId) return;
                $.ajax({
                    url: "{{ url('shift/assignments') }}/" + endPermanentId + '/end-permanent', type: 'POST',
                    data: { end_date: $('#ep_end_date').val(), reason: $('#ep_reason').val() }, headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { bootstrap.Modal.getInstance(document.getElementById('endPermanentModal')).hide(); toastr.success(r.message || 'Ended'); loadList(); },
                    error: x => toastr.error(x.responseJSON?.message || 'Failed to end permanent shift')
                });
            });

            function historyTypeLabel(t) { return t === 'permanent' ? 'Permanent' : 'Flexible'; }
            $(document).on('click', '.view-history', function (e) {
                e.preventDefault();
                const userId = $(this).data('user');
                $('#historyBody').html('<tr><td colspan="5" class="text-center text-muted py-3">Loading…</td></tr>');
                new bootstrap.Modal('#historyModal').show();
                if (!userId) return;
                $.get("{{ route('shift.assignments.history') }}", { user_id: userId }, r => {
                    if (!r.status || !r.data.length) { $('#historyBody').html('<tr><td colspan="5" class="text-center text-muted py-3">No history yet.</td></tr>'); return; }
                    $('#historyBody').html(r.data.map(h => '<tr>' +
                        '<td>' + historyTypeLabel(h.type) + '</td>' +
                        '<td>' + (h.shift?.name || '—') + '</td>' +
                        '<td>' + fmtDate(h.start_date) + '</td>' +
                        '<td>' + (h.end_date ? fmtDate(h.end_date) : (h.type === 'permanent' && h.status === 'active' ? 'Ongoing' : '—')) + '</td>' +
                        '<td>' + statusBadge2(h.status) + '</td>' +
                    '</tr>').join(''));
                }).fail(() => $('#historyBody').html('<tr><td colspan="5" class="text-center text-danger py-3">Failed to load history.</td></tr>'));
            });
            function statusBadge2(s) {
                return '<span class="badge-theme ' + (s || '') + '">' + (s || '—') + '</span>';
            }

            // Lazy-load the assignments list the first time its tab is opened.
            $('#assignTabBtn').on('shown.bs.tab', function () { if (!listLoaded) { listLoaded = true; loadList(); } });
        });
    </script>
@endsection
