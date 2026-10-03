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

        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-mid) 100%);
            color: white;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 10px;
            box-shadow: var(--shadow-md);
        }

        .profile-avatar {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            border: 2px solid white;
            object-fit: cover;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.18);
        }

        .profile-header h4 {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .profile-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            line-height: 1.3;
            background: rgba(255, 255, 255, .15);
            padding: 3px 9px;
            border-radius: 20px;
        }

        .profile-meta-item i {
            font-size: 10.5px;
        }

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

        .badge-present {
            background: #0D6EFD;
            color: #ffffff;
        }

        .badge-absent {
            background: #0D6EFD;
            color: #ffffff;
        }

        .badge-leave {
            background: #dbeafe;
            color: #0B5ED7;
        }

        .badge-holiday {
            background: #bfdbfe;
            color: #0B5ED7;
        }

        .badge-week-off {
            background: #eff6ff;
            color: #475569;
        }

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

        /* FullCalendar */
        #attendanceCalendar {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .fc {
            font-size: 13px;
        }

        .fc .fc-col-header-cell {
            background: #f8fafd;
            border-bottom: 2px solid #eef2f7;
            padding: 8px 0;
        }

        .fc .fc-toolbar {
            padding: 10px 14px;
            margin-bottom: 0 !important;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-mid) 100%);
            color: white;
            border-radius: 10px 12px 0 0;
        }

        .fc .fc-toolbar-title {
            font-size: 13px !important;
            font-weight: 600;
            color: #ffffff;
        }

        .fc .fc-button {
            padding: 4px 10px !important;
            font-size: 11.5px !important;
            background-color: var(--primary-mid) !important;
            border-color: var(--primary-mid) !important;
        }

        .fc .fc-button:hover {
            background-color: var(--primary-dark) !important;
            border-color: var(--primary-dark) !important;
        }

        .fc .fc-day-today {
            background-color: var(--primary-light) !important;
        }

        .fc-daygrid-event {
            border-radius: 4px;
            border: 1px solid !important;
            padding: 2px 6px !important;
            font-size: 11px !important;
            font-weight: 600;
            margin: 1px 0;
        }

        /* Calendar Controls */
        .calendar-controls {
            background: #f8fafc;
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid var(--border);
            margin-bottom: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .calendar-view-options {
            display: flex;
            gap: 6px;
        }

        .calendar-view-btn {
            padding: 4px 10px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 500;
            color: #64748b;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .calendar-view-btn:hover {
            border-color: var(--primary-mid);
            color: var(--primary-mid);
        }

        .calendar-view-btn.active {
            background: var(--primary-mid);
            border-color: var(--primary-mid);
            color: white;
        }

        /* Calendar Legend */
        .calendar-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #475569;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            border: 1px solid transparent;
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
            .profile-header {
                padding: 14px;
                text-align: center;
            }

            .profile-avatar {
                width: 80px;
                height: 80px;
                margin: 0 auto 12px;
            }

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

            .calendar-controls {
                flex-direction: column;
            }
            
            .calendar-legend {
                display: flex;
                flex-direction: column;
                flex-wrap: wrap;
                margin-top: 150px;
            }

        }
    </style>
@endsection

@section('content-area')
    <!-- Page Header -->
    <x-ui.page-header title="Member Profile" :crumbs="[['label' => 'Team', 'url' => route('team.index')]]" :back="route('team.index')" />

    <div class="main-content" style="padding: 20px !important;">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-lg-2 col-md-3 text-center text-md-start">
                    <img src="{{ $profileImage }}" alt="{{ $userInfo->name }}" class="profile-avatar">
                </div>
                <div class="col-lg-10 col-md-9 mt-2 mt-md-0">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h4 class="mb-1">{{ strtoupper($userInfo->name) }}</h4>
                            <div class="d-flex flex-wrap gap-2 mb-1">
                                <span class="profile-meta-item">
                                    <i class="feather-briefcase"></i>{{ $userInfo->designation ?? 'Not Assigned' }}
                                </span>
                                <span class="profile-meta-item">
                                    <i class="feather-hash"></i>ID: {{ $userInfo->employee_id ?? 'N/A' }}
                                </span>
                                <span class="profile-meta-item">
                                    <i class="feather-mail"></i>{{ $userInfo->email }}
                                </span>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                @if ($userInfo->contact)
                                    <span class="profile-meta-item">
                                        <i class="feather-phone"></i>{{ $userInfo->contact }}
                                    </span>
                                @endif
                                @if ($userInfo->department)
                                    <span class="profile-meta-item">
                                        <i class="feather-layers"></i>{{ $userInfo->department }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div>
                            @if ($todayAttendance)
                                <span
                                    class="badge-status {{ 'badge-' . strtolower(str_replace(' ', '-', $todayAttendance['day_status'])) }}">
                                    {{ $todayAttendance['day_status'] }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                    <div class="text-muted fs-12 mb-2">
                        <i class="feather-calendar me-1"></i>
                        Showing: <span id="currentMonthDisplay">{{ date('F Y', strtotime($selectedMonth)) }}</span>
                    </div>

                    <!-- Calendar Controls -->
                    <div class="calendar-controls">
                        <div class="calendar-view-options">
                            <button class="calendar-view-btn active" onclick="changeCalendarView('dayGridMonth')">
                                <i class="feather-grid"></i> Month
                            </button>
                            <button class="calendar-view-btn" onclick="changeCalendarView('timeGridWeek')">
                                <i class="feather-calendar"></i> Week
                            </button>
                            <button class="calendar-view-btn" onclick="changeCalendarView('timeGridDay')">
                                <i class="feather-sun"></i> Day
                            </button>
                            <button class="calendar-view-btn" onclick="changeCalendarView('listMonth')">
                                <i class="feather-list"></i> List
                            </button>
                        </div>
                    </div>

                    <!-- FullCalendar Container -->
                    <div id="attendanceCalendar" class="d-block"></div>

                    <!-- Legend -->
                    <div class="calendar-legend">
                        <div class="legend-item">
                            <div class="legend-color" style="background: #0D6EFD; border-color: #0B5ED7;"></div><span>Present</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #0D6EFD; border-color: #1e293b;"></div><span>Absent</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #dbeafe; border-color: #93c5fd;"></div><span>Leave</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #bfdbfe; border-color: #93c5fd;"></div><span>Holiday</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #eff6ff; border-color: #dbeafe;"></div><span>Week Off</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #93c5fd; border-color: #60a5fa;"></div><span>Checked In Only</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #f8fafc; border-color: #cbd5e1;"></div><span>Upcoming</span>
                        </div>
                        <!--<div class="legend-item">-->
                        <!--    <div class="legend-color" style="background: rgba(255,255,255,0.3);"></div><span>Has Tasks</span>-->
                        <!--</div>-->
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
                                                {{ \Carbon\Carbon::parse($record->clock_out)->format('h:i A') }}
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
    <script>
        $(document).ready(function() {
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

                // Single-color (blue) theme, matching the app's blue-only
                // convention (see the Team Attendance .stats-card family) —
                // statuses are distinguished by shade/fill intensity, not hue.
                let bgColor, title, borderColor, textColor;
                const statusLower = status.toLowerCase();

                switch (statusLower) {
                    case 'present':
                        bgColor = '#0D6EFD';
                        borderColor = '#0B5ED7';
                        textColor = '#ffffff';
                        title = 'Present';
                        break;
                    case 'halfday':
                        bgColor = '#60a5fa';
                        borderColor = '#3b82f6';
                        textColor = '#ffffff';
                        title = 'Half Day';
                        break;
                    case 'absent':
                        bgColor = '#0D6EFD';
                        borderColor = '#1e293b';
                        textColor = '#ffffff';
                        title = 'Absent';
                        break;
                    case 'first half leave':
                    case 'second half leave':
                    case 'full day leave':
                        bgColor = '#dbeafe';
                        borderColor = '#93c5fd';
                        textColor = '#0B5ED7';
                        title = 'Leave';
                        break;
                    case 'holiday':
                        bgColor = '#bfdbfe';
                        borderColor = '#93c5fd';
                        textColor = '#0B5ED7';
                        title = record.holiday_name || 'Holiday';
                        break;
                    case 'week off':
                        bgColor = '#eff6ff';
                        borderColor = '#dbeafe';
                        textColor = '#475569';
                        title = 'Week Off';
                        break;
                    case 'checked in only':
                        bgColor = '#93c5fd';
                        borderColor = '#60a5fa';
                        textColor = '#0D6EFD';
                        title = 'Checked In Only';
                        break;
                    case 'upcoming':
                        bgColor = '#f8fafc';
                        borderColor = '#cbd5e1';
                        textColor = '#64748b';
                        title = 'Upcoming';
                        break;
                    default:
                        bgColor = '#eff6ff';
                        borderColor = '#dbeafe';
                        textColor = '#475569';
                        title = status;
                }

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
                }
                if (record.total_hours) {
                    totalHours = record.total_hours;
                }

                events.push({
                    id: 'attendance_' + dateStr,
                    title: title,
                    start: dateStr,
                    allDay: true,
                    backgroundColor: bgColor,
                    borderColor: borderColor,
                    textColor: textColor,
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
                        encrypted_user_id: encryptedUserId,
                        description: `${status}<br>${record.clock_in ? 'In: ' + clockInTime : ''}${record.clock_out ? '<br>Out: ' + clockOutTime : ''}${record.total_hours ? '<br>Hours: ' + totalHours : ''}${taskCount > 0 ? '<br> Tasks: ' + taskCount : ''}`
                    }
                });
            });

            const selectedMonth = '{{ $selectedMonth }}';
            const initialDate = selectedMonth ? selectedMonth + '-01' : moment().format('YYYY-MM-DD');

            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                initialDate: initialDate,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: ''
                },
                themeSystem: 'standard',
                events: events,
                datesSet: function(info) {
                    // Native prev/next/today navigation replaces the old
                    // Apply/Reset month filter — fetch the newly-visible
                    // month's data as soon as the calendar moves.
                    const visibleMonth = moment(info.view.currentStart).format('YYYY-MM');
                    $('#currentMonthDisplay').text(moment(visibleMonth + '-01').format('MMMM YYYY'));
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
                eventContent: function(arg) {
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

                    return {
                        html: `<div class="fc-event-main" style="display: flex; justify-content: space-between; align-items: center; padding: 2px 4px; color: ${txtColor};">
                                    <span style="font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: ${txtColor};">${cleanTitle}</span>
                                    ${taskBadge}
                               </div>`
                    };
                },
                eventDisplay: 'block',
                dayMaxEvents: 3,
                height: 'auto',
                contentHeight: 400
            });

            calendar.render();
            updateCalendarViewButtons('dayGridMonth');
            window.calendar = calendar;
        }

        function changeCalendarView(viewType) {
            if (calendar) {
                calendar.changeView(viewType);
                updateCalendarViewButtons(viewType);
            }
        }

        function updateCalendarViewButtons(viewType) {
            $('.calendar-view-btn').removeClass('active');
            $(`.calendar-view-btn[onclick*="${viewType}"]`).addClass('active');
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
                    $('.fc-prev-button, .fc-next-button, .fc-today-button').prop('disabled', true);
                },
                success: function(response) {
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
                    $('.fc-prev-button, .fc-next-button, .fc-today-button').prop('disabled', false);
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
    const startDate = moment(selectedMonth + '-01').format('YYYY-MM-DD');
    const endDate = moment(selectedMonth + '-01').endOf('month').format('YYYY-MM-DD');

    $.ajax({
        url: '/team/user/' + encryptedId + '/attendance/calendar',
        type: 'GET',
        data: { start_date: startDate, end_date: endDate, _token: '{{ csrf_token() }}' },
        success: function(response) {
            if (response.status && calendar) {
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
                        calendar.addEvent({
                            id: event.id,
                            title: eventTitle,
                            start: event.start,
                            allDay: true,
                            backgroundColor: event.bgColor,
                            borderColor: event.borderColor,
                            textColor: event.color || '#1a2236',
                            extendedProps: {
                                date: event.start,
                                day_name: event.day_status,
                                clock_in: event.clock_in,
                                clock_out: event.clock_out,
                                total_hours: event.total_hours,
                                status: event.day_status,
                                task_count: event.task_count || 0,
                                holiday_name: event.holiday_name,
                                encrypted_user_id: encryptedId
                            }
                        });
                    });
                }
                
                // Navigate to the selected month
                calendar.gotoDate(startDate);
                
                // Force calendar to re-render
                calendar.render();
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