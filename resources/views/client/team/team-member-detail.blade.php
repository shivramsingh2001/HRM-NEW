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

        /* Hide FullCalendar toolbar */
        .fc-toolbar-chunk {
            display: none !important;
        }

        /* Loading text */
        .loading-text {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* Month picker styling */
        input[type="month"] {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-color: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.375rem 2rem 0.375rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: #374151;
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1.5em 1.5em;
        }

        input[type="month"]:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        input[type="month"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            position: absolute;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        /* Stats Cards */
        .stats-grid {
            margin: 15px 0 20px;
        }

        .stats-card {
            padding: 15px;
            border-radius: 10px;
            background: white;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            height: 100%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            border-color: #3b82f6;
        }

        .stats-number {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .stats-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Month Filter Card */
        .month-filter-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        /* Tabs */
        .nav-tabs {
            border-bottom: 2px solid #e5e7eb;
            padding: 0 5px;
        }

        .nav-tabs .nav-link {
            padding: 10px 20px;
            margin: 0 5px -2px 0;
            font-size: 14px;
        }

        .tab-content {
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 12px 12px;
            padding: 20px;
        }

        /* Info Cards */
        .info-card {
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            background-color: #ffffff;
        }

        .info-card .card-header {
            padding: 12px 15px;
            font-size: 14px;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-item {
            padding: 10px 15px;
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
            border-color: #3b82f6;
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
            background: #10b981;
            color: white;
        }

        .badge-absent {
            background: #ef4444;
            color: white;
        }

        .badge-leave {
            background: #3b82f6;
            color: white;
        }

        .badge-holiday {
            background: #8b5cf6;
            color: white;
        }

        .badge-week-off {
            background: #6c757d;
            color: white;
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
            padding: 24px 24px 16px;
            margin-bottom: 0 !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 12px 0 0;
        }

        .fc .fc-toolbar-title {
            font-size: 16px !important;
            font-weight: 600;
            color: #374151;
        }

        .fc .fc-button {
            padding: 6px 12px !important;
            font-size: 13px !important;
            background-color: #3b82f6 !important;
            border-color: #3b82f6 !important;
        }

        .fc .fc-button:hover {
            background-color: #2563eb !important;
            border-color: #2563eb !important;
        }

        .fc .fc-day-today {
            background-color: rgba(59, 130, 246, 0.1) !important;
        }

        .fc-daygrid-event {
            border-radius: 4px;
            border: none !important;
            padding: 2px 6px !important;
            font-size: 11px !important;
            font-weight: 500;
            margin: 1px 0;
        }

        /* Calendar Controls */
        .calendar-controls {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .calendar-view-options {
            display: flex;
            gap: 8px;
        }

        .calendar-view-btn {
            padding: 6px 12px;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .calendar-view-btn:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .calendar-view-btn.active {
            background: #3b82f6;
            border-color: #3b82f6;
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
            padding: 10px 12px !important;
            font-size: 13px;
        }

        .attendance-table th {
            font-size: 11px;
            white-space: nowrap;
            background: #f8fafc;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-header {
                padding: 20px;
                text-align: center;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
                margin: 0 auto 15px;
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
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <ul class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('team.index') }}">Team</a></li>
                <li class="breadcrumb-item active">Member Profile</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <a href="{{ route('team.index') }}" class="btn btn-light btn-sm">
                <i class="feather-arrow-left me-1"></i>Back
            </a>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-lg-2 col-md-3 text-center text-md-start">
                    <img src="{{ $profileImage }}" alt="{{ $userInfo->name }}" class="profile-avatar">
                </div>
                <div class="col-lg-10 col-md-9 mt-3 mt-md-0">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h4 class="mb-1">{{ strtoupper($userInfo->name) }}</h4>
                            <div class="d-flex flex-wrap gap-3 mb-2">
                                <div class="d-flex align-items-center">
                                    <i class="feather-briefcase me-2 fs-12"></i>
                                    <span class="fs-13">{{ $userInfo->designation ?? 'Not Assigned' }}</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="feather-hash me-2 fs-12"></i>
                                    <span class="fs-13">ID: {{ $userInfo->employee_id ?? 'N/A' }}</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="feather-mail me-2 fs-12"></i>
                                    <span class="fs-13">{{ $userInfo->email }}</span>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-3">
                                @if ($userInfo->contact)
                                    <div class="d-flex align-items-center">
                                        <i class="feather-phone me-2 fs-12"></i>
                                        <span class="fs-13">{{ $userInfo->contact }}</span>
                                    </div>
                                @endif
                                @if ($userInfo->department)
                                    <div class="d-flex align-items-center">
                                        <i class="feather-layers me-2 fs-12"></i>
                                        <span class="fs-13">{{ $userInfo->department }}</span>
                                    </div>
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

        <!-- Attendance Stats -->
        <div class="row stats-grid g-2">
            <div class="col-md-2 col-sm-4 col-6 text-center">
                <div class="stats-card">
                    <div class="stats-number" id="stat-present">{{ $attendanceSummary['present'] ?? 0 }}</div>
                    <div class="stats-label">Present</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 text-center">
                <div class="stats-card">
                    <div class="stats-number" id="stat-absent">{{ $attendanceSummary['absent'] ?? 0 }}</div>
                    <div class="stats-label">Absent</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 text-center">
                <div class="stats-card">
                    <div class="stats-number" id="stat-leave">{{ $attendanceSummary['on_leave'] ?? 0 }}</div>
                    <div class="stats-label">On Leave</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 text-center">
                <div class="stats-card">
                    <div class="stats-number" id="stat-holiday">{{ $attendanceSummary['holiday'] ?? 0 }}</div>
                    <div class="stats-label">Holidays</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 text-center">
                <div class="stats-card">
                    <div class="stats-number" id="stat-weekoff">{{ $attendanceSummary['week_off'] ?? 0 }}</div>
                    <div class="stats-label">Week Off</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 text-center">
                <div class="stats-card">
                    <div class="stats-number" id="stat-workdays">{{ $attendanceSummary['work_days'] ?? 0 }}</div>
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
            <div class="tab-pane fade show active" id="attendance" role="tabpanel">
                <!-- Month Filter -->
                <div class="month-filter-card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex flex-column flex-md-row align-items-center gap-2">
                                <div class="position-relative w-100 w-md-auto" style="max-width: 180px;">
                                    <input type="month" id="monthFilter" class="form-control form-control-sm"
                                        value="{{ $selectedMonth }}" max="{{ date('Y-m') }}"
                                        data-onboard-date="{{ $userCreatedDate ?? date('Y-m-d') }}"
                                        style="padding-right: 30px;">
                                    <i class="feather-calendar position-absolute"
                                        style="right: 8px; top: 50%; transform: translateY(-50%); color: #6b7280; pointer-events: none;"></i>
                                </div>
                                <button id="applyFilter" class="btn btn-primary btn-sm w-100 w-md-auto">
                                    <i class="feather-filter me-2"></i> Apply
                                </button>
                                <button id="resetFilter" class="btn btn-secondary btn-sm w-100 w-md-auto">
                                    <i class="feather-refresh-cw me-2"></i> Current
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 text-end mt-3 mt-md-0">
                            <div class="text-muted fs-12">
                                <i class="feather-calendar me-1"></i>
                                Showing: <span
                                    id="currentMonthDisplay">{{ date('F Y', strtotime($selectedMonth)) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calendar View -->
                <div class="attendance-section active" id="calendarView">
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
                            <div class="legend-color" style="background: #28a745;"></div><span>Present</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #dc3545;"></div><span>Absent</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #fd7e14;"></div><span>Leave</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #0d6efd;"></div><span>Holiday</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #6c757d;"></div><span>Week Off</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #ffc107;"></div><span>Checked In Only</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #6f42c1;"></div><span>Upcoming</span>
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

            $('#applyFilter').on('click', function() {
                const selectedMonth = $('#monthFilter').val();
                const encryptedId = '{{ encrypt($userInfo->id) }}';

                if (!selectedMonth) {
                    showToast('Please select a month', 'warning');
                    return;
                }

                const selectedDate = moment(selectedMonth + '-01');
                const currentDate = moment();

                if (selectedDate.isAfter(currentDate, 'month')) {
                    showToast('Cannot select future months', 'warning');
                    $('#monthFilter').val(currentDate.format('YYYY-MM'));
                    return;
                }

                const userOnboardDate = $('#monthFilter').data('onboard-date');
                if (userOnboardDate) {
                    const onboardDate = moment(userOnboardDate);
                    if (selectedDate.isBefore(onboardDate, 'month')) {
                        showToast('Selected month is before employee onboarding date', 'warning');
                        return;
                    }
                }

                $('#calendarView').addClass('opacity-50');
                $('#tableView').addClass('opacity-50');
                $('.stats-grid').addClass('opacity-50');

                updateAttendanceStats(selectedMonth, encryptedId);
            });

            $('#resetFilter').on('click', function(e) {
                e.preventDefault();
                const currentMonth = '{{ date('Y-m') }}';
                $('#monthFilter').val(currentMonth);
                $('#applyFilter').click();
            });
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

                let bgColor, title, borderColor;
                const statusLower = status.toLowerCase();

                switch (statusLower) {
                    case 'present':
                        bgColor = '#28a745';
                        borderColor = '#28a745';
                        title = 'Present';
                        break;
                    case 'absent':
                        bgColor = '#dc3545';
                        borderColor = '#dc3545';
                        title = 'Absent';
                        break;
                    case 'first half leave':
                    case 'second half leave':
                    case 'full day leave':
                        bgColor = '#fd7e14';
                        borderColor = '#fd7e14';
                        title = 'Leave';
                        break;
                    case 'holiday':
                        bgColor = '#0d6efd';
                        borderColor = '#0d6efd';
                        title = record.holiday_name || 'Holiday';
                        break;
                    case 'week off':
                        bgColor = '#6c757d';
                        borderColor = '#6c757d';
                        title = 'Week Off';
                        break;
                    case 'checked in only':
                        bgColor = '#ffc107';
                        borderColor = '#ffc107';
                        title = 'Checked In Only';
                        break;
                    case 'upcoming':
                        bgColor = '#6f42c1';
                        borderColor = '#6f42c1';
                        title = 'Upcoming';
                        break;
                    default:
                        bgColor = '#9ca3af';
                        borderColor = '#9ca3af';
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
                    textColor: '#ffffff',
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
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
                },
                themeSystem: 'standard',
                events: events,
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
                    
                    if (taskCount > 0) {
                        taskBadge = `<span class="task-count-badge">Tasks ${taskCount}</span>`;
                    }
                    
                    let cleanTitle = arg.event.title.replace(/📋 \d+/, '').trim();
                    
                    return {
                        html: `<div class="fc-event-main" style="display: flex; justify-content: space-between; align-items: center; padding: 2px 4px;">
                                    <span style="font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${cleanTitle}</span>
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
                    $('#applyFilter').prop('disabled', true).addClass('loading-text').html('<span class="loading-spinner"></span> Loading...');
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
                    $('.stats-grid').removeClass('opacity-50');
                    $('#applyFilter').prop('disabled', false).removeClass('loading-text').html('<i class="feather-filter me-1"></i> Apply');
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
                            textColor: event.color || '#ffffff',
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