@extends('client.layout.master')

@section('style')
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <style>
        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Loading text */
        .loading-text {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* Profile header: the ui.profile-header component, styled in theme-custom.css */

        /* Attendance Stats — a touch more compact than the shared default,
           matching this page's overall smaller-density layout. */
        .member-stats-grid {
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin-bottom: 10px;
        }

        .member-stats-grid .stats-card {
            padding: 8px 10px;
            gap: 8px;
        }

        .member-stats-grid .stats-icon-wrapper {
            width: 28px;
            height: 28px;
        }

        .member-stats-grid .stats-amount-main {
            font-size: 14px;
        }

        @media (max-width: 767px) {
            .member-stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Tabs */
        .nav-tabs {
            border-bottom: 2px solid var(--border);
            padding: 0 5px;
        }

        .nav-tabs .nav-link {
            padding: 7px 14px;
            margin: 0 5px -2px 0;
            font-size: 12px;
        }

        .tab-content {
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 10px 10px;
            padding: 14px;
        }

        /* Info Cards */
        .info-card {
            border-radius: 10px;
            border: 1px solid var(--border);
            margin-bottom: 12px;
            box-shadow: var(--shadow-sm);
            background-color: #ffffff;
        }

        .info-card .card-header {
            padding: 8px 12px;
            font-size: 12px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
        }

        .info-item {
            padding: 7px 12px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            min-height: 45px;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            min-width: 140px;
            font-size: 13px;
        }

        /* Document Cards */
        .document-card {
            padding: 15px;
            height: 100%;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .document-card:hover {
            border-color: var(--primary-mid);
            background: #f0f9ff;
        }

        .document-icon {
            font-size: 32px;
            margin-bottom: 8px;
        }

        .document-card h6 {
            font-size: 13px;
            margin-bottom: 5px;
        }

        /* Attendance Section */
        .attendance-section {
            display: none;
        }

        .attendance-section.active {
            display: block;
        }

        /* Status Badges */
        .badge-status {
            padding: 4px 10px;
            font-size: 11px;
            border-radius: 4px;
            font-weight: 600;
        }

        /* Same colours as the calendar (CALENDAR_STATUS_STYLES) */
        .badge-present { background: #16a34a; color: #ffffff; }
        .badge-checked-in-only { background: #d97706; color: #ffffff; }
        .badge-absent { background: #dc2626; color: #ffffff; }
        .badge-leave,
        .badge-full-day-leave { background: #7c3aed; color: #ffffff; }
        .badge-first-half-leave,
        .badge-second-half-leave { background: #a855f7; color: #ffffff; }
        .badge-holiday { background: #0D6EFD; color: #ffffff; }
        .badge-week-off { background: #64748b; color: #ffffff; }
        .badge-upcoming { background: #e2e8f0; color: #475569; }

        /* Task count badge on calendar events */
        .fc-event .task-count-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 2px 6px;
            font-size: 9px;
            margin-left: 6px;
            font-weight: 600;
        }

        /* FullCalendar — same look as the employee's Attendance page calendar */
        #attendanceCalendar {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid #eef2f7;
        }

        #attendanceCalendar .fc-toolbar.fc-header-toolbar { margin-bottom: 0 !important; }

        #attendanceCalendar .fc-col-header-cell {
            background: #f8fafd;
            border-bottom: 2px solid #eef2f7;
            padding: 8px 0;
        }

        #attendanceCalendar .fc-col-header-cell-cushion {
            color: #4a5568;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
        }

        #attendanceCalendar .fc-daygrid-day {
            border: 1px solid #dee2e6;
            transition: all 0.2s ease;
        }

        #attendanceCalendar .fc-daygrid-day:hover { background: #f9fafb; cursor: pointer; }

        #attendanceCalendar .fc-daygrid-day.fc-day-today {
            background: rgba(13, 110, 253, 0.1) !important;
            position: relative;
        }

        #attendanceCalendar .fc-daygrid-day.fc-day-today::before {
            content: '';
            position: absolute;
            top: 4px;
            right: 4px;
            width: 6px;
            height: 6px;
            background: #0D6EFD;
            border-radius: 50%;
        }

        #attendanceCalendar .fc-daygrid-day-number {
            color: #2d3748;
            font-weight: 600;
            padding: 10px;
            font-size: 0.9rem;
            text-decoration: none;
        }

        #attendanceCalendar .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
            color: #0D6EFD;
            font-weight: 700;
            font-size: 1rem;
        }

        #attendanceCalendar .fc-event {
            border: none !important;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 500;
            padding: 6px 8px;
            margin: 2px 0;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        #attendanceCalendar .fc-daygrid-event-harness {
            padding: 0px 5px;
        }

        #attendanceCalendar .fc-event:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        /* Header row above the calendar: Prev / Next / Today · month · view */
        .cal-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
        .cal-head-month { margin: 0; font-size: 1.15rem; font-weight: 700; color: #0D6EFD; text-transform: uppercase; letter-spacing: 1px; }
        .cal-head .custom-dropdown .dropdown-toggle {
            background: white;
            border: 1px solid #eef2f7;
            color: #4a5568;
            font-weight: 400;
            font-size: 12.5px;
            padding: 6px 10px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .cal-head .custom-dropdown .dropdown-toggle:hover { background: #f9fafb; border-color: #0D6EFD; }
        .cal-head .custom-dropdown .dropdown-menu { border: 2px solid #eef2f7; border-radius: 10px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1); margin-top: 8px; }
        .cal-head .custom-dropdown .dropdown-item { font-size: 12.5px; }
        .cal-head .custom-dropdown .dropdown-item:hover { background: #0D6EFD; color: white; }

        /* Calendar Legend */
        .calendar-legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
            color: #6c757d;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }

        /* Loading state */
        .opacity-50 {
            opacity: 0.5;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        /* Table */
        .attendance-table th,
        .attendance-table td {
            padding: 7px 10px !important;
            font-size: 11.5px;
        }

        .attendance-table th {
            font-size: 11px;
            white-space: nowrap;
            background: #f8fafc;
        }

        /* Responsive */
        @media (max-width: 768px) {

            .info-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .info-label {
                min-width: auto;
                width: 100%;
            }

            #attendanceCalendar {
                height: 400px;
            }

            .cal-head { justify-content: center; }
            .calendar-legend { gap: 0.75rem 1rem; }

        }
    </style>
@endsection

@section('content-area')
    <!-- Page Header -->
    <x-ui.page-header title="Member Profile" :crumbs="[['label' => 'Team', 'url' => route('team.index')]]" :back="route('team.index')" />

    <div class="main-content" style="padding: 20px !important;">
        <!-- Profile Header -->
        <x-ui.profile-header :profile="$profileHeader" />

        <!-- Attendance Stats — same .stats-grid/.stats-card family as the
             Team Attendance page (theme-custom.css centralizes it to the
             app's single blue-only look). -->
        <div class="stats-grid member-stats-grid">
            <div class="stats-card present-card">
                <div class="stats-icon-wrapper"><i class="feather-check-circle"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main" id="stat-present">{{ $attendanceSummary['present'] ?? 0 }}</div>
                    <div class="stats-label">Present</div>
                </div>
            </div>
            <div class="stats-card absent-card">
                <div class="stats-icon-wrapper"><i class="feather-x-circle"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main" id="stat-absent">{{ $attendanceSummary['absent'] ?? 0 }}</div>
                    <div class="stats-label">Absent</div>
                </div>
            </div>
            <div class="stats-card leave-card">
                <div class="stats-icon-wrapper"><i class="feather-calendar"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main" id="stat-leave">{{ $attendanceSummary['on_leave'] ?? 0 }}</div>
                    <div class="stats-label">On Leave</div>
                </div>
            </div>
            <div class="stats-card holiday-card">
                <div class="stats-icon-wrapper"><i class="feather-star"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main" id="stat-holiday">{{ $attendanceSummary['holiday'] ?? 0 }}</div>
                    <div class="stats-label">Holidays</div>
                </div>
            </div>
            <div class="stats-card weekoff-card">
                <div class="stats-icon-wrapper"><i class="feather-coffee"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main" id="stat-weekoff">{{ $attendanceSummary['week_off'] ?? 0 }}</div>
                    <div class="stats-label">Week Off</div>
                </div>
            </div>
            <div class="stats-card total-card">
                <div class="stats-icon-wrapper"><i class="feather-briefcase"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main" id="stat-workdays">{{ $attendanceSummary['work_days'] ?? 0 }}</div>
                    <div class="stats-label">Work Days</div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal"
                    type="button" role="tab">
                    <i class="feather-user me-1"></i>Personal
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="job-tab" data-bs-toggle="tab" data-bs-target="#job" type="button"
                    role="tab">
                    <i class="feather-briefcase me-1"></i>Job
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance"
                    type="button" role="tab">
                    <i class="feather-calendar me-1"></i>Attendance
                </button>
            </li>
            @if ($userInfo->role === 'manager')
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="team-tab" data-bs-toggle="tab" data-bs-target="#reportingTeam"
                        type="button" role="tab">
                        <i class="feather-users me-1"></i>Team ({{ $directReports->count() }})
                    </button>
                </li>
            @endif
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="profileTabsContent">
            <!-- Personal Information Tab -->
            <div class="tab-pane fade" id="personal" role="tabpanel">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="info-card">
                            <div class="card-header"><i class="feather-info me-2"></i>Basic Information</div>
                            @if ($userInfo->dob)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-calendar me-1"></i>Date of Birth</span>
                                    <span class="info-value">
                                        {{ \Carbon\Carbon::parse($userInfo->dob)->format('d M, Y') }}
                                        @if ($age)
                                            ({{ $age }} years)
                                        @endif
                                    </span>
                                </div>
                            @endif
                            @if ($userInfo->gender)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-user me-1"></i>Gender</span>
                                    <span class="info-value">{{ ucfirst($userInfo->gender) }}</span>
                                </div>
                            @endif
                            @if ($userInfo->blood_group)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-droplet me-1"></i>Blood Group</span>
                                    <span class="info-value">{{ $userInfo->blood_group }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="info-card">
                            <div class="card-header"><i class="feather-home me-2"></i>Contact Information</div>
                            @if ($userInfo->father_name)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-user me-1"></i>Father's Name</span>
                                    <span class="info-value">{{ $userInfo->father_name }}</span>
                                </div>
                            @endif
                            @if ($userInfo->personal_email)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-mail me-1"></i>Personal Email</span>
                                    <span class="info-value">{{ $userInfo->personal_email }}</span>
                                </div>
                            @endif
                            @if ($userInfo->alternate_phone)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-phone me-1"></i>Alternate Phone</span>
                                    <span class="info-value">{{ $userInfo->alternate_phone }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Job Details Tab -->
            <div class="tab-pane fade" id="job" role="tabpanel">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="info-card">
                            <div class="card-header"><i class="feather-briefcase me-2"></i>Employment Details</div>
                            @if ($userInfo->designation)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-award me-1"></i>Designation</span>
                                    <span class="info-value">{{ $userInfo->designation }}</span>
                                </div>
                            @endif
                            @if ($userInfo->department)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-layers me-1"></i>Department</span>
                                    <span class="info-value">{{ $userInfo->department }}</span>
                                </div>
                            @endif
                            @if ($userInfo->joining_date)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-calendar me-1"></i>Joining Date</span>
                                    <span
                                        class="info-value">{{ \Carbon\Carbon::parse($userInfo->joining_date)->format('d M, Y') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="info-card">
                            <div class="card-header"><i class="feather-map-pin me-2"></i>Location Details</div>
                            @if ($userInfo->address)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-map me-1"></i>Current Address</span>
                                    <span class="info-value">{{ $userInfo->address }}</span>
                                </div>
                            @endif
                            @if ($userInfo->city)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-navigation me-1"></i>City</span>
                                    <span class="info-value">{{ $userInfo->city }}</span>
                                </div>
                            @endif
                            @if ($userInfo->state)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-globe me-1"></i>State</span>
                                    <span class="info-value">{{ $userInfo->state }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Tab -->
            <div class="tab-pane fade show active" id="attendance" role="tabpanel"
                data-onboard-date="{{ $userCreatedDate ?? date('Y-m-d') }}">
                <!-- Calendar View -->
                <div class="attendance-section active" id="calendarView">
                    <!-- Calendar header: Prev / Next / Today · month · view (same as the employee's Attendance page) -->
                    <div class="cal-head">
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm cal-nav-btn" onclick="calendarPrev()">
                                <i class="feather-chevron-left"></i> Prev
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm cal-nav-btn" onclick="calendarNext()">
                                Next <i class="feather-chevron-right"></i>
                            </button>
                            <button type="button" class="btn btn-primary btn-sm cal-nav-btn" onclick="calendarToday()">
                                <i class="feather-clock me-1"></i> Today
                            </button>
                        </div>
                        <h3 class="cal-head-month" id="currentMonthDisplay">{{ date('F Y', strtotime($selectedMonth)) }}</h3>
                        <div class="custom-dropdown">
                            <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="feather-grid me-2"></i>
                                <span id="currentCalendarView">Month View</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="changeCalendarView('dayGridMonth')"><i class="feather-calendar me-2"></i> Month View</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="changeCalendarView('timeGridWeek')"><i class="feather-calendar me-2"></i> Week View</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="changeCalendarView('timeGridDay')"><i class="feather-calendar me-2"></i> Day View</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="changeCalendarView('listMonth')"><i class="feather-list me-2"></i> List View</a></li>
                            </ul>
                        </div>
                    </div>

                    <!-- FullCalendar Container -->
                    <div id="attendanceCalendar" class="d-block"></div>

                    <!-- Legend: same colours as CALENDAR_STATUS_STYLES in the script below -->
                    <div class="calendar-legend">
                        @foreach (['Present' => '#16a34a', 'Checked In Only' => '#d97706', 'Absent' => '#dc2626', 'Leave' => '#7c3aed', 'Half Day Leave' => '#a855f7', 'Holiday' => '#0D6EFD', 'Week Off' => '#64748b', 'Upcoming' => '#e2e8f0'] as $legendLabel => $legendColor)
                            <div class="legend-item">
                                <div class="legend-color" style="background: {{ $legendColor }};{{ $legendLabel === 'Upcoming' ? ' border: 1px solid #cbd5e1;' : '' }}"></div><span>{{ $legendLabel }}</span>
                            </div>
                        @endforeach
                    </div>


                </div>

                <!-- Table View -->
                <div class="attendance-section" id="tableView">
                    <div class="table-responsive">
                        <table class="table table-hover attendance-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Day</th>
                                    <th>Punch In</th>
                                    <th>Punch Out</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                    <th>Tasks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($attendanceData as $record)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($record->date)->format('d M, Y') }}</td>
                                        <td>{{ $record->day_name }}</td>
                                        <td>
                                            @if ($record->clock_in)
                                                {{ \Carbon\Carbon::parse($record->clock_in)->format('h:i A') }}
                                            @else
                                                <span class="text-muted">--:--</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($record->clock_out)
                                                {{ clock_out_time($record->clock_out, $record->date ?? $record->clock_in) }}
                                            @else
                                                <span class="text-muted">--:--</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($record->total_hours)
                                                {{ $record->total_hours }}h
                                            @else
                                                <span class="text-muted">--</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge-status {{ 'badge-' . strtolower(str_replace(' ', '-', $record->day_status)) }}">
                                                {{ $record->day_status }}
                                            </span>
                                        </td>
                                        <td>
                                            @if(isset($record->task_count) && $record->task_count > 0)
                                                <span class="badge bg-warning text-dark">
                                                    <i class="feather-list"></i> {{ $record->task_count }}
                                                </span>
                                            @else
                                                <span class="text-muted">--</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($userInfo->role === 'manager')
                <!-- Reporting Team Tab -->
                <div class="tab-pane fade" id="reportingTeam" role="tabpanel">
                    @include('client.team.partials.direct-reports', ['directReports' => $directReports])
                </div>
            @endif

            <!-- Documents Tab -->
            <div class="tab-pane fade" id="documents" role="tabpanel">
                <div class="row g-3">
                    @if ($documents['experience_letter'])
                        <div class="col-md-3 col-sm-6">
                            <a href="{{ $documents['experience_letter'] }}" target="_blank" class="text-decoration-none">
                                <div class="document-card">
                                    <div class="document-icon"><i class="feather-file-text"></i></div>
                                    <h6>Experience Letter</h6>
                                    <small class="text-muted">Click to view</small>
                                </div>
                            </a>
                        </div>
                    @endif
                    @if ($documents['tenth_marksheet'])
                        <div class="col-md-3 col-sm-6">
                            <a href="{{ $documents['tenth_marksheet'] }}" target="_blank" class="text-decoration-none">
                                <div class="document-card">
                                    <div class="document-icon"><i class="feather-file"></i></div>
                                    <h6>10th Marksheet</h6>
                                    <small class="text-muted">Click to view</small>
                                </div>
                            </a>
                        </div>
                    @endif
                    @if ($documents['twelfth_marksheet'])
                        <div class="col-md-3 col-sm-6">
                            <a href="{{ $documents['twelfth_marksheet'] }}" target="_blank" class="text-decoration-none">
                                <div class="document-card">
                                    <div class="document-icon"><i class="feather-file"></i></div>
                                    <h6>12th Marksheet</h6>
                                    <small class="text-muted">Click to view</small>
                                </div>
                            </a>
                        </div>
                    @endif
                    @if ($documents['highest_qualification_certificate'])
                        <div class="col-md-3 col-sm-6">
                            <a href="{{ $documents['highest_qualification_certificate'] }}" target="_blank" class="text-decoration-none">
                                <div class="document-card">
                                    <div class="document-icon"><i class="feather-award"></i></div>
                                    <h6>Highest Qualification</h6>
                                    <small class="text-muted">Click to view</small>
                                </div>
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Bank Details Tab -->
            <div class="tab-pane fade" id="bank" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="info-card">
                            <div class="card-header"><i class="feather-credit-card me-2"></i>Bank Account Details</div>
                            @if ($userInfo->account_number)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-hash me-1"></i>Account Number</span>
                                    <span class="info-value">{{ $userInfo->account_number }}</span>
                                </div>
                            @endif
                            @if ($userInfo->bank_name)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-home me-1"></i>Bank Name</span>
                                    <span class="info-value">{{ $userInfo->bank_name }}</span>
                                </div>
                            @endif
                            @if ($userInfo->ifsc)
                                <div class="info-item">
                                    <span class="info-label"><i class="feather-code me-1"></i>IFSC Code</span>
                                    <span class="info-value">{{ $userInfo->ifsc }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Attendance Details Modal -->
    <div class="modal fade" id="attendanceDetailsModal" tabindex="-1" aria-labelledby="attendanceDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attendanceDetailsModalLabel">Attendance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body attendance-details-modal">
                    <div id="attendanceDetailsContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    @include('client.partials.calendar-day-hover')
    <script>
        $(document).ready(function() {
            // Hover any day → shift, clock in / out with location, regularized, etc.
            initCalendarDayHover(() => calendar, document.getElementById('attendanceCalendar'));

            $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                localStorage.setItem('activeTab', $(e.target).attr('href'));
            });

            var activeTab = localStorage.getItem('activeTab');
            if (activeTab) {
                $('#profileTabs a[href="' + activeTab + '"]').tab('show');
            }

            $('#attendance-tab').on('shown.bs.tab', function() {
                setTimeout(initializeCalendar, 100);
            });

            if ($('#attendance-tab').hasClass('active')) {
                setTimeout(initializeCalendar, 100);
            }

            // Navigation-driven month change (calendar's own prev/next/today
            // buttons), replacing the old Apply/Reset filter form.
            window.currentCalendarMonth = '{{ $selectedMonth }}';

            window.handleCalendarMonthChange = function(selectedMonth) {
                if (selectedMonth === window.currentCalendarMonth) {
                    // Same month, but a Week/Month view can show days of the next or
                    // previous month too — load those events if they are missing.
                    const vis = window.calendarVisibleRange, loaded = window.calendarLoadedRange;
                    if (vis && loaded && (vis.start < loaded.start || vis.end > loaded.end)) {
                        updateAttendanceCalendar(selectedMonth, '{{ encrypt($userInfo->id) }}');
                    }
                    return;
                }

                const selectedDate = moment(selectedMonth + '-01');
                const currentDate = moment();

                if (selectedDate.isAfter(currentDate, 'month')) {
                    if (calendar) calendar.gotoDate(currentDate.format('YYYY-MM') + '-01');
                    return;
                }

                const userOnboardDate = $('#attendance').data('onboard-date');
                if (userOnboardDate) {
                    const onboardDate = moment(userOnboardDate);
                    if (selectedDate.isBefore(onboardDate, 'month')) {
                        if (calendar) calendar.gotoDate(onboardDate.format('YYYY-MM') + '-01');
                        return;
                    }
                }

                window.currentCalendarMonth = selectedMonth;
                const encryptedId = '{{ encrypt($userInfo->id) }}';

                $('#calendarView').addClass('opacity-50');
                $('#tableView').addClass('opacity-50');
                $('.member-stats-grid').addClass('opacity-50');

                updateAttendanceStats(selectedMonth, encryptedId);
            };
        });

        let calendar = null;

        // One colour per day status, so each day is readable at a glance.
        // Keep in sync with the legend under the calendar.
        const CALENDAR_STATUS_STYLES = {
            'present':           { bg: '#16a34a', text: '#ffffff' },
            'halfday':           { bg: '#65a30d', text: '#ffffff' },
            'half day':          { bg: '#65a30d', text: '#ffffff' },
            'checked in only':   { bg: '#d97706', text: '#ffffff' },
            'absent':            { bg: '#dc2626', text: '#ffffff' },
            'full day leave':    { bg: '#7c3aed', text: '#ffffff' },
            'first half leave':  { bg: '#a855f7', text: '#ffffff' },
            'second half leave': { bg: '#a855f7', text: '#ffffff' },
            'holiday':           { bg: '#0D6EFD', text: '#ffffff' },
            'week off':          { bg: '#64748b', text: '#ffffff' },
            'upcoming':          { bg: '#e2e8f0', text: '#475569' },
        };

        function calendarEventStyle(status) {
            return CALENDAR_STATUS_STYLES[String(status || '').toLowerCase()] || { bg: '#94a3b8', text: '#ffffff' };
        }

        // Week / Day view: one timed block per worked span (clock-in → clock-out,
        // or each shift's own in → out on a multi-shift day). Month / List views
        // keep only the one-line day status.
        window.workEvents = [];

        function buildWorkEvents(date, clockIn, clockOut, shifts, encryptedUserId) {
            const spans = (shifts && shifts.length)
                ? shifts.map(s => ({ in: s.in, out: s.out, name: (s.is_extra ? '+ ' : '') + s.name }))
                : (clockIn ? [{ in: clockIn, out: clockOut, name: '' }] : []);
            return spans.filter(s => s.in).map(function(s, i) {
                const start = moment(s.in);
                let end = s.out ? moment(s.out) : null, open = !end;
                if (open) {
                    // Still working (today) → up to now; a past day with no clock-out → a short marker.
                    end = start.isSame(moment(), 'day') ? moment() : start.clone().add(30, 'minutes');
                }
                if (!end.isAfter(start)) end = start.clone().add(15, 'minutes');
                const hrs = s.out ? ' · ' + (moment(s.out).diff(start, 'minutes') / 60).toFixed(1) + 'h' : '';
                const label = (s.name ? s.name + ': ' : '') + start.format('hh:mm A') + ' – '
                    + (s.out ? moment(s.out).format('hh:mm A') : (start.isSame(moment(), 'day') ? 'ongoing' : 'no clock-out')) + hrs;
                return {
                    id: 'work_' + date + '_' + i,
                    title: label,
                    start: start.format('YYYY-MM-DDTHH:mm:ss'),
                    end: end.format('YYYY-MM-DDTHH:mm:ss'),
                    allDay: false,
                    backgroundColor: open ? '#60a5fa' : '#0D6EFD',
                    borderColor: '#0B5ED7',
                    textColor: '#ffffff',
                    extendedProps: { kind: 'work', date: date, encrypted_user_id: encryptedUserId }
                };
            });
        }

        function syncWorkEvents(viewType) {
            if (!calendar) return;
            const src = calendar.getEventSourceById('work');
            if (src) src.remove();
            if (String(viewType || calendar.view.type).indexOf('timeGrid') === 0) {
                calendar.addEventSource({ id: 'work', events: window.workEvents });
            }
        }

        function calendarPrev() { if (calendar) calendar.prev(); }
        function calendarNext() { if (calendar) calendar.next(); }
        function calendarToday() {
            if (!calendar) return;
            if (calendar.view.type === 'timeGridWeek') {
                calendar.gotoDate(moment().subtract(6, 'days').format('YYYY-MM-DD'));
            } else {
                calendar.today();
            }
        }

        function initializeCalendar() {
            if (calendar) {
                calendar.destroy();
                calendar = null;
            }

            var calendarEl = document.getElementById('attendanceCalendar');
            if (!calendarEl) return;

            const encryptedUserId = '{{ encrypt($userInfo->id) }}';
            let events = [];
            const attendanceData = @json($attendanceData);

            attendanceData.forEach(function(record) {
                const dateStr = record.formatted_date || record.date;
                const status = record.day_status;
                const taskCount = record.task_count || 0;

                if (!dateStr || !status) return;

                // Colour comes from calendarEventStyle(); this only picks the title.
                let title;
                switch (status.toLowerCase()) {
                    case 'present':
                        title = 'Present';
                        break;
                    case 'halfday':
                        title = 'Half Day';
                        break;
                    case 'absent':
                        title = 'Absent';
                        break;
                    case 'first half leave':
                    case 'second half leave':
                    case 'full day leave':
                        title = 'Leave';
                        break;
                    case 'holiday':
                        title = record.holiday_name || 'Holiday';
                        break;
                    case 'week off':
                        title = 'Week Off';
                        break;
                    case 'checked in only':
                        title = 'Checked In Only';
                        break;
                    case 'upcoming':
                        title = 'Upcoming';
                        break;
                    default:
                        title = status;
                }
                const style = calendarEventStyle(status);

                // if (taskCount > 0) {
                //     title += ` Task ${taskCount}`;
                // }

                let clockInTime = '--:--';
                let clockOutTime = '--:--';
                let totalHours = '--';

                if (record.clock_in) {
                    clockInTime = moment(record.clock_in).format('hh:mm A');
                }
                if (record.clock_out) {
                    clockOutTime = moment(record.clock_out).format('hh:mm A');
                    // Clock-out on a later day than the attendance date (e.g. 06:30 → 09:00 next morning).
                    const outDays = moment(record.clock_out).startOf('day').diff(moment(record.date || record.clock_in).startOf('day'), 'days');
                    if (outDays > 0) clockOutTime += ' (+' + outDays + ' day' + (outDays > 1 ? 's' : '') + ')';
                }
                if (record.total_hours) {
                    totalHours = record.total_hours;
                }
                // Multi-shift: "Present ×2" / "×1.5" (extra shifts paid as extra working days),
                // and each shift's own in / out in the hover text.
                if (record.day_label) {
                    title = record.day_label;
                }
                const shiftLines = (record.shifts || []).map(s => (s.is_extra ? '+ ' : '') + s.name + ': '
                    + (s.in ? moment(s.in).format('hh:mm A') : '--:--') + ' – ' + (s.out ? moment(s.out).format('hh:mm A') : 'ongoing')).join('<br>');

                events.push({
                    id: 'attendance_' + dateStr,
                    title: title,
                    start: dateStr,
                    allDay: true,
                    backgroundColor: style.bg,
                    borderColor: style.bg,
                    textColor: style.text,
                    extendedProps: {
                        date: dateStr,
                        day_name: record.day_name,
                        clock_in: clockInTime,
                        clock_out: clockOutTime,
                        total_hours: totalHours,
                        status: status,
                        task_count: taskCount,
                        holiday_name: record.holiday_name,
                        leave_type: record.leave_type,
                        leave_reason: record.leave_reason,
                        details: record.details || null,
                        encrypted_user_id: encryptedUserId,
                        description: shiftLines ? `${title}<br>${shiftLines}${taskCount > 0 ? '<br> Tasks: ' + taskCount : ''}` : `${status}<br>${record.clock_in ? 'In: ' + clockInTime : ''}${record.clock_out ? '<br>Out: ' + clockOutTime : ''}${record.total_hours ? '<br>Hours: ' + totalHours : ''}${taskCount > 0 ? '<br> Tasks: ' + taskCount : ''}`
                    }
                });
            });

            window.workEvents = [];
            attendanceData.forEach(function(record) {
                const d = record.formatted_date || record.date;
                if (d) window.workEvents.push(...buildWorkEvents(d, record.clock_in, record.clock_out, record.shifts, encryptedUserId));
            });

            const selectedMonth = '{{ $selectedMonth }}';
            // Re-opening the tab rebuilds the calendar: stay on the month being viewed;
            // the events above are only the page's own month, so mark just that as loaded.
            const viewMonth = window.currentCalendarMonth || selectedMonth;
            const initialDate = viewMonth ? viewMonth + '-01' : moment().format('YYYY-MM-DD');
            if (selectedMonth) {
                window.calendarLoadedRange = {
                    start: moment(selectedMonth + '-01').format('YYYY-MM-DD'),
                    end: moment(selectedMonth + '-01').endOf('month').format('YYYY-MM-DD'),
                };
            }

            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                initialDate: initialDate,
                // The page's own header row (Prev / Next / Today · month · view) replaces the toolbar.
                headerToolbar: { left: '', center: '', right: '' },
                themeSystem: 'standard',
                // Week view: 7 days from the given start date, not aligned to Sunday/Monday.
                views: { timeGridWeek: { duration: { days: 7 } } },
                // Day / Week columns show the day name with its date ("Wednesday, 21 Oct 2026" / "Wed, 21 Oct").
                dayHeaderContent: function(arg) {
                    if (arg.view.type === 'timeGridDay') return moment(arg.date).format('dddd, DD MMM YYYY');
                    if (arg.view.type === 'timeGridWeek') return moment(arg.date).format('ddd, DD MMM');
                    return arg.text;
                },
                events: events,
                datesSet: function(info) {
                    // Native prev/next/today navigation replaces the old
                    // Apply/Reset month filter — fetch the newly-visible
                    // month's data as soon as the calendar moves.
                    // Month = the date being looked at, not the first visible day: in
                    // Week view that day is often the previous month (week of 27 Sep
                    // for 1 Oct), which used to walk the calendar back month by month.
                    // The rolling 7-day Week view is "looking at" its last day (today).
                    const lookedAt = info.view.type === 'timeGridWeek'
                        ? moment(info.end).subtract(1, 'day')
                        : moment(info.view.calendar.getDate());
                    const visibleMonth = lookedAt.format('YYYY-MM');
                    window.calendarVisibleRange = {
                        start: moment(info.start).format('YYYY-MM-DD'),
                        end: moment(info.end).subtract(1, 'day').format('YYYY-MM-DD'),
                    };
                    $('#currentMonthDisplay').text(moment(visibleMonth + '-01').format('MMMM YYYY'));
                    if (calendar) syncWorkEvents(info.view.type);
                    if (window.handleCalendarMonthChange) {
                        window.handleCalendarMonthChange(visibleMonth);
                    }
                },
                eventClick: function(info) {
                    const formattedDate = info.event.startStr.split('T')[0];
                    const encryptedUserId = info.event.extendedProps.encrypted_user_id;
                    const sessionsUrl = "{{ route('attendance.sessions') }}";
                    window.location.href = sessionsUrl + "?date=" + encodeURIComponent(formattedDate) + 
                                          "&user_id=" + encodeURIComponent(encryptedUserId);
                },
                // Month view: clicking a date (not its status chip) opens that date in Day view.
                dateClick: function(info) {
                    if (info.view.type !== 'dayGridMonth') return;
                    calendar.changeView('timeGridDay', info.dateStr);
                    updateCalendarViewButtons('timeGridDay');
                },
                eventContent: function(arg) {
                    if (arg.event.extendedProps.kind === 'work') {
                        return { html: `<div style="padding: 2px 4px; font-size: 11px; line-height: 1.3; color: #ffffff; white-space: normal;">${arg.event.title}</div>` };
                    }
                    let taskCount = arg.event.extendedProps.task_count || 0;
                    let taskBadge = '';

                    // Custom HTML replaces FullCalendar's own title element, so it
                    // no longer reliably inherits the per-event textColor — set it
                    // explicitly or a light-bg event (e.g. Week Off, Upcoming) can
                    // render with unreadable (white-on-white) text.
                    const txtColor = arg.event.textColor || '#1a2236';

                    if (taskCount > 0) {
                        taskBadge = `<span class="task-count-badge" style="color:${txtColor};">Tasks ${taskCount}</span>`;
                    }

                    let cleanTitle = arg.event.title.replace(/📋 \d+/, '').trim();

                    // List view rows sit on a white background (no event fill), so show the
                    // status as a coloured badge instead of white text on white.
                    if (String(arg.view.type).indexOf('list') === 0) {
                        const bg = arg.event.backgroundColor || '#94a3b8';
                        return {
                            html: `<span style="display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; background: ${bg}; color: ${txtColor};">${cleanTitle}</span>`
                                + (taskCount > 0 ? `<span style="margin-left: 8px; font-size: 11px; color: #475569;">Tasks ${taskCount}</span>` : '')
                        };
                    }

                    return {
                        html: `<div class="fc-event-main" style="display: flex; justify-content: space-between; align-items: center; padding: 2px 4px; color: ${txtColor};">
                                    <span style="font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: ${txtColor};">${cleanTitle}</span>
                                    ${taskBadge}
                               </div>`
                    };
                },
                // Tag every event with its date so the hover card also works on List-view rows.
                eventDidMount: function(info) {
                    info.el.setAttribute('data-date', moment(info.event.start).format('YYYY-MM-DD'));
                },
                eventDisplay: 'block',
                dayMaxEvents: 3,
                height: 580,
                contentHeight: 'auto',
                // Week / Day: an hour grid so each clock-in → clock-out block shows its real length.
                slotDuration: '01:00:00',
                scrollTime: '07:00:00',
                nowIndicator: true,
                dayCellContent: function(arg) {
                    return { html: '<div class="fc-daygrid-day-number">' + arg.dayNumberText + '</div>' };
                }
            });

            calendar.render();
            updateCalendarViewButtons('dayGridMonth');
            window.calendar = calendar;
        }

        // Week view = the last 7 days ending today (today and the 6 days before it).
        // Looking at an earlier month, it ends on that month's last day instead.
        function weekViewStart() {
            const month = window.currentCalendarMonth || moment().format('YYYY-MM');
            const end = month === moment().format('YYYY-MM') ? moment() : moment(month + '-01').endOf('month');
            return end.clone().subtract(6, 'days').format('YYYY-MM-DD');
        }

        function changeCalendarView(viewType) {
            if (calendar) {
                if (viewType === 'timeGridWeek') {
                    calendar.changeView(viewType, weekViewStart());
                } else if (viewType === 'timeGridDay') {
                    // Today if it is on screen; from Week view otherwise its last day.
                    const view = calendar.view, today = moment().startOf('day');
                    const day = today.isSameOrAfter(moment(view.activeStart)) && today.isBefore(moment(view.activeEnd))
                        ? today
                        : (view.type === 'timeGridWeek' ? moment(view.activeEnd).subtract(1, 'day') : moment(calendar.getDate()));
                    calendar.changeView(viewType, day.format('YYYY-MM-DD'));
                } else {
                    calendar.changeView(viewType);
                }
                updateCalendarViewButtons(viewType);
            }
        }

        function updateCalendarViewButtons(viewType) {
            const names = { dayGridMonth: 'Month View', timeGridWeek: 'Week View', timeGridDay: 'Day View', listMonth: 'List View' };
            $('#currentCalendarView').text(names[viewType] || viewType);
        }

        function updateAttendanceStats(selectedMonth, encryptedId) {
            const url = new URL(window.location.href);
            url.searchParams.set('month', selectedMonth);
            window.history.pushState({}, '', url);

            const formattedMonth = moment(selectedMonth + '-01').format('MMMM YYYY');
            $('#currentMonthDisplay').text(formattedMonth);

            $.ajax({
                url: '/team/user/' + encryptedId + '/attendance-stats',
                type: 'GET',
                data: { month: selectedMonth, _token: '{{ csrf_token() }}' },
                beforeSend: function() {
                    $('.cal-nav-btn').prop('disabled', true);
                },
                success: function(response) {
                    // The user already moved on to another month: drop this answer.
                    if (selectedMonth !== window.currentCalendarMonth) return;
                    if (response.status) {
                        $('#stat-present').text(response.summary.present);
                        $('#stat-absent').text(response.summary.absent);
                        $('#stat-leave').text(response.summary.on_leave);
                        $('#stat-holiday').text(response.summary.holiday);
                        $('#stat-weekoff').text(response.summary.week_off);
                        $('#stat-workdays').text(response.summary.work_days);
                        $('#currentMonthDisplay').text(response.month_name);

                        updateAttendanceTable(selectedMonth, encryptedId);
                        updateAttendanceCalendar(selectedMonth, encryptedId);

                        showToast('Attendance data updated for ' + response.month_name, 'success');
                    } else {
                        showToast(response.message || 'Failed to update attendance data', 'error');
                    }
                },
                error: function(xhr) {
                    showToast('Error updating attendance data. Please try again.', 'error');
                },
                complete: function() {
                    $('#calendarView').removeClass('opacity-50');
                    $('#tableView').removeClass('opacity-50');
                    $('.member-stats-grid').removeClass('opacity-50');
                    $('.cal-nav-btn').prop('disabled', false);
                }
            });
        }

        function updateAttendanceTable(selectedMonth, encryptedId) {
            const startDate = moment(selectedMonth + '-01').format('YYYY-MM-DD');
            const endDate = moment(selectedMonth + '-01').endOf('month').format('YYYY-MM-DD');

            $.ajax({
                url: '/team/user/' + encryptedId + '/attendance/table',
                type: 'GET',
                data: { start_date: startDate, end_date: endDate, _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.status && response.data) {
                        $('.attendance-table tbody').empty();
                        response.data.forEach(function(record) {
                            let badgeClass = 'badge-secondary';
                            const statusLower = record.day_status.toLowerCase();
                            if (statusLower === 'present') badgeClass = 'badge-present';
                            else if (statusLower === 'absent') badgeClass = 'badge-absent';
                            else if (statusLower.includes('leave')) badgeClass = 'badge-leave';
                            else if (statusLower === 'holiday') badgeClass = 'badge-holiday';
                            else if (statusLower === 'week off') badgeClass = 'badge-week-off';
                            else if (statusLower === 'upcoming') badgeClass = 'badge-info';

                            const row = `<tr>
                                <td>${record.date}</td>
                                <td>${record.day_name}</td>
                                <td>${record.clock_in || '--:--'}</td>
                                <td>${record.clock_out || '--:--'}</td>
                                <td>${record.total_hours || '--'}</td>
                                <td><span class="badge-status ${badgeClass}">${record.day_status}</span></td>
                                <td>${record.task_count > 0 ? `<span class="badge bg-warning text-dark"><i class="feather-list"></i> ${record.task_count}</span>` : '--'}</td>
                            </tr>`;
                            $('.attendance-table tbody').append(row);
                        });
                    }
                }
            });
        }

        function updateAttendanceCalendar(selectedMonth, encryptedId) {
    // The whole month plus any other-month days the current view shows.
    const vis = window.calendarVisibleRange || {};
    let startDate = moment(selectedMonth + '-01').format('YYYY-MM-DD');
    let endDate = moment(selectedMonth + '-01').endOf('month').format('YYYY-MM-DD');
    if (vis.start && vis.start < startDate) startDate = vis.start;
    if (vis.end && vis.end > endDate) endDate = vis.end;

    $.ajax({
        url: '/team/user/' + encryptedId + '/attendance/calendar',
        type: 'GET',
        data: { start_date: startDate, end_date: endDate, _token: '{{ csrf_token() }}' },
        success: function(response) {
            // A late answer for a month the user already left must not move the
            // calendar (that ping-ponged Month view between June and July).
            if (selectedMonth !== window.currentCalendarMonth) return;
            if (response.status && calendar) {
                window.calendarLoadedRange = { start: startDate, end: endDate };
                // Clear all existing events
                calendar.removeAllEvents();
                
                // Check if response.events exists and is an array
                if (response.events && Array.isArray(response.events)) {
                    response.events.forEach(function(event) {
                        let eventTitle = event.title;
                        if (event.task_count && event.task_count > 0) {
                            eventTitle += ` 📋 ${event.task_count}`;
                        }
                        
                        // Add event to calendar
                        const style = calendarEventStyle(event.day_status);
                        calendar.addEvent({
                            id: event.id,
                            title: eventTitle,
                            start: event.start,
                            allDay: true,
                            backgroundColor: style.bg,
                            borderColor: style.bg,
                            textColor: style.text,
                            extendedProps: {
                                date: event.start,
                                day_name: event.day_status,
                                clock_in: event.clock_in,
                                clock_out: event.clock_out,
                                total_hours: event.total_hours,
                                status: event.day_status,
                                task_count: event.task_count || 0,
                                holiday_name: event.holiday_name,
                                details: event.details || null,
                                encrypted_user_id: encryptedId
                            }
                        });
                    });
                    window.workEvents = response.events.flatMap(e => buildWorkEvents(e.start, e.clock_in_at, e.clock_out_at, e.shifts, encryptedId));
                }
                syncWorkEvents();
                // No gotoDate()/render() here: the calendar is already on the date the
                // user navigated to, and jumping to the 1st re-triggered datesSet (loop).
            } else if (!response.status) {
                console.error('Failed to fetch calendar data:', response.message);
                showToast(response.message || 'Failed to load calendar data', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showToast('Error loading calendar data. Please try again.', 'error');
        }
    });
}

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            const bgClass = type === 'success' ? 'text-bg-success' : (type === 'error' ? 'text-bg-danger' : 'text-bg-warning');
            toast.className = `toast align-items-center ${bgClass} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;

            const toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '1050';
            toastContainer.appendChild(toast);
            document.body.appendChild(toastContainer);

            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();

            toast.addEventListener('hidden.bs.toast', function() {
                toastContainer.remove();
            });
        }
    </script>
@endsection