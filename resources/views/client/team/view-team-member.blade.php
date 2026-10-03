@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== MODERN STATS CARDS ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }

        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            cursor: default;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stats-card:hover::before {
            opacity: 1;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.1);
            border-color: #d1d5db;
        }

        .stats-card.total-card::before {
            background: linear-gradient(90deg, var(--primary), var(--primary-mid));
        }

        .stats-card.present-card::before {
            background: var(--success);
        }

        .stats-card.absent-card::before {
            background: var(--danger);
        }

        .stats-card.leave-card::before {
            background: var(--warning);
        }

        .stats-card.holiday-card::before {
            background: var(--purple);
        }

        .stats-card.weekoff-card::before {
            background: var(--warning);
        }

        .stats-icon-wrapper {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .stats-card:hover .stats-icon-wrapper {
            transform: scale(1.05);
        }

        .total-card .stats-icon-wrapper {
            background: var(--primary-light);
        }

        .total-card .stats-icon-wrapper i {
            color: var(--icon-color, #0D6EFD);
            font-size: 15px;
        }

        .present-card .stats-icon-wrapper {
            background: var(--success-light);
        }

        .present-card .stats-icon-wrapper i {
            color: var(--success);
            font-size: 15px;
        }

        .absent-card .stats-icon-wrapper {
            background: var(--danger-light);
        }

        .absent-card .stats-icon-wrapper i {
            color: var(--danger);
            font-size: 15px;
        }

        .leave-card .stats-icon-wrapper {
            background: var(--warning-light);
        }

        .leave-card .stats-icon-wrapper i {
            color: var(--warning);
            font-size: 15px;
        }

        .holiday-card .stats-icon-wrapper {
            background: var(--purple-light);
        }

        .holiday-card .stats-icon-wrapper i {
            color: var(--purple);
            font-size: 15px;
        }

        .weekoff-card .stats-icon-wrapper {
            background: var(--warning-light);
        }

        .weekoff-card .stats-icon-wrapper i {
            color: var(--warning);
            font-size: 15px;
        }

        .stats-content {
            flex: 1;
            min-width: 0;
        }

        .stats-amount-main {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
            margin-bottom: 1px;
            letter-spacing: -0.5px;
        }

        .stats-label {
            font-size: 10.5px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0;
        }




        .card-body {
            padding: 0;
        }








        .table-responsive {
            border-radius: 0 0 12px 12px;
        }


        /* Badges */
        .badge {
            padding: 4px 10px;
            font-weight: 600;
            font-size: 10px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        .badge-present {
            background: #d1fae5 !important;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .badge-absent {
            background: #fee2e2 !important;
            color: #991b1b;
            border-color: #fecaca;
        }

        .badge-on_leave {
            background: #fef3c7 !important;
            color: #92400e;
            border-color: #fde68a;
        }

        .badge-holiday {
            background: #dbeafe !important;
            color: var(--primary);
            border-color: #bfdbfe;
        }

        .badge-week_off {
            background: #ede9fe !important;
            color: #5b21b6;
            border-color: #ddd6fe;
        }

        .badge-checked_in_only {
            background: #fef3c7 !important;
            color: #92400e;
            border-color: #fde68a;
        }

        .badge-halfday {
            background: #fef3c7 !important;
            color: #92400e;
            border-color: #fde68a;
        }

        .badge-first_half {
            background: #fef3c7 !important;
            color: #92400e;
            border-color: #fde68a;
        }

        .badge-second_half {
            background: #fef3c7 !important;
            color: #92400e;
            border-color: #fde68a;
        }

        .status-dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .status-dot.present {
            background: #10b981;
        }

        .status-dot.absent {
            background: #ef4444;
        }

        .status-dot.on_leave {
            background: #f59e0b;
        }

        .status-dot.holiday {
            background: #3b82f6;
        }

        .status-dot.week_off {
            background: #8b5cf6;
        }

        .status-dot.checked_in_only {
            background: #f59e0b;
        }

        .status-dot.halfday {
            background: #f59e0b;
        }

        .status-dot.first_half {
            background: #f59e0b;
        }

        .status-dot.second_half {
            background: #f59e0b;
        }







        /* ==================== MODAL STYLES ==================== */
        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            border-bottom: 1px solid #f1f5f9;
            padding: 18px 24px;
            background: #fafbfc;
            border-radius: 16px 16px 0 0;
        }

        .modal-header .modal-title {
            font-weight: 600;
            font-size: 11.5px;
            color: #0f172a;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            border-top: 1px solid #f1f5f9;
            padding: 16px 24px;
            background: #fafbfc;
            border-radius: 0 0 16px 16px;
        }

        .form-label {
            font-weight: 600;
            font-size: 11.5px;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-label .required {
            color: #ef4444;
            margin-left: 2px;
        }

        .form-control {
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            padding: 10px 14px;
            font-size: 11.5px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.18);
        }

        .form-control.is-invalid {
            border-color: #ef4444;
        }

        .form-control.is-invalid:focus {
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.08);
        }

        .invalid-feedback {
            font-size: 12px;
            color: #ef4444;
            margin-top: 4px;
        }

        /* Employee Info Card */
        .employee-info-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .employee-info-card .avatar-lg {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-light), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 700;
            font-size: 11.5px;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .employee-info-card .info h6 {
            margin-bottom: 2px;
            font-weight: 600;
            color: #0f172a;
            font-size: 12px;
        }

        .employee-info-card .info p {
            margin-bottom: 0;
            font-size: 11.5px;
            color: #64748b;
        }

        /* Shift Info Card */
        .shift-info-card {
            background: #f0fdf4;
            border-radius: 10px;
            padding: 12px 16px;
            border: 1px solid #bbf7d0;
        }

        .shift-info-card .shift-label {
            color: #64748b;
            font-size: 11px;
            font-weight: 500;
        }

        .shift-info-card .shift-value {
            font-weight: 600;
            color: #0f172a;
            font-size: 11.5px;
        }

        .shift-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .shift-badge.overnight {
            background: #fef3c7;
            color: #92400e;
        }

        .shift-badge.regular {
            background: #dbeafe;
            color: var(--primary);
        }

        .shift-badge.no-shift {
            background: #fee2e2;
            color: #991b1b;
        }

        .admin-badge {
            background: #dbeafe;
            color: var(--primary);
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-info-custom {
            background: var(--primary-light) !important;
            color: var(--primary) !important;
            font-weight: 600 !important;
            padding: 4px 12px !important;
            border-radius: 16px !important;
            border: 1px solid var(--border-focus) !important;
            font-size: 11px !important;
        }

        /* Empty State */
        .empty-state {
            padding: 40px 20px;
            text-align: center;
        }

        .empty-state i {
            font-size: 56px;
            color: #d1d5db;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .empty-state h4 {
            color: #0f172a;
            font-size: 11.5px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .empty-state p {
            color: #64748b;
            font-size: 11.5px;
            margin-bottom: 0;
        }

        /* ==================== TOAST CUSTOMIZATIONS ==================== */
        #toast-container>div {
            opacity: 1 !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            border-radius: 10px !important;
            padding: 14px 16px 14px 50px !important;
            background-position: 16px center !important;
            background-repeat: no-repeat !important;
            background-size: 20px !important;
            width: auto !important;
            max-width: 350px !important;
        }

        #toast-container>div:hover {
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2) !important;
        }

        .toast-success {
            background-color: #10b981 !important;
        }

        .toast-error {
            background-color: #ef4444 !important;
        }

        .toast-warning {
            background-color: #f59e0b !important;
        }

        .toast-info {
            background-color: var(--primary) !important;
        }

        /* Half Day Status Select Custom Styles */
        .attendance-status-select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 11.5px;
            background: white;
            transition: all 0.3s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            cursor: pointer;
        }

        .attendance-status-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.18);
            outline: none;
        }











        /* Responsive Pagination */
        @media (max-width: 768px) {



        }

        @media (max-width: 480px) {
            .pagination .page-item.hide-mobile {
                display: none;
            }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }


            .employee-info {
                min-width: 100px;
            }

            .employee-avatar {
                width: 28px;
                height: 28px;
                font-size: 10px;
            }

            .employee-name-text {
                font-size: 11px;
            }

            .employee-email-text {
                font-size: 8px;
            }


            .modal-dialog {
                margin: 10px;
            }

            .employee-info-card {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .stats-card {
                padding: 12px 14px;
                gap: 10px;
            }

            .stats-icon-wrapper {
                width: 36px;
                height: 36px;
            }

            .stats-icon-wrapper i {
                font-size: 18px !important;
            }

            .stats-amount-main {
                font-size: 11.5px;
            }

            .stats-label {
                font-size: 9px;
            }
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Team Attendance" />

    <div class="main-content" style="padding: 20px !important;">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stats-card total-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-users"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statusCount['total'] ?? 0 }}</div>
                    <div class="stats-label">Total Team</div>
                </div>
            </div>
            <div class="stats-card present-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-check-circle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statusCount['present'] ?? 0 }}</div>
                    <div class="stats-label">Present</div>
                </div>
            </div>
            <div class="stats-card absent-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-x-circle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statusCount['absent'] ?? 0 }}</div>
                    <div class="stats-label">Absent</div>
                </div>
            </div>
            <div class="stats-card leave-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-calendar"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statusCount['on_leave'] ?? 0 }}</div>
                    <div class="stats-label">On Leave</div>
                </div>
            </div>
            <div class="stats-card holiday-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-home"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statusCount['holiday'] ?? 0 }}</div>
                    <div class="stats-label">Holiday</div>
                </div>
            </div>
            <div class="stats-card weekoff-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-sun"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statusCount['weekoff'] ?? 0 }}</div>
                    <div class="stats-label">Week Off</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Team Members
                </div>
                @if (request()->hasAny(['date', 'status', 'search', 'branch_id']))
                    <a href="{{ route('team.index') }}" class="reset-btn"
                        style="height: auto; padding: 4px 12px; font-size: 12px;">
                        <i class="feather-x"></i> Clear Filters
                    </a>
                @endif
            </div>

            <form action="{{ route('team.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item search">
                        <div class="search-wrapper">
                            <i class="feather-search"></i>
                            <input type="text" name="search" placeholder="Search by name, ID, email..."
                                value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="filter-item date-picker">
                        <input type="date" name="date" value="{{ request('date', date('Y-m-d')) }}"
                            max="{{ date('Y-m-d') }}" onchange="this.form.submit()">
                    </div>

                    <div class="filter-item status-filter">
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="present" {{ request('status') == 'present' ? 'selected' : '' }}>✅ Present
                            </option>
                            <option value="absent" {{ request('status') == 'absent' ? 'selected' : '' }}>❌ Absent</option>
                            <option value="on_leave" {{ request('status') == 'on_leave' ? 'selected' : '' }}>🏖️ On Leave
                            </option>
                            <option value="holiday" {{ request('status') == 'holiday' ? 'selected' : '' }}>🎉 Holiday
                            </option>
                            <option value="week_off" {{ request('status') == 'week_off' ? 'selected' : '' }}>📅 Week Off
                            </option>
                            <option value="halfday" {{ request('status') == 'halfday' ? 'selected' : '' }}>📅 Halfday
                            </option>
                            <option value="checked_in_only" {{ request('status') == 'checked_in_only' ? 'selected' : '' }}>
                                ⏳ Checked In Only</option>

                        </select>
                    </div>

                    <div class="filter-item branch-filter">
                        <select name="branch_id" onchange="this.form.submit()">
                            <option value="">All Branches</option>
                            @foreach ($allBranches as $branch)
                                <option value="{{ $branch->id }}"
                                    {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-item">
                        <a href="{{ route('team.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Team Members Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table" id="teamTable">
                                <thead>
                                    <tr>
                                        <th width="40">#</th>
                                        <th>Employee</th>
                                        <th>Designation</th>
                                        <th>Department</th>
                                        <th>Branch</th>
                                        <th>Status</th>
                                        <th>Punch In</th>
                                        <th>Punch Out</th>
                                        <th>Total Hours</th>
                                        <th width="120">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($teamData as $index => $member)
                                        @php
                                            $status = strtolower($member->status ?? 'absent');
                                            $statusClass = match ($status) {
                                                'present' => 'badge-present',
                                                'absent' => 'badge-absent',
                                                'on_leave',
                                                'first half leave',
                                                'second half leave',
                                                'full day leave'
                                                    => 'badge-on_leave',
                                                'halfday' => 'badge-halfday',
                                                'first_half' => 'badge-first_half',
                                                'second_half' => 'badge-second_half',
                                                'holiday' => 'badge-holiday',
                                                'week_off', 'week off' => 'badge-week_off',
                                                'checked in only' => 'badge-checked_in_only',
                                                default => 'badge-secondary',
                                            };
                                            $statusLabel = match ($status) {
                                                'present' => 'Present',
                                                'absent' => 'Absent',
                                                'on_leave' => 'On Leave',
                                                'first half leave' => 'First Half Leave',
                                                'second half leave' => 'Second Half Leave',
                                                'full day leave' => 'Full Day Leave',
                                                'halfday' => 'Half Day',
                                                'first_half' => 'First Half',
                                                'second_half' => 'Second Half',
                                                'holiday' => 'Holiday',
                                                'week_off', 'week off' => 'Week Off',
                                                'checked in only' => 'Checked In Only',
                                                default => ucfirst($status),
                                            };
                                            $statusDot = match ($status) {
                                                'present' => 'present',
                                                'absent' => 'absent',
                                                'on_leave',
                                                'first half leave',
                                                'second half leave',
                                                'full day leave'
                                                    => 'on_leave',
                                                'halfday' => 'halfday',
                                                'first_half' => 'first_half',
                                                'second_half' => 'second_half',
                                                'holiday' => 'holiday',
                                                'week_off', 'week off' => 'week_off',
                                                'checked in only' => 'checked_in_only',
                                                default => 'absent',
                                            };
                                            $isAbsent = $status == 'absent';
                                            $isAdmin =
                                                app(\App\Services\RbacService::class)->scopeFor(
                                                    auth()->user(),
                                                    'attendance',
                                                    'edit',
                                                ) === 'company';
                                            // Managers may mark their own reportees; the roster is already
                                            // filtered to reporting_head, and MarkAttendanceRequest re-checks.
                                            $canMarkAttendance = app(\App\Services\RbacService::class)->can(
                                                auth()->user(),
                                                'attendance',
                                                'edit',
                                            );

                                            // Calculate serial number with pagination
                                            $serialNumber =
                                                ($teamData->currentPage() - 1) * $teamData->perPage() +
                                                $loop->index +
                                                1;
                                        @endphp
                                        <tr>
                                            <td>{{ $serialNumber }}</td>
                                            <td>
                                                <div class="employee-info">
                                                    <div class="employee-avatar">
                                                        {{ strtoupper(substr($member->name ?? 'N/A', 0, 2)) }}
                                                    </div>
                                                    <div class="employee-details">
                                                        <div class="employee-name-text">{{ $member->name ?? 'N/A' }}
                                                            <small>( {{ $member->employee_id ?? 'N/A' }} )</small>
                                                        </div>
                                                        <div class="employee-email-text">
                                                            {{ $member->email ?? 'N/A' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ $member->designation ?? 'N/A' }}</td>
                                            <td>{{ $member->department ?? 'N/A' }}</td>
                                            <td>{{ $member->branch ?? 'N/A' }}</td>
                                            <td>
                                                <span class="badge {{ $statusClass }}">
                                                    <span class="status-dot {{ $statusDot }}"></span>
                                                    {{ $statusLabel }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($member->punch_in)
                                                    <span style="font-weight: 500; color: #0f172a;">
                                                        {{ \Carbon\Carbon::parse($member->punch_in)->format('h:i A') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($member->punch_out)
                                                    <span style="font-weight: 500; color: #0f172a;">
                                                        {{ \Carbon\Carbon::parse($member->punch_out)->format('h:i A') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($member->punch_in && $member->punch_out)
                                                    @php
                                                        $clockIn = \Carbon\Carbon::parse($member->punch_in);
                                                        $clockOut = \Carbon\Carbon::parse($member->punch_out);
                                                        $diff = $clockIn->diff($clockOut);
                                                        $hours = $diff->h + $diff->i / 60;
                                                    @endphp
                                                    <span style="font-weight: 600; color: var(--primary);">
                                                        {{ $member->total_hours }} hrs
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <!-- View Button -->
                                                    <a href="{{ route('team.member-detail', ['id' => encrypt($member->id)]) }}"
                                                        class="action-btn view-btn" title="View Member">
                                                        <i class="feather-eye"></i>
                                                    </a>

                                                    <!-- Mark Attendance Button - Admin / HR / Manager (own reportees) -->
                                                    @if ($canMarkAttendance)
                                                        <button type="button" class="action-btn mark-btn"
                                                            onclick="openMarkAttendanceModal({{ $member->id }}, '{{ $member->name }}', '{{ $member->employee_id }}', '{{ $member->designation ?? 'N/A' }}')"
                                                            title="Mark Attendance">
                                                            <i class="feather-edit-2"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-users"></i>
                                                    <h4>No Team Members Found</h4>
                                                    <p>No team members available for the selected date.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if ($teamData->count() > 0)
                        <x-ui.pagination-footer :paginator="$teamData" label="members" />
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Mark Attendance Modal -->
    <div class="modal fade-scale" id="markAttendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered compact-modal compact-modal-plain modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="feather-edit-2 me-2" style="color: var(--icon-color, #0D6EFD);"></i>
                        Mark Attendance
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Employee Info -->
                    <div class="employee-info-card mb-3">
                        <div class="avatar-lg" id="modalEmployeeAvatar">
                            {{-- Dynamic --}}
                        </div>
                        <div class="info">
                            <h6 id="modalEmployeeName">Employee Name</h6>
                            <p>
                                <span id="modalEmployeeId">ID</span> •
                                <span id="modalEmployeeDesignation">Designation</span>
                            </p>
                            <p class="text-muted" style="font-size: 12px;">
                                <i class="feather-calendar me-1"></i>
                                Date: {{ \Carbon\Carbon::parse(request('date', date('Y-m-d')))->format('d M Y') }}
                            </p>
                        </div>
                    </div>

                    <!-- Shift Info -->
                    <div class="shift-info-card mb-3" id="shiftInfoCard">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div>
                                <span class="shift-label">Shift:</span>
                                <span class="shift-value" id="modalShiftName">Loading...</span>
                            </div>
                            <div>
                                <span class="shift-label">Start:</span>
                                <span class="shift-value" id="modalShiftStart">--:--</span>
                            </div>
                            <div>
                                <span class="shift-label">End:</span>
                                <span class="shift-value" id="modalShiftEnd">--:--</span>
                            </div>
                            <div>
                                <span class="shift-badge regular" id="modalShiftType">☀️ Regular Shift</span>
                            </div>
                        </div>
                        <div class="mt-2 small text-muted" id="shiftNote" style="display: none;">
                            <i class="feather-info me-1"></i>
                            <span id="shiftNoteText"></span>
                        </div>
                    </div>

                    <form id="markAttendanceForm" method="POST">
                        @csrf
                        <input type="hidden" name="user_id" id="markUserId">
                        <input type="hidden" name="date" id="attendanceDate"
                            value="{{ request('date', date('Y-m-d')) }}">

                        <!-- Attendance Status Selection -->
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="feather-check-circle me-1" style="color: var(--icon-color, #0D6EFD);"></i>
                                Attendance Status <span class="required">*</span>
                            </label>
                            <select class="attendance-status-select form-control" name="status" id="attendanceStatus">
                                <option value="present">✅ Present (Full Day)</option>
                                <option value="half_day">🌓 Half Day</option>
                                <option value="first_half_leave">🌅 First Half Leave (Morning)</option>
                                <option value="second_half_leave">🌇 Second Half Leave (Afternoon)</option>
                                <option value="on_leave">📅 On Leave (Full Day)</option>
                                <option value="absent">❌ Absent</option>
                                <option value="holiday">🎉 Holiday</option>
                                <option value="weekoff">🛌 Week Off</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <!-- Optional date range: apply the same status from `date` through `end_date` -->
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="feather-calendar me-1" style="color: var(--icon-color, #0D6EFD);"></i>
                                Apply through <span class="text-muted" style="font-size: 11px;">(optional — leave blank
                                    for a single day)</span>
                            </label>
                            <input type="date" class="form-control" name="end_date" id="attendanceEndDate"
                                value="{{ request('date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}">
                            <small class="text-muted" style="font-size: 11px; display: block; margin-top: 4px;">
                                <i class="feather-info me-1"></i>
                                When set to a later date, the selected status is applied to every day in the range.
                            </small>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="row g-3" id="clockTimeFields">
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="feather-clock me-1" style="color: var(--icon-color, #0D6EFD);"></i>
                                    Clock In Time <span class="required">*</span>
                                </label>
                                <input type="time" class="form-control" name="clock_in" id="clockInTime">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="feather-clock me-1" style="color: var(--icon-color, #0D6EFD);"></i>
                                    Clock Out Time <span class="required">*</span>
                                </label>
                                <input type="time" class="form-control" name="clock_out" id="clockOutTime">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>

                        <div class="mt-3" id="leaveTypeField" style="display: none;">
                            <label class="form-label">
                                <i class="feather-calendar me-1" style="color: var(--icon-color, #0D6EFD);"></i>
                                Leave Type <span class="required">*</span>
                            </label>
                            <select class="form-control" name="leave_type_id" id="leaveTypeId">
                                <option value="">-- Select leave type --</option>
                                @foreach ($leaveTypes ?? [] as $lt)
                                    <option value="{{ $lt->id }}">{{ $lt->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                            <small class="text-muted" style="font-size: 11px; display: block; margin-top: 4px;">
                                <i class="feather-info me-1"></i>
                                An approved leave will be created and the employee's balance deducted
                                (loss of pay if the balance is short).
                            </small>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">
                                <i class="feather-message-square me-1" style="color: var(--icon-color, #0D6EFD);"></i>
                                Remarks
                            </label>
                            <textarea class="form-control" name="remarks" id="attendanceRemarks" rows="2"
                                placeholder="e.g., Admin marked attendance for {{ \Carbon\Carbon::parse(request('date', date('Y-m-d')))->format('d M Y') }}"></textarea>
                        </div>

                        <div class="mt-2">
                            <div class="alert alert-info"
                                style="background: var(--primary-light); border-color: var(--border-focus); color: var(--primary); padding: 8px 12px; font-size: 12px; border-radius: 8px;">
                                <i class="feather-shield me-1"></i>
                                Attendance will be marked by: <strong>{{ auth()->user()->name }}</strong>
                                ({{ auth()->user()->role }})
                            </div>
                        </div>
                    </form>

                    <!-- Recent changes (audit trail) -->
                    <div class="mt-3">
                        <button type="button" class="btn btn-link p-0" style="font-size: 12px; text-decoration: none;"
                            onclick="toggleAttendanceLog()">
                            <i class="feather-clock me-1" id="attendanceLogToggleIcon"></i>
                            <span id="attendanceLogToggleText">Show recent changes</span>
                        </button>
                        <div id="attendanceLogPanel" style="display: none; margin-top: 8px;">
                            <div id="attendanceLogBody" style="max-height: 220px; overflow-y: auto; font-size: 12px;">
                                <div class="text-muted">Loading…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="markAttendanceSubmitBtn"
                        onclick="submitMarkAttendance()">
                        <i class="feather-check me-1"></i> Mark Attendance
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Auto-submit on date change
            $('input[name="date"]').on('change', function() {
                $('#filterForm').submit();
            });

            // Auto-submit the LIST FILTER on status change (scoped to the filter
            // form so it does not fire for the mark-attendance modal's own
            // status select, which also uses name="status").
            $('#filterForm select[name="status"]').on('change', function() {
                $('#filterForm').submit();
            });

            // Debounced live search, matching the employee list page's pattern.
            let searchTimeout;
            $('#filterForm input[name="search"]').on('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    $('#filterForm').submit();
                }, 500);
            });

            // Set default time values
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const currentTime = hours + ':' + minutes;

            // When modal is shown
            $('#markAttendanceModal').on('shown.bs.modal', function() {
                if (!$('#clockInTime').val()) {
                    $('#clockInTime').val(currentTime);
                }
                if (!$('#clockOutTime').val()) {
                    const clockOut = new Date();
                    clockOut.setHours(clockOut.getHours() + 1);
                    const outHours = String(clockOut.getHours()).padStart(2, '0');
                    const outMinutes = String(clockOut.getMinutes()).padStart(2, '0');
                    $('#clockOutTime').val(outHours + ':' + outMinutes);
                }
            });

            // Toastr options
            toastr.options = {
                "closeButton": true,
                "progressBar": true,
                "positionClass": "toast-top-right",
                "timeOut": "5000",
                "extendedTimeOut": "2000",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut",
                "tapToDismiss": true,
                "showDuration": 300,
                "hideDuration": 1000,
                "closeHtml": '<button>&times;</button>'
            };
        });

        /**
         * Open the Mark Attendance Modal with employee details
         */
        function openMarkAttendanceModal(userId, name, employeeId, designation) {
            // Set employee details
            document.getElementById('modalEmployeeAvatar').textContent = name.substring(0, 2).toUpperCase();
            document.getElementById('modalEmployeeName').textContent = name;
            document.getElementById('modalEmployeeId').textContent = employeeId || 'N/A';
            document.getElementById('modalEmployeeDesignation').textContent = designation || 'N/A';
            document.getElementById('markUserId').value = userId;

            // Reset shift info to loading state
            document.getElementById('modalShiftName').textContent = 'Loading...';
            document.getElementById('modalShiftStart').textContent = '--:--';
            document.getElementById('modalShiftEnd').textContent = '--:--';
            const shiftType = document.getElementById('modalShiftType');
            shiftType.textContent = '⏳ Loading...';
            shiftType.className = 'shift-badge regular';

            // Fetch shift details
            const date = document.querySelector('input[name="date"]').value || '{{ date('Y-m-d') }}';

            fetch('{{ route('team.get-user-shift') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        date: date
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.shift) {
                        const shift = data.shift;
                        document.getElementById('modalShiftName').textContent = shift.shift_name || 'Not Assigned';
                        document.getElementById('modalShiftStart').textContent = shift.start_time || '--:--';
                        document.getElementById('modalShiftEnd').textContent = shift.end_time || '--:--';

                        const shiftType = document.getElementById('modalShiftType');
                        if (shift.is_overnight) {
                            shiftType.textContent = '🌙 Overnight Shift';
                            shiftType.className = 'shift-badge overnight';
                            document.getElementById('shiftNote').style.display = 'block';
                            document.getElementById('shiftNoteText').textContent =
                                'This is an overnight shift. Clock out time will be on the next day.';
                        } else {
                            shiftType.textContent = '☀️ Regular Shift';
                            shiftType.className = 'shift-badge regular';
                            document.getElementById('shiftNote').style.display = 'none';
                        }

                        // Set default clock in/out based on shift
                        if (shift.start_time) {
                            document.getElementById('clockInTime').value = shift.start_time;
                        }
                        if (shift.end_time) {
                            document.getElementById('clockOutTime').value = shift.end_time;
                        }
                    } else {
                        document.getElementById('modalShiftName').textContent = 'No Shift Assigned';
                        document.getElementById('modalShiftStart').textContent = '--:--';
                        document.getElementById('modalShiftEnd').textContent = '--:--';
                        const shiftType = document.getElementById('modalShiftType');
                        shiftType.textContent = '⚠️ No Shift';
                        shiftType.className = 'shift-badge no-shift';
                        document.getElementById('shiftNote').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error fetching shift:', error);
                    document.getElementById('modalShiftName').textContent = 'Error Loading Shift';
                    document.getElementById('modalShiftStart').textContent = '--:--';
                    document.getElementById('modalShiftEnd').textContent = '--:--';
                    const shiftType = document.getElementById('modalShiftType');
                    shiftType.textContent = '⚠️ Error';
                    shiftType.className = 'shift-badge no-shift';
                    document.getElementById('shiftNote').style.display = 'none';
                });

            // Set default remarks
            const dateFormatted = '{{ \Carbon\Carbon::parse(request('date', date('Y-m-d')))->format('d M Y') }}';
            const adminName = '{{ auth()->user()->name }}';
            document.getElementById('attendanceRemarks').value =
                `Admin (${adminName}) marked attendance for ${dateFormatted}`;

            // Reset the optional range end to the selected day, and constrain its min.
            const rangeEnd = document.getElementById('attendanceEndDate');
            if (rangeEnd) {
                rangeEnd.value = date;
                rangeEnd.min = date;
            }

            // Reset the recent-changes panel (lazy-loaded on toggle).
            const logPanel = document.getElementById('attendanceLogPanel');
            if (logPanel) {
                logPanel.style.display = 'none';
                logPanel.dataset.loadedFor = '';
                document.getElementById('attendanceLogToggleText').textContent = 'Show recent changes';
            }

            // Show modal
            var modal = new bootstrap.Modal(document.getElementById('markAttendanceModal'));
            modal.show();
        }

        /**
         * Toggle + lazy-load the read-only attendance change log for the employee.
         */
        function toggleAttendanceLog() {
            const panel = document.getElementById('attendanceLogPanel');
            const label = document.getElementById('attendanceLogToggleText');
            if (!panel) return;

            if (panel.style.display === 'none') {
                panel.style.display = 'block';
                label.textContent = 'Hide recent changes';
                const userId = document.getElementById('markUserId').value;
                if (panel.dataset.loadedFor !== String(userId)) {
                    loadAttendanceLog(userId);
                }
            } else {
                panel.style.display = 'none';
                label.textContent = 'Show recent changes';
            }
        }

        function loadAttendanceLog(userId) {
            const body = document.getElementById('attendanceLogBody');
            const panel = document.getElementById('attendanceLogPanel');
            if (!userId) {
                body.innerHTML = '<div class="text-muted">No employee selected.</div>';
                return;
            }
            body.innerHTML = '<div class="text-muted">Loading…</div>';

            const url = '{{ route('team.attendance-log') }}?user_id=' + encodeURIComponent(userId);
            fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !Array.isArray(data.data) || data.data.length === 0) {
                        body.innerHTML = '<div class="text-muted">No recorded changes in the last 30 days.</div>';
                        return;
                    }
                    panel.dataset.loadedFor = String(userId);
                    body.innerHTML = data.data.map(row => {
                        const changes = (row.changes || [])
                            .map(c => {
                                const from = (c.from === null || c.from === '' || typeof c.from ===
                                    'undefined') ? '—' : c.from;
                                const to = (c.to === null || c.to === '' || typeof c.to === 'undefined') ?
                                    '—' : c.to;
                                return '<div style="padding-left:10px;"><code>' + escapeLogHtml(c.field) +
                                    '</code>: ' +
                                    escapeLogHtml(String(from)) + ' &rarr; ' + escapeLogHtml(String(to)) +
                                    '</div>';
                            })
                            .join('');
                        return '<div style="border-bottom:1px solid #eee; padding:6px 0;">' +
                            '<div><strong>' + escapeLogHtml(row.source || row.event_type || 'change') +
                            '</strong>' +
                            '<span class="text-muted"> · ' + escapeLogHtml(row.event_time || '') +
                            '</span></div>' +
                            '<div class="text-muted">by ' + escapeLogHtml(row.actor || 'System') +
                            (row.actor_role ? ' (' + escapeLogHtml(row.actor_role) + ')' : '') + '</div>' +
                            (row.reason ? '<div class="text-muted">' + escapeLogHtml(row.reason) + '</div>' :
                                '') +
                            changes +
                            '</div>';
                    }).join('');
                })
                .catch(() => {
                    body.innerHTML = '<div class="text-danger">Failed to load change history.</div>';
                });
        }

        function escapeLogHtml(s) {
            return String(s).replace(/[&<>"']/g, m => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [m]));
        }

        /**
         * Submit the mark attendance form via AJAX
         */
        // Statuses that need clock in/out; the rest are leave/absent.
        const CLOCK_STATUSES = ['present', 'half_day'];
        const LEAVE_STATUSES = ['on_leave', 'first_half_leave', 'second_half_leave'];

        function onMarkStatusChange() {
            const status = document.getElementById('attendanceStatus').value;
            const needsClock = CLOCK_STATUSES.includes(status);
            const isLeave = LEAVE_STATUSES.includes(status);
            document.getElementById('clockTimeFields').style.display = needsClock ? '' : 'none';
            document.getElementById('leaveTypeField').style.display = isLeave ? '' : 'none';
        }
        document.addEventListener('DOMContentLoaded', function() {
            const sel = document.getElementById('attendanceStatus');
            if (sel) {
                sel.addEventListener('change', onMarkStatusChange);
                onMarkStatusChange();
            }
        });

        function submitMarkAttendance() {
            const form = document.getElementById('markAttendanceForm');
            const formData = new FormData(form);

            const status = document.getElementById('attendanceStatus').value;
            if (!status) {
                toastr.error('Please select an attendance status');
                return;
            }
            const needsClock = CLOCK_STATUSES.includes(status);
            const isLeave = LEAVE_STATUSES.includes(status);

            let clockIn = document.getElementById('clockInTime').value;
            let clockOut = document.getElementById('clockOutTime').value;

            if (needsClock) {
                if (!clockIn || !clockOut) {
                    toastr.error('Clock In and Clock Out are required for ' + status.replace('_', ' '));
                    return;
                }
                clockIn = clockIn.substring(0, 5);
                clockOut = clockOut.substring(0, 5);

                const timeRegex = /^([0-1][0-9]|2[0-3]):[0-5][0-9]$/;
                if (!timeRegex.test(clockIn) || !timeRegex.test(clockOut)) {
                    toastr.error('Invalid time format. Please use HH:MM (24-hour).');
                    return;
                }

                const shiftType = document.getElementById('modalShiftType');
                const isOvernight = shiftType && shiftType.textContent.includes('Overnight');
                if (!isOvernight && clockOut <= clockIn) {
                    toastr.error('Clock Out must be after Clock In for a regular shift');
                    return;
                }

                formData.set('clock_in', clockIn);
                formData.set('clock_out', clockOut);
            } else {
                formData.delete('clock_in');
                formData.delete('clock_out');
            }

            if (isLeave) {
                const ltId = document.getElementById('leaveTypeId').value;
                if (!ltId) {
                    toastr.error('Please select a leave type');
                    return;
                }
                formData.set('leave_type_id', ltId);
            } else {
                formData.delete('leave_type_id');
            }

            formData.set('status', status);
            let attendanceStatus = status;

            // Show loading state
            const submitBtn = document.getElementById('markAttendanceSubmitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="feather-loader me-1"></i> Saving...';
            submitBtn.disabled = true;

            // Get the selected date from the filter
            const selectedDate = document.querySelector('input[name="date"]').value || '{{ date('Y-m-d') }}';
            formData.set('date', selectedDate);

            // Log the data being sent for debugging
            console.log('Sending data:');
            for (var pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }

            fetch('{{ route('team.mark-attendance') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;

                    if (data.success === true) {
                        let message = data.message || 'Attendance marked successfully!';

                        // Add shift info to success message
                        if (data.shift_details && data.shift_details.is_overnight) {
                            message += ' (Overnight shift: ' +
                                data.shift_details.clock_in + ' to ' +
                                data.shift_details.clock_out + ')';
                        }

                        // Add status info
                        const statusLabels = {
                            'present': 'Present (Full Day)',
                            'half_day': 'Half Day',
                            'first_half_leave': 'First Half Leave',
                            'second_half_leave': 'Second Half Leave',
                            'on_leave': 'On Leave',
                            'absent': 'Absent',
                            'holiday': 'Holiday',
                            'weekoff': 'Week Off'
                        };
                        const statusLabel = statusLabels[attendanceStatus] || attendanceStatus;
                        message += ' | Status: ' + statusLabel;

                        toastr.success(message);
                        // Close modal
                        var modal = bootstrap.Modal.getInstance(document.getElementById('markAttendanceModal'));
                        if (modal) {
                            modal.hide();
                        }
                        // Reload page after 1.5 seconds
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        toastr.error(data.message || 'Failed to mark attendance');
                    }
                })
                .catch(error => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    toastr.error('An error occurred while marking attendance');
                    console.error('Error:', error);
                });
        }
    </script>
@endsection
