@extends('client.layout.master')

@section('style')
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <style>
        /* ==================== FILTER SECTION — same design as the Task
           "Assigned By Me" page (view-assigned-by-task.blade.php), adapted
           to Meeting's actual fields (search/status/date range). ==================== */
        .filter-wrapper {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .02);
        }
        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .filter-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }
        .filter-title i {
            color: var(--primary);
            font-size: 16px;
        }
        .filter-title span {
            background: var(--primary-light);
            color: var(--primary);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 6px;
        }
        .clear-all-link {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 12px;
            text-decoration: none;
            padding: 4px 10px;
            border-radius: 20px;
            transition: all .2s;
        }
        .clear-all-link:hover {
            background: var(--danger-light);
            color: var(--danger);
        }
        .clear-all-link i {
            font-size: 14px;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
        }
        .filter-item {
            flex: 0 0 auto;
            min-width: 160px;
        }
        .filter-item.date-range {
            min-width: 140px;
        }
        .filter-item .form-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 4px;
            display: block;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .filter-select,
        .filter-input {
            width: 100%;
            height: 36px;
            padding: 6px 28px 6px 10px;
            font-size: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface-2) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 8px center;
            background-size: 14px;
            appearance: none;
            cursor: pointer;
            transition: all .2s;
        }
        .filter-input {
            background-image: none;
            padding: 6px 10px;
            cursor: text;
        }
        .filter-select:focus,
        .filter-input:focus {
            background-color: var(--surface);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, .1);
            outline: none;
        }
        .filter-select:hover,
        .filter-input:hover {
            background-color: var(--surface);
            border-color: var(--gray-400, #94a3b8);
        }
        .filter-date {
            width: 100%;
            height: 36px;
            padding: 6px 10px;
            font-size: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface-2);
            cursor: pointer;
            transition: all .2s;
        }
        .filter-date:focus {
            background-color: var(--surface);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, .1);
            outline: none;
        }
        .reset-btn {
            height: 36px;
            padding: 0 16px;
            background: var(--surface);
            color: var(--text-muted);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all .2s;
            white-space: nowrap;
        }
        .reset-btn:hover {
            background: var(--surface-2);
            border-color: var(--gray-400, #94a3b8);
            color: var(--text-primary);
        }
        .active-filters {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px dashed var(--border);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .active-filters-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .3px;
            background: var(--surface-3);
            padding: 2px 8px;
            border-radius: 20px;
        }
        .filter-tag {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 3px 10px 3px 8px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all .2s;
            color: var(--text-secondary);
            text-decoration: none;
        }
        .filter-tag i {
            color: var(--primary);
            font-size: 11px;
        }
        .filter-tag .remove-tag {
            color: var(--gray-400, #94a3b8);
            margin-left: 2px;
            cursor: pointer;
            transition: color .2s;
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }
        .filter-tag .remove-tag:hover {
            color: var(--danger);
        }
        .filter-tag.clear-all {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
            font-weight: 600;
            padding: 3px 10px;
        }
        .filter-tag.clear-all:hover {
            background: var(--primary);
            color: #fff;
        }
        @media (max-width: 992px) {
            .filter-row { gap: 10px; }
            .filter-item { flex: 1 1 calc(33.333% - 10px); min-width: 140px; }
        }
        @media (max-width: 768px) {
            .filter-wrapper { padding: 12px; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-item { width: 100%; }
        }
        .meetings-table td, .meetings-table th {
            vertical-align: middle;
        }
        .meetings-table .title-link {
            font-weight: 600;
            color: var(--text-primary);
            text-decoration: none;
        }
        .meetings-table .title-link:hover {
            color: var(--primary-mid);
        }
        .meetings-table .meeting-code {
            font-size: 11px;
            color: var(--text-muted);
            font-family: monospace;
        }
        .type-icon-sm {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .type-icon-sm.physical { background: var(--primary-light); color: var(--primary-mid); }
        .type-icon-sm.virtual { background: var(--success-light); color: var(--success); }
        .type-icon-sm.hybrid { background: var(--purple-light); color: var(--purple); }

        /* Meeting drawers: 480px + slightly taller header than the shared
           .ui-drawer default (8px 14px), scoped to just these two drawers
           so Task/Leave/Attendance drawers elsewhere are unaffected. */
        #meetingDrawer .offcanvas-header,
        #meetingEditDrawer .offcanvas-header {
            padding: 16px 14px;
        }
        #meetingDrawer .form-group,
        #meetingEditDrawer .form-group {
            margin-bottom: 10px;
        }
        #meetingDrawer label,
        #meetingEditDrawer label {
            font-size: 11px;
            font-weight: 600;
            color: #1a2236;
        }
        #meetingDrawer .form-control,
        #meetingEditDrawer .form-control {
            font-size: 11.5px;
            padding: 6px 10px;
            border-radius: 7px;
            border: 1px solid #dfe5f0;
        }
        #meetingDrawer .form-control:focus,
        #meetingEditDrawer .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 .15rem rgba(30, 58, 138, .12);
        }

        /* Schedule Meeting button — a small lift/shadow on hover, matching
           the original pre-rebuild page's .btn-new-meeting treatment,
           reapplied scoped here rather than touching .btn-primary globally. */
        .btn-schedule-meeting {
            padding: 7px 18px;
            box-shadow: 0 1px 3px rgba(30, 58, 138, .15);
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .btn-schedule-meeting:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(30, 58, 138, .3);
        }

        /* Meeting Details / MOM drawers */
        #meetingShowDrawer .offcanvas-header,
        #meetingMomDrawer .offcanvas-header {
            padding: 16px 14px;
        }
        /* Meeting Details drawer — smaller font, tighter padding/margins
           than the shared .meeting-info-*/.task-item-* defaults (which stay
           as-is for the full-page fallback at meetings/show/{id}). */
        #meetingShowDrawerBody .card {
            margin-bottom: 10px;
            box-shadow: none;
            border: 1px solid var(--border);
        }
        #meetingShowDrawerBody .card-header {
            padding: 8px 12px;
        }
        #meetingShowDrawerBody .card-title {
            font-size: 12.5px;
            font-weight: 700;
        }
        #meetingShowDrawerBody .card-body {
            padding: 10px 12px;
            font-size: 11.5px;
        }
        #meetingShowDrawerBody .meeting-info-grid {
            gap: 10px;
        }
        #meetingShowDrawerBody .meeting-info-item {
            gap: 7px;
        }
        #meetingShowDrawerBody .meeting-info-icon {
            width: 24px;
            height: 24px;
            font-size: 11px;
        }
        #meetingShowDrawerBody .meeting-info-label {
            font-size: 9.5px;
            margin-bottom: 1px;
        }
        #meetingShowDrawerBody .meeting-info-value {
            font-size: 11.5px;
        }
        #meetingShowDrawerBody .meeting-description-box,
        #meetingShowDrawerBody .mom-content-box {
            padding: 8px 10px;
            font-size: 11.5px;
        }
        #meetingShowDrawerBody .participant-badge,
        #meetingShowDrawerBody .writer-badge {
            padding: 3px 8px;
            font-size: 10.5px;
            gap: 4px;
            margin: 0 4px 4px 0;
        }
        #meetingShowDrawerBody .task-item-card {
            margin-bottom: 8px;
        }
        #meetingShowDrawerBody .task-item-header {
            padding: 8px 10px;
        }
        #meetingShowDrawerBody .task-item-body {
            padding: 8px 10px;
        }
        #meetingShowDrawerBody .task-meta-grid {
            gap: 8px;
            margin-bottom: 8px;
            font-size: 10.5px;
        }
        #meetingShowDrawerBody .task-meta-label {
            font-size: 9px;
        }
        #meetingShowDrawerBody .priority-badge {
            padding: 1px 7px;
            font-size: 9px;
        }
        #meetingShowDrawerBody .attendance-row {
            padding: 4px 0;
            font-size: 11px;
        }
        #meetingShowDrawerBody .history-item {
            padding: 6px 0;
            font-size: 10.5px;
        }
        #meetingShowDrawerBody .status-badge {
            font-size: 9.5px;
            padding: 2px 8px;
        }
        #meetingShowDrawerBody .btn-sm {
            font-size: 11px;
            padding: 4px 10px;
        }
        #meetingShowDrawerBody .gap-2 {
            gap: .35rem !important;
        }
        #meetingShowDrawerBody .mb-3 {
            margin-bottom: .6rem !important;
        }
        #meetingShowDrawer .offcanvas-body {
            padding: 8px 12px;
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Meeting Management">
        <x-slot:actions>
            <button type="button" class="btn btn-primary btn-sm btn-schedule-meeting" data-bs-toggle="offcanvas" data-bs-target="#meetingDrawer">
                <i class="feather-plus-circle me-1"></i> Schedule Meeting
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="feather-check-circle me-1"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="feather-alert-circle me-1"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Stat cards — same .stats-grid/.stats-card pattern as the Task
             "Assigned By Me" page; clicking one filters the table. --}}
        <div class="stats-grid">
            <div class="stats-card" data-status="">
                <div class="stats-info"><h3>{{ $upcomingCount ?? 0 }}</h3><p>Upcoming</p></div>
                <div class="stats-icon"><i class="feather-calendar"></i></div>
            </div>
            <div class="stats-card" data-status="">
                <div class="stats-info"><h3>{{ $todayCount ?? 0 }}</h3><p>Today</p></div>
                <div class="stats-icon"><i class="feather-sun"></i></div>
            </div>
            <div class="stats-card" data-status="completed">
                <div class="stats-info"><h3>{{ $completeMeetings ?? 0 }}</h3><p>Complete</p></div>
                <div class="stats-icon"><i class="feather-check-circle"></i></div>
            </div>
            <div class="stats-card" data-status="cancelled">
                <div class="stats-info"><h3>{{ $cancelledMeetings ?? 0 }}</h3><p>Cancelled</p></div>
                <div class="stats-icon"><i class="feather-x-circle"></i></div>
            </div>
            <div class="stats-card" data-status="">
                <div class="stats-info"><h3>{{ $pendingMomCount ?? 0 }}</h3><p>Pending MOM</p></div>
                <div class="stats-icon"><i class="feather-file-minus"></i></div>
            </div>
            <div class="stats-card" data-status="">
                <div class="stats-info"><h3>{{ $myMeetingsCount ?? 0 }}</h3><p>My Meetings</p></div>
                <div class="stats-icon"><i class="feather-user"></i></div>
            </div>
            <div class="stats-card active" data-status="all">
                <div class="stats-info"><h3>{{ $totalMeetings ?? 0 }}</h3><p>Total Meetings</p></div>
                <div class="stats-icon"><i class="feather-layers"></i></div>
            </div>
        </div>

        {{-- Filter section — same design as the Task "Assigned By Me" page.
             Filters auto-apply on change (data-auto-filter), no Apply button. --}}
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Meetings
                </div>
                @if (request()->hasAny(['search', 'status', 'date_from', 'date_to']))
                    <a href="{{ route('meetings.index') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ route('meetings.index') }}" id="meetingFilterForm" data-auto-filter data-auto-filter-delay="350">
                <div class="filter-row">
                    <div class="filter-item">
                        {{-- <label class="form-label">Search</label> --}}
                        <input type="text" name="search" class="filter-input" placeholder="Title or Meeting ID" value="{{ request('search') }}">
                    </div>
                    <div class="filter-item">
                        {{-- <label class="form-label">Status</label> --}}
                        <select name="status" class="filter-select" id="statusFilterSelect">
                            <option value="">All Status</option>
                            <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-item date-range">
                        {{-- <label class="form-label">From</label> --}}
                        <input type="date" name="date_from" class="filter-date" value="{{ request('date_from') }}">
                    </div>
                    <div class="filter-item date-range">
                        {{-- <label class="form-label">To</label> --}}
                        <input type="date" name="date_to" class="filter-date" value="{{ request('date_to') }}">
                    </div>
                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('meetings.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>

                @if (request()->hasAny(['search', 'status', 'date_from', 'date_to']))
                    <div class="active-filters">
                        <span class="active-filters-label">Active Filters:</span>
                        @if (request('search'))
                            <span class="filter-tag"><i class="feather-search"></i> {{ request('search') }}
                                <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}" class="remove-tag"><i class="feather-x"></i></a>
                            </span>
                        @endif
                        @if (request('status'))
                            <span class="filter-tag"><i class="feather-flag"></i> {{ ucfirst(request('status')) }}
                                <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" class="remove-tag"><i class="feather-x"></i></a>
                            </span>
                        @endif
                        @if (request('date_from'))
                            <span class="filter-tag"><i class="feather-calendar"></i> From {{ request('date_from') }}
                                <a href="{{ request()->fullUrlWithQuery(['date_from' => null]) }}" class="remove-tag"><i class="feather-x"></i></a>
                            </span>
                        @endif
                        @if (request('date_to'))
                            <span class="filter-tag"><i class="feather-calendar"></i> To {{ request('date_to') }}
                                <a href="{{ request()->fullUrlWithQuery(['date_to' => null]) }}" class="remove-tag"><i class="feather-x"></i></a>
                            </span>
                        @endif
                        <a href="{{ route('meetings.index') }}" class="filter-tag clear-all">
                            <i class="feather-x"></i> Clear All
                        </a>
                    </div>
                @endif
            </form>
        </div>

        <div class="card border">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover meetings-table mb-0">
                        <thead>
                            <tr>
                                <th>Sr.No</th>
                                <th>Code</th>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Organizer</th>
                                <th>Participants</th>
                                <th>MOM</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($meetings as $meeting)
                                @php
                                    $typeIcon = match ($meeting->meeting_type ?? 'physical') {
                                        'virtual' => 'feather-video',
                                        'hybrid' => 'feather-globe',
                                        default => 'feather-users',
                                    };
                                    $hasMom = !empty($meeting->mom_content) || ($meeting->tasks && $meeting->tasks->count() > 0);
                                    $isEditableRow = $meeting->status == 'scheduled';
                                @endphp
                                <tr>
                                    <td>{{ $meetings->firstItem() + $loop->index }}</td>
                                    <td><span class="meeting-code">{{ $meeting->meeting_id }}</span></td>
                                    <td><a href="{{ route('meetings.show', $meeting->id) }}" class="title-link">{{ $meeting->title }}</a></td>
                                    <td class="fs-12">{{ \Carbon\Carbon::parse($meeting->meeting_date)->format('d M Y') }}</td>
                                    <td class="fs-12">
                                        {{ \Carbon\Carbon::parse($meeting->start_time)->format('h:i A') }} &ndash; {{ \Carbon\Carbon::parse($meeting->end_time)->format('h:i A') }}
                                    </td>
                                    <td>
                                        <span class="type-icon-sm {{ $meeting->meeting_type }}"><i class="{{ $typeIcon }}"></i></span>
                                    </td>
                                    <td class="fs-12">{{ $meeting->creator->name ?? '—' }}</td>
                                    <td>
                                        <div class="meeting-avatar-stack">
                                            @foreach ($meeting->participants->take(4) as $p)
                                                @php
                                                    $name = $p->user->name ?? '?';
                                                    $initials = collect(explode(' ', $name))->take(2)->map(fn($w) => strtoupper($w[0]))->join('');
                                                @endphp
                                                <span class="m-av" title="{{ $name }}">{{ $initials }}</span>
                                            @endforeach
                                            @if ($meeting->participants->count() > 4)
                                                <span class="m-av overflow">+{{ $meeting->participants->count() - 4 }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if ($hasMom)
                                            <span class="mom-badge"><i class="feather-file-text"></i> MOM</span>
                                        @else
                                            <span class="text-muted fs-11">—</span>
                                        @endif
                                    </td>
                                    <td><x-ui.status-badge :status="$meeting->status" /></td>
                                    <td class="text-end">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button type="button" class="btn btn-icon btn-sm btn-light" title="View" onclick="openShowDrawer({{ $meeting->id }})"><i class="feather-eye"></i></button>
                                            @if ($meeting->status != 'cancelled')
                                                <button type="button" class="btn btn-icon btn-sm btn-light" title="MOM" onclick="openMomDrawer({{ $meeting->id }})"><i class="feather-file-plus"></i></button>
                                            @endif
                                            @if ($isEditableRow)
                                                <button type="button" class="btn btn-icon btn-sm btn-light" title="Edit"
                                                    data-meeting='@json($meeting->toEditPayload())'
                                                    onclick="openEditMeetingDrawer(this)">
                                                    <i class="feather-edit-2"></i>
                                                </button>
                                                <button type="button" class="btn btn-icon btn-sm btn-light" title="Cancel"
                                                    onclick="openCancelModal({{ $meeting->id }}, '{{ addslashes($meeting->title) }}')">
                                                    <i class="feather-x-circle"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11">
                                        <x-ui.empty-state icon="calendar" title="No meetings found"
                                            :subtitle="request()->hasAny(['search', 'status', 'date_from', 'date_to']) ? 'No meetings match your current filters.' : 'Get started by scheduling your first meeting.'" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($meetings->hasPages())
            <div class="d-flex justify-content-center mt-3">
                {{ $meetings->links() }}
            </div>
        @endif
    </div>
@endsection

@section('create-modal')
    {{-- Schedule Meeting — opens on this page, no navigation --}}
    <x-ui.drawer id="meetingDrawer" title="Schedule Meeting" width="480px">
        @include('client.mom.meeting._form', [
            'meeting' => null,
            'allUsers' => $allUsers,
            'isEditable' => true,
            'formAction' => route('meetings.store'),
            'formMethod' => 'POST',
            'idPrefix' => '',
        ])
    </x-ui.drawer>

    {{-- Shared Edit drawer — one instance, repopulated client-side from
         each row's data-meeting JSON (openEditMeetingDrawer below) instead
         of navigating to a separate edit page. --}}
    <x-ui.drawer id="meetingEditDrawer" title="Edit Meeting" width="480px">
        @include('client.mom.meeting._form', [
            'meeting' => null,
            'allUsers' => $allUsers,
            'isEditable' => true,
            'formAction' => '#',
            'formMethod' => 'PUT',
            'idPrefix' => 'edit_',
        ])
    </x-ui.drawer>

    {{-- Meeting Details — AJAX-fetched into this drawer instead of
         navigating to /meetings/show/{id} (see _show_content.blade.php +
         MeetingController::show()'s ajax() branch). --}}
    <x-ui.drawer id="meetingShowDrawer" title="Meeting Details" width="480px">
        <div id="meetingShowDrawerBody">
            <div class="text-center text-muted py-5"><i class="feather-loader"></i> Loading…</div>
        </div>
    </x-ui.drawer>

    {{-- Minutes of Meeting — same AJAX pattern, wider drawer since the task
         grid/Summernote toolbar need more room. --}}
    <x-ui.drawer id="meetingMomDrawer" title="Minutes of Meeting" width="480px">
        <div id="meetingMomDrawerBody">
            <div class="text-center text-muted py-5"><i class="feather-loader"></i> Loading…</div>
        </div>
    </x-ui.drawer>

    <x-ui.modal id="cancelModal" title="Cancel Meeting" bodyOnly>
        <form action="" method="POST" id="cancelForm">
            @csrf
            <p class="fs-13 text-secondary">You are about to cancel <strong id="meetingTitle"></strong>. This will notify all participants.</p>
            <label class="form-label fs-12 fw-semibold">Cancellation Reason *</label>
            <textarea name="reason" class="form-control form-control-sm" rows="3" required placeholder="e.g. Rescheduled to next week, venue unavailable…"></textarea>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="modal">Go Back</button>
            <button type="submit" form="cancelForm" class="btn btn-danger btn-sm">
                <i class="feather-x-circle me-1"></i> Cancel Meeting
            </button>
        </x-slot:footer>
    </x-ui.modal>

    <script>
        function openCancelModal(meetingId, meetingTitle) {
            document.getElementById('meetingTitle').innerText = meetingTitle;
            document.getElementById('cancelForm').action = '/meetings/' + meetingId + '/cancel';
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }

        function openEditMeetingDrawer(btn) {
            let data = {};
            try {
                data = JSON.parse(btn.getAttribute('data-meeting'));
            } catch (e) {
                console.error('Could not parse meeting data', e);
                return;
            }
            const root = document.getElementById('edit_formRoot');
            if (root && typeof root.populateFromData === 'function') {
                root.populateFromData(data);
            }
            new bootstrap.Offcanvas(document.getElementById('meetingEditDrawer')).show();
        }

        // Meeting Details drawer — fetches the same content show.blade.php
        // renders full-page, via the meetings.show route's $request->ajax()
        // branch, and injects it here instead of navigating.
        function openShowDrawer(meetingId) {
            const body = document.getElementById('meetingShowDrawerBody');
            body.innerHTML = '<div class="text-center text-muted py-5"><i class="feather-loader"></i> Loading…</div>';
            new bootstrap.Offcanvas(document.getElementById('meetingShowDrawer')).show();

            $.get('{{ url('meetings/show') }}/' + meetingId)
                .done(function(html) {
                    // jQuery's .html() (unlike plain .innerHTML =) actually
                    // evaluates any <script> tags in the response — needed
                    // here since this fragment can carry its own inline
                    // script.
                    $(body).html(html);
                })
                .fail(function() {
                    body.innerHTML = '<div class="alert alert-danger m-3">Could not load this meeting. Please try again.</div>';
                });
        }

        // Minutes of Meeting drawer — same AJAX-fetch-and-inject pattern.
        // Can also be opened from inside the Show drawer (its "Create/View
        // MOM" button calls this too), in which case we swap panels rather
        // than stacking two offcanvases.
        function openMomDrawer(meetingId) {
            const showInstance = bootstrap.Offcanvas.getInstance(document.getElementById('meetingShowDrawer'));
            if (showInstance) showInstance.hide();

            const body = document.getElementById('meetingMomDrawerBody');
            body.innerHTML = '<div class="text-center text-muted py-5"><i class="feather-loader"></i> Loading…</div>';
            new bootstrap.Offcanvas(document.getElementById('meetingMomDrawer')).show();

            $.get('{{ url('meetings/mom/create') }}/' + meetingId)
                .done(function(html) {
                    // Must use jQuery's .html() here, not .innerHTML = — the
                    // fragment's own <script> (Summernote/Select2 init, task
                    // rows, voice recording) would otherwise be inserted
                    // inert and never run, which is why the Meeting Minutes
                    // field was staying a plain textarea instead of becoming
                    // the rich-text editor.
                    $(body).html(html);
                })
                .fail(function() {
                    body.innerHTML = '<div class="alert alert-danger m-3">Could not load the MOM form. Please try again.</div>';
                });
        }
    </script>
@endsection

@section('script-area')
    {{-- Needed by the Minutes of Meeting drawer's fragment (_mom_form_content.blade.php)
         when it's fetched and injected — jQuery/Select2 are already global
         (client.layout.foot), only Summernote is page-specific. --}}
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script src="{{ asset('assets/js/auto-filter.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                document.querySelectorAll('.alert .btn-close').forEach(btn => btn.click());
            }, 5000);

            // Stat card click-to-filter (same pattern as the Task "Assigned
            // By Me" page): set the status filter and resubmit.
            document.querySelectorAll('.stats-card').forEach(function(card) {
                card.addEventListener('click', function() {
                    const status = card.getAttribute('data-status');
                    document.querySelectorAll('.stats-card').forEach(c => c.classList.remove('active'));
                    card.classList.add('active');
                    const select = document.getElementById('statusFilterSelect');
                    if (select) {
                        select.value = status || '';
                    }
                    document.getElementById('meetingFilterForm').submit();
                });
            });

            const currentStatus = "{{ request('status') }}";
            document.querySelectorAll('.stats-card').forEach(c => c.classList.remove('active'));
            if (currentStatus) {
                const match = document.querySelector(`.stats-card[data-status="${currentStatus}"]`);
                if (match) match.classList.add('active');
            } else {
                const all = document.querySelector('.stats-card[data-status="all"]');
                if (all) all.classList.add('active');
            }
        });
    </script>
@endsection
