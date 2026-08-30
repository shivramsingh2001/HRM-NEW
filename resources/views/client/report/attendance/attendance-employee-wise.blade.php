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
            padding: 20px 24px;
            border: 1px solid #eef2f6;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }
        .employee-header-card .emp-code {
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
        }
        .employee-header-card .emp-name {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }
        .employee-header-card .emp-dept {
            font-size: 13px;
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
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #eef2f6;
        }
        .summary-badges .badge-item {
            font-size: 12px;
            color: #475569;
            font-weight: 500;
        }
        .summary-badges .badge-item strong {
            font-weight: 700;
            color: #0f172a;
        }
        .summary-badges .badge-item .num {
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
        }
        .summary-badges .badge-item .num.present { color: #065f46; background: #d1fae5; }
        .summary-badges .badge-item .num.holiday { color: #1e40af; background: #dbeafe; }
        .summary-badges .badge-item .num.weekoff { color: #5b21b6; background: #ede9fe; }
        .summary-badges .badge-item .num.halfday { color: #0e7490; background: #cffafe; }
        .summary-badges .badge-item .num.absent { color: #991b1b; background: #fee2e2; }
        .summary-badges .badge-item .num.paiddays { color: #92400e; background: #fef3c7; }
        .summary-badges .badge-item .num.workhrs { color: #4f46e5; background: #eef2ff; }
        .summary-badges .badge-item .num.shorthrs { color: #b45309; background: #fef3c7; }
        .summary-badges .badge-item .num.othrs { color: #dc2626; background: #fee2e2; }

        /* Employee List Card */
        .employee-list-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #eef2f6;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .employee-list-card .card-header {
            padding: 16px 20px;
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
            padding: 12px 20px;
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
        .employee-row .emp-info .name {
            font-weight: 600;
            color: #0f172a;
            font-size: 14px;
        }
        .employee-row .emp-info .id {
            font-size: 12px;
            color: #64748b;
        }
        .employee-row .emp-dept {
            flex: 0 0 150px;
            font-size: 13px;
            color: #475569;
        }
        .employee-row .emp-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 12px;
            flex: 1;
        }
        .employee-row .emp-stats .stat {
            font-size: 12px;
            color: #475569;
        }
        .employee-row .emp-stats .stat strong {
            color: #0f172a;
        }
        .employee-row .emp-stats .stat .num {
            font-weight: 700;
        }
        .employee-row .emp-stats .stat .num.present { color: #10b981; }
        .employee-row .emp-stats .stat .num.absent { color: #ef4444; }
        .employee-row .emp-stats .stat .num.weekoff { color: #8b5cf6; }
        .employee-row .emp-stats .stat .num.halfday { color: #06b6d4; }
        .employee-row .emp-stats .stat .num.holiday { color: #3b82f6; }
        .employee-row .emp-stats .stat .num.leave { color: #f59e0b; }

        .employee-row .view-btn {
            flex: 0 0 auto;
        }
        .employee-row .view-btn .btn {
            padding: 4px 14px;
            font-size: 12px;
            border-radius: 6px;
            background: #4f46e5;
            color: white;
            border: none;
            transition: all 0.3s;
        }
        .employee-row .view-btn .btn:hover {
            background: #4338ca;
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
            font-size: 12px;
            min-width: 900px;
        }
        .detail-table thead th {
            background: #f1f5f9;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #475569;
            padding: 10px 12px;
            border-bottom: 2px solid #e2e8f0;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .detail-table tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
            text-align: center;
            vertical-align: middle;
            font-size: 12px;
        }
        .detail-table tbody tr:last-child td {
            border-bottom: none;
        }
        .detail-table tbody tr:hover td {
            background-color: #f8fafc;
        }

        /* Status Colors */
        .status-p { 
            color: #065f46; 
            font-weight: 700; 
            background: #d1fae5 !important; 
            border-radius: 4px;
            padding: 2px 10px;
            display: inline-block;
        }
        .status-a { 
            color: #991b1b; 
            font-weight: 700; 
            background: #fee2e2 !important; 
            border-radius: 4px;
            padding: 2px 10px;
            display: inline-block;
        }
        .status-l { 
            color: #92400e; 
            font-weight: 700; 
            background: #fef3c7 !important; 
            border-radius: 4px;
            padding: 2px 10px;
            display: inline-block;
        }
        .status-wo { 
            color: #5b21b6; 
            font-weight: 700; 
            background: #ede9fe !important; 
            border-radius: 4px;
            padding: 2px 10px;
            display: inline-block;
        }
        .status-h { 
            color: #1e40af; 
            font-weight: 700; 
            background: #dbeafe !important; 
            border-radius: 4px;
            padding: 2px 10px;
            display: inline-block;
        }
        .status-hd { 
            color: #0e7490; 
            font-weight: 700; 
            background: #cffafe !important; 
            border-radius: 4px;
            padding: 2px 10px;
            display: inline-block;
        }
        .status-ci { 
            color: #b45309; 
            font-weight: 700; 
            background: #fef3c7 !important; 
            border-radius: 4px;
            padding: 2px 10px;
            display: inline-block;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid #eef2f6;
            margin-bottom: 20px;
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
            padding: 6px 12px;
            font-size: 13px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            transition: all 0.3s;
            width: 100%;
        }
        .form-control-sm-custom:focus {
            border-color: #4f46e5;
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.08);
        }
        .btn-sm-custom {
            height: 38px;
            padding: 0 16px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        .btn-sm-custom:hover {
            background: #4338ca;
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
            font-size: 13px;
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
                font-size: 17px;
            }
            .summary-badges {
                gap: 6px 12px;
                padding: 10px 14px;
            }
            .summary-badges .badge-item {
                font-size: 11px;
            }
            .detail-table td, .detail-table th {
                padding: 6px 8px;
                font-size: 11px;
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

    <div class="main-content" style="padding: 30px !important;">
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
                        <button type="submit" class="btn-sm-custom">
                            <i class="feather-eye"></i> View
                        </button>
                    </div>
                    <div class="filter-item">
                        <a href="{{ route('report.attendance.detailed.index') }}" class="btn-sm-custom-outline">
                            <i class="feather-refresh-cw"></i> Reset
                        </a>
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
                        <div class="emp-dept">Dept: <strong>{{ $employee->department_name ?? 'N/A' }}</strong> | Desig: <strong>{{ $employee->designation_name ?? 'N/A' }}</strong></div>
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
                <span class="badge" style="background: #d1fae5; color: #065f46; padding: 6px 14px; font-size: 11px;">P - Present</span>
                <span class="badge" style="background: #fee2e2; color: #991b1b; padding: 6px 14px; font-size: 11px;">A - Absent</span>
                <span class="badge" style="background: #fef3c7; color: #92400e; padding: 6px 14px; font-size: 11px;">L - Leave</span>
                <span class="badge" style="background: #ede9fe; color: #5b21b6; padding: 6px 14px; font-size: 11px;">WO - Week Off</span>
                <span class="badge" style="background: #dbeafe; color: #1e40af; padding: 6px 14px; font-size: 11px;">H - Holiday</span>
                <span class="badge" style="background: #cffafe; color: #0e7490; padding: 6px 14px; font-size: 11px;">HD - Half Day</span>
                <span class="badge" style="background: #fef3c7; color: #b45309; padding: 6px 14px; font-size: 11px;">CI - Checked In</span>
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
                    <i class="feather-users" style="font-size: 48px; color: #cbd5e1;"></i>
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