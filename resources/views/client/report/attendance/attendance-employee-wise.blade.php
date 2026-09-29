@extends('client.layout.master')

@php
    use Carbon\Carbon;
@endphp

@section('style')
    <style>
        /* Employee Header */
        .employee-header-card {
            background: white;
            border-radius: 12px;
            padding: 14px 17px;
            border: 1px solid #eef2f6;
            margin-bottom: 14px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }
        .employee-header-card .emp-code {
            font-size: 11px;
            font-weight: 500;
            color: #64748b;
        }
        .employee-header-card .emp-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }
        .employee-header-card .emp-dept {
            font-size: 10.5px;
            color: #475569;
        }
        .employee-header-card .emp-dept strong {
            color: #0f172a;
        }

        /* Summary Badges */
        .summary-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            background: #f8fafc;
            padding: 8px 14px;
            border-radius: 10px;
            margin-bottom: 14px;
            border: 1px solid #eef2f6;
        }
        .summary-badges .badge-item {
            font-size: 10px;
            color: #475569;
            font-weight: 500;
        }
        .summary-badges .badge-item strong {
            font-weight: 700;
            color: #0f172a;
        }
        .summary-badges .badge-item .num {
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .summary-badges .badge-item .num.present { color: #1e3a8a; background: #e3edfe; }
        .summary-badges .badge-item .num.holiday { color: #1e40af; background: #dbeafe; }
        .summary-badges .badge-item .num.weekoff { color: #16295e; background: #e3edfe; }
        .summary-badges .badge-item .num.halfday { color: #2563eb; background: #e3edfe; }
        .summary-badges .badge-item .num.absent { color: #475569; background: #e2e8f0; }
        .summary-badges .badge-item .num.paiddays { color: #1e3a8a; background: #bfd3f7; }
        .summary-badges .badge-item .num.workhrs { color: #1e3a8a; background: #e3edfe; }
        .summary-badges .badge-item .num.shorthrs { color: #1e3a8a; background: #bfd3f7; }
        .summary-badges .badge-item .num.othrs { color: #475569; background: #e2e8f0; }

        /* Employee List Card */
        .employee-list-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #eef2f6;
            overflow: hidden;
            margin-bottom: 14px;
        }
        .employee-list-card .card-header {
            padding: 11px 14px;
            background: #f8fafc;
            border-bottom: 1px solid #eef2f6;
        }
        .employee-list-card .card-body {
            padding: 0;
        }

        /* Employee Row */
        .employee-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            padding: 8px 14px;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
            cursor: pointer;
        }
        .employee-row:hover {
            background: #f8fafc;
        }
        .employee-row:last-child {
            border-bottom: none;
        }
        .employee-row .emp-info {
            flex: 0 0 250px;
            min-width: 200px;
        }
        /* Sized to match the project-wide employee-name/employee-email
           convention (12px/600-weight name, 8px muted id) — this row has
           no avatar so it keeps its own .emp-info/.name/.id classes rather
           than the shared .employee-info markup. */
        .employee-row .emp-info .name {
            font-weight: 600;
            color: #0f172a;
            font-size: 10px;
        }
        .employee-row .emp-info .id {
            font-size: 8px;
            color: #64748b;
        }
        .employee-row .emp-dept {
            flex: 0 0 150px;
            font-size: 10.5px;
            color: #475569;
        }
        .employee-row .emp-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 12px;
            flex: 1;
        }
        .employee-row .emp-stats .stat {
            font-size: 10px;
            color: #475569;
        }
        .employee-row .emp-stats .stat strong {
            color: #0f172a;
        }
        .employee-row .emp-stats .stat .num {
            font-weight: 700;
        }
        .employee-row .emp-stats .stat .num.present { color: #1e3a8a; }
        .employee-row .emp-stats .stat .num.absent { color: #475569; }
        .employee-row .emp-stats .stat .num.weekoff { color: #2563eb; }
        .employee-row .emp-stats .stat .num.halfday { color: #2563eb; }
        .employee-row .emp-stats .stat .num.holiday { color: #3b82f6; }
        .employee-row .emp-stats .stat .num.leave { color: #2563eb; }

        .employee-row .view-btn {
            flex: 0 0 auto;
        }
        .employee-row .view-btn .btn {
            padding: 3px 10px;
            font-size: 10px;
            border-radius: 6px;
            background: #1e3a8a;
            color: white;
            border: none;
            transition: all 0.3s;
        }
        .employee-row .view-btn .btn:hover {
            background: #16295e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        /* Table */
        .detail-table-wrapper {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #eef2f6;
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            min-width: 900px;
        }
        .detail-table thead th {
            background: #f1f5f9;
            font-weight: 600;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #475569;
            padding: 7px 8px;
            border-bottom: 2px solid #e2e8f0;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .detail-table tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #f1f5f9;
            text-align: center;
            vertical-align: middle;
            font-size: 10px;
        }
        .detail-table tbody tr:last-child td {
            border-bottom: none;
        }
        .detail-table tbody tr:hover td {
            background-color: #f8fafc;
        }

        /* Status Colors */
        .status-p { 
            color: #1e3a8a; 
            font-weight: 700; 
            background: #e3edfe !important; 
            border-radius: 4px;
            padding: 2px 7px;
            display: inline-block;
        }
        .status-a { 
            color: #475569; 
            font-weight: 700; 
            background: #e2e8f0 !important; 
            border-radius: 4px;
            padding: 2px 7px;
            display: inline-block;
        }
        .status-l { 
            color: #1e3a8a; 
            font-weight: 700; 
            background: #bfd3f7 !important; 
            border-radius: 4px;
            padding: 2px 7px;
            display: inline-block;
        }
        .status-wo { 
            color: #16295e; 
            font-weight: 700; 
            background: #e3edfe !important; 
            border-radius: 4px;
            padding: 2px 7px;
            display: inline-block;
        }
        .status-h { 
            color: #1e40af; 
            font-weight: 700; 
            background: #dbeafe !important; 
            border-radius: 4px;
            padding: 2px 7px;
            display: inline-block;
        }
        .status-hd { 
            color: #2563eb; 
            font-weight: 700; 
            background: #e3edfe !important; 
            border-radius: 4px;
            padding: 2px 7px;
            display: inline-block;
        }
        .status-ci { 
            color: #1e3a8a; 
            font-weight: 700; 
            background: #bfd3f7 !important; 
            border-radius: 4px;
            padding: 2px 7px;
            display: inline-block;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 11px 14px;
            border: 1px solid #eef2f6;
            margin-bottom: 14px;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }
        .filter-item {
            flex: 0 0 auto;
        }
        .filter-item.employee-select {
            flex: 1;
            min-width: 200px;
        }
        .form-control-sm-custom {
            height: 38px;
            padding: 4px 8px;
            font-size: 10.5px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            transition: all 0.3s;
            width: 100%;
        }
        .form-control-sm-custom:focus {
            border-color: #1e3a8a;
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.08);
        }
        .btn-sm-custom {
            height: 38px;
            padding: 0 16px;
            background: #1e3a8a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 10.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        .btn-sm-custom:hover {
            background: #16295e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            color: white;
            text-decoration: none;
        }
        .btn-sm-custom-outline {
            height: 38px;
            padding: 0 16px;
            background: white;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 10.5px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        .btn-sm-custom-outline:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
            text-decoration: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .filter-item {
                flex: 1 1 100%;
            }
            .filter-item.employee-select {
                flex: 1 1 100%;
            }
            .employee-header-card .emp-name {
                font-size: 13px;
            }
            .summary-badges {
                gap: 6px 12px;
                padding: 7px 10px;
            }
            .summary-badges .badge-item {
                font-size: 9.5px;
            }
            .detail-table td, .detail-table th {
                padding: 4px 6px;
                font-size: 9.5px;
            }
            .employee-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .employee-row .emp-info {
                flex: 1 1 100%;
            }
            .employee-row .emp-dept {
                flex: 1 1 100%;
            }
            .employee-row .emp-stats {
                flex: 1 1 100%;
            }
            .employee-row .view-btn {
                width: 100%;
            }
            .employee-row .view-btn .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Employee Wise Attendance Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
 <li class="breadcrumb-item"><a href="{{ route('report.attendance.index') }}">Reports</a></li>
                <li class="breadcrumb-item active">Employee Wise Attendance</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                @if(isset($employee) && isset($selectedMonth))
                    <a href="{{ route('report.attendance.detailed.export', ['employee_id' => $employee->id, 'month' => $selectedMonth]) }}" 
                       class="btn btn-sm btn-primary" target="_blank">
                        <i class="feather-download me-1"></i> Export CSV
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <!-- Filter Section -->
        <div class="filter-section">
            <form action="{{ route('report.attendance.detailed.index') }}" method="GET">
                <div class="filter-row">
                    <div class="filter-item">
                        <input type="month" name="month" class="form-control-sm-custom" 
                               value="{{ request('month', now()->format('Y-m')) }}" style="width: 160px;">
                    </div>
                    <div class="filter-item employee-select">
                        <select name="employee_id" class="form-control-sm-custom" onchange="this.form.submit()">
                            <option value="">-- All Employees --</option>
                            @foreach($allEmployees ?? [] as $emp)
                                <option value="{{ $emp->id }}" {{ isset($employee) && $employee->id == $emp->id ? 'selected' : '' }}>
                                    {{ $emp->employee_id }} - {{ $emp->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="branch_id" class="form-control-sm-custom" onchange="this.form.submit()" style="width: 160px;">
                            <option value="">-- All Branches --</option>
                            @foreach($branches ?? [] as $b)
                                <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <input type="text" name="search" onchange="this.form.submit()" onkeydown="if (event.key === 'Enter') { event.preventDefault(); this.form.submit(); }" class="form-control-sm-custom" placeholder="Search name or ID…"
                               value="{{ request('search') }}" style="width: 170px;">
                    </div>
                    <div class="filter-item">
                        <a href="{{ route('report.attendance.detailed.index') }}" class="btn-sm-custom-outline" title="Reset filters" aria-label="Reset filters">
                            <i class="feather-refresh-cw"></i></a>
                    </div>
                </div>
            </form>
        </div>

        @if(isset($employee) && !empty($dailyData))
            <!-- Single Employee Detail View -->
            <!-- Employee Header -->
            <div class="employee-header-card">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="emp-code">EmpCode: {{ $employee->employee_id ?? 'N/A' }}</div>
                        <div class="emp-name">{{ $employee->name }}</div>
                        <div class="emp-dept">Dept: <strong>{{ $employee->department_name ?? 'N/A' }}</strong> | Desig: <strong>{{ $employee->designation_name ?? 'N/A' }}</strong> | Branch: <strong>{{ $employee->branch_name ?? 'N/A' }}</strong></div>
                    </div>
                    <div class="col-md-6 text-md-end mt-2 mt-md-0">
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="feather-calendar me-1"></i> {{ $selectedDate->format('F Y') }}
                        </span>
                        <span class="badge bg-light text-dark px-3 py-2 ms-2">
                            <i class="feather-clock me-1"></i> Total Days: {{ $daysInMonth }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Summary Badges -->
            <div class="summary-badges">
                <span class="badge-item">Present: <strong class="num present">{{ $summary['present'] }}</strong></span>
                <span class="badge-item">Holiday: <strong class="num holiday">{{ $summary['holiday'] }}</strong></span>
                <span class="badge-item">WeekOff: <strong class="num weekoff">{{ $summary['weekoff'] }}</strong></span>
                <span class="badge-item">Halfday: <strong class="num halfday">{{ $summary['halfday'] }}</strong></span>
                <span class="badge-item">Absent: <strong class="num absent">{{ $summary['absent'] }}</strong></span>
                <span class="badge-item">PaidDay: <strong class="num paiddays">{{ $summary['paid_days'] }}</strong></span>
                <span class="badge-item">WorkHrs: <strong class="num workhrs">{{ isset($summary['work_hours']) ? number_format($summary['work_hours']/60, 2) . ' hrs' : '00:00' }}</strong></span>
                <span class="badge-item">ShortHrs: <strong class="num shorthrs">{{ isset($summary['short_hours']) ? number_format($summary['short_hours']/60, 2) . ' hrs' : '00:00' }}</strong></span>
                <span class="badge-item">OT Hrs: <strong class="num othrs">{{ isset($summary['ot_hours']) ? number_format($summary['ot_hours']/60, 2) . ' hrs' : '00:00' }}</strong></span>
            </div>

            <!-- Daily Detail Table -->
            <div class="detail-table-wrapper">
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>IN Time</th>
                            <th>OUT Time</th>
                            <th>Shift From</th>
                            <th>Shift To</th>
                            <th>Working</th>
                            <th>Short hrs.</th>
                            <th>O.Times</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dailyData as $day => $data)
                            <tr>
                                <td><strong>{{ $day }}</strong></td>
                                <td>{{ $data['in_time'] }}</td>
                                <td>{{ $data['out_time'] }}</td>
                                <td>{{ $data['shift_from'] }}</td>
                                <td>{{ $data['shift_to'] }}</td>
                                <td class="working-hours">{{ $data['working'] }}</td>
                                <td class="short-hours">{{ $data['short_hrs'] }}</td>
                                <td class="ot-hours">{{ $data['ot_times'] }}</td>
                                <td>
                                    <span class="
                                        @if($data['status'] == 'P') status-p
                                        @elseif($data['status'] == 'A') status-a
                                        @elseif($data['status'] == 'L') status-l
                                        @elseif($data['status'] == 'WO') status-wo
                                        @elseif($data['status'] == 'H') status-h
                                        @elseif($data['status'] == 'HD') status-hd
                                        @elseif($data['status'] == 'CI') status-ci
                                        @endif
                                    ">
                                        {{ $data['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="text-muted">No attendance data found for this month.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Legend -->
            <div class="mt-3 d-flex flex-wrap gap-2">
                <span class="badge" style="background: #e3edfe; color: #1e3a8a; padding: 4px 10px; font-size: 9.5px;">P - Present</span>
                <span class="badge" style="background: #e2e8f0; color: #475569; padding: 4px 10px; font-size: 9.5px;">A - Absent</span>
                <span class="badge" style="background: #bfd3f7; color: #1e3a8a; padding: 4px 10px; font-size: 9.5px;">L - Leave</span>
                <span class="badge" style="background: #e3edfe; color: #16295e; padding: 4px 10px; font-size: 9.5px;">WO - Week Off</span>
                <span class="badge" style="background: #dbeafe; color: #1e40af; padding: 4px 10px; font-size: 9.5px;">H - Holiday</span>
                <span class="badge" style="background: #e3edfe; color: #2563eb; padding: 4px 10px; font-size: 9.5px;">HD - Half Day</span>
                <span class="badge" style="background: #bfd3f7; color: #1e3a8a; padding: 4px 10px; font-size: 9.5px;">CI - Checked In</span>
            </div>

        @elseif(isset($employeeData) && count($employeeData) > 0)
            <!-- All Employees Summary View -->
            <div class="employee-list-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="feather-users me-2"></i>
                        Employee Attendance Summary - {{ $selectedDate->format('F Y') }}
                    </h6>
                    <span class="badge bg-primary">{{ count($employeeData) }} Employees</span>
                </div>
                <div class="card-body">
                    @foreach($employeeData as $data)
                        @php
                            $emp = $data['employee'];
                            $summary = $data['summary'];
                        @endphp
                        <div class="employee-row">
                            <div class="emp-info">
                                <div class="name">{{ $emp->name }}</div>
                                <div class="id">{{ $emp->employee_id ?? 'N/A' }}</div>
                            </div>
                            <div class="emp-dept">
                                {{ $emp->department_name ?? 'N/A' }}
                                <br><small class="text-muted">{{ $emp->branch_name ?? 'No branch' }}</small>
                            </div>
                            <div class="emp-stats">
                                <span class="stat">P: <strong class="num present">{{ $summary['present'] }}</strong></span>
                                <span class="stat">A: <strong class="num absent">{{ $summary['absent'] }}</strong></span>
                                <span class="stat">WO: <strong class="num weekoff">{{ $summary['weekoff'] }}</strong></span>
                                <span class="stat">HD: <strong class="num halfday">{{ $summary['halfday'] }}</strong></span>
                                <span class="stat">H: <strong class="num holiday">{{ $summary['holiday'] }}</strong></span>
                                <span class="stat">L: <strong class="num leave">{{ $summary['paid_days'] }}</strong></span>
                                <span class="stat">WH: <strong>{{ isset($summary['work_hours']) ? number_format($summary['work_hours']/60, 1) . 'h' : '0h' }}</strong></span>
                            </div>
                            <div class="view-btn">
                                <a href="{{ route('report.attendance.detailed.index', ['employee_id' => $emp->id, 'month' => $selectedMonth]) }}" 
                                   class="btn">
                                    <i class="feather-eye"></i> View
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        @else
            <!-- No Data -->
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="feather-users" style="font-size: 32px; color: #cbd5e1;"></i>
                    <h5 class="mt-3">No Employees Found</h5>
                    <p class="text-muted">No employees found for the selected filters.</p>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('script-area')
    <script>
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000",
            "extendedTimeOut": "1000"
        };

        // Auto-submit on month change
        document.querySelector('input[name="month"]')?.addEventListener('change', function() {
            this.closest('form').submit();
        });

        // Auto-submit on employee select change
        document.querySelector('select[name="employee_id"]')?.addEventListener('change', function() {
            this.closest('form').submit();
        });
    </script>
@endsection