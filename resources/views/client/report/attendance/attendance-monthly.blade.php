@extends('client.layout.master')

@section('title', 'Attendance Summary Report')

@section('style')
    <style>
        /* ==================== FILTER SECTION ==================== */
        .filter-wrapper {
            background: white;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            padding: 11px 14px;
            margin-bottom: 17px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 600;
            color: #1e293b;
        }

        .filter-title i {
            color: #1e3a8a;
            font-size: 12px;
        }

        .filter-title .badge-count {
            background: #e3edfe;
            color: #1e3a8a;
            font-size: 9.5px;
            padding: 2px 7px;
            border-radius: 20px;
            font-weight: 600;
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

        .filter-item.search-filter {
            flex: 1;
            min-width: 200px;
        }

        /* Search Wrapper */
        .search-wrapper {
            position: relative;
            width: 100%;
        }

        .search-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 12px;
            pointer-events: none;
            transition: color 0.3s;
        }

        .search-wrapper:focus-within i {
            color: #1e3a8a;
        }

        .search-wrapper .form-control {
            width: 100%;
            height: 40px;
            padding: 6px 10px 6px 27px;
            font-size: 10.5px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            transition: all 0.3s;
            color: #0f172a;
        }

        .search-wrapper .form-control:focus {
            border-color: #1e3a8a;
            outline: none;
            background: white;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
        }

        .search-wrapper .form-control::placeholder {
            color: #94a3b8;
            font-size: 10px;
        }

        /* Select Dropdowns */
        .filter-select {
            width: 100%;
            height: 40px;
            padding: 6px 22px 6px 10px;
            font-size: 10.5px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 12px center;
            background-size: 14px;
            appearance: none;
            cursor: pointer;
            transition: all 0.3s;
            color: #0f172a;
            font-weight: 500;
        }

        .filter-select:hover {
            background-color: white;
            border-color: #cbd5e1;
        }

        .filter-select:focus {
            border-color: #1e3a8a;
            outline: none;
            background-color: white;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
        }

        .filter-select option {
            padding: 6px;
        }

        .month-select {
            width: 200px;
            height: 40px;
            padding: 6px 8px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 10.5px;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.3s;
            color: #0f172a;
            font-weight: 500;
        }

        .month-select:focus {
            border-color: #1e3a8a;
            outline: none;
            background: white;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
        }

        .month-select:hover {
            background: white;
            border-color: #cbd5e1;
        }

        /* Buttons */
        .btn-sm-custom {
            height: 40px;
            padding: 0 20px;
            background: #1e3a8a;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 10.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
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
            height: 40px;
            padding: 0 16px;
            background: white;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 10.5px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .btn-sm-custom-outline:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            text-decoration: none;
        }

        .btn-sm-custom-outline i {
            font-size: 11px;
        }

        .reset-button {
            height: 40px;
            padding: 0 16px;
            background: white;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 10.5px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .reset-button:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            text-decoration: none;
        }

        .reset-button i {
            font-size: 11px;
        }

        /* ==================== TABLE STYLES ==================== */
        .table-wrapper {
            background: white;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #475569;
            border-bottom-width: 1px;
            padding: 10px 11px;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            font-size: 10.5px;
            padding: 8px 11px;
            border-bottom: 1px solid #f1f5f9;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        .table tfoot {
            background-color: #f1f5f9;
            font-weight: 600;
        }

        .table tfoot td {
            padding: 10px 11px;
            border-top: 2px solid #e2e8f0;
        }

        /* Employee Info */
        .employee-info {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
        }

        .employee-info:hover {
            text-decoration: none;
        }

        .employee-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #e3edfe;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e3a8a;
            font-weight: 600;
            font-size: 10.5px;
            text-transform: uppercase;
            flex-shrink: 0;
            transition: all 0.3s;
        }

        .employee-info:hover .employee-avatar {
            transform: scale(1.05);
        }

        .employee-details {
            line-height: 1.3;
            min-width: 0;
        }

        .employee-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 10.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .employee-name small {
            font-weight: 400;
            color: #94a3b8;
            font-size: 9.5px;
        }

        .employee-email {
            font-size: 9.5px;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Badges */
        .badge {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 9.5px;
            font-weight: 600;
            display: inline-block;
            min-width: 32px;
            text-align: center;
        }

        .badge-success {
            background: #e3edfe;
            color: #1e3a8a;
        }

        .badge-warning {
            background: #bfd3f7;
            color: #1e3a8a;
        }

        .badge-danger {
            background: #e2e8f0;
            color: #475569;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-purple {
            background: #e3edfe;
            color: #16295e;
        }

        .badge-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .badge-dark {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* Empty State */
        .empty-state {
            padding: 42px 14px;
            text-align: center;
        }

        .empty-state i {
            font-size: 32px;
            color: #cbd5e1;
        }

        .empty-state h4 {
            color: #0f172a;
            font-size: 14px;
            margin-top: 11px;
        }

        .empty-state p {
            color: #94a3b8;
            font-size: 11px;
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
            }

            .filter-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .filter-row {
                flex-direction: column;
                width: 100%;
            }

            .filter-item {
                width: 100%;
            }

            .filter-item.search-filter {
                width: 100%;
            }

            .month-select {
                width: 100%;
            }

            .btn-sm-custom,
            .btn-sm-custom-outline,
            .reset-button {
                width: 100%;
                justify-content: center;
            }

            .table {
                min-width: 1200px;
            }

            .employee-name {
                font-size: 10px;
            }

            .employee-email {
                font-size: 9px;
            }
        }

        /* Print Styles */
        @media print {
            .filter-wrapper {
                display: none;
            }
            .page-header-right {
                display: none;
            }
            .table th {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .badge {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .employee-avatar {
                background: #e3edfe !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Attendance Summary Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('report.attendance.index') }}">Reports</a></li>
                <li class="breadcrumb-item active">Monthly Summary</li>
            </ul>
        </div>
        {{-- <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-light" onclick="window.location.reload()" title="Refresh">
                        <i class="feather-refresh-cw"></i>
                    </button>
                    <button class="btn btn-light" onclick="printReport()" title="Print Report">
                        <i class="feather-printer"></i>
                    </button>
                </div>
            </div>
        </div> --}}
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Report
                    @php
                        $activeFilters = 0;
                        if(request('month') && request('month') != now()->format('Y-m')) $activeFilters++;
                        if(request('search')) $activeFilters++;
                        if(request('department')) $activeFilters++;
                    @endphp
                    @if($activeFilters > 0)
                        <span class="badge-count">{{ $activeFilters }} active</span>
                    @endif
                </div>
                {{-- @if(request()->hasAny(['month', 'search', 'department']))
                    <a href="{{ route('team.attendance-summary') }}" class="reset-button" style="border-color: #475569; color: #475569;">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif --}}
            </div>

            <form action="{{ route('team.attendance-summary') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <select name="month" class="month-select" onchange="this.form.submit()">
                            @foreach ($months as $value => $name)
                                <option value="{{ $value }}" {{ $selectedMonth == $value ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- <div class="filter-item">
                        <select name="department" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            @if(isset($departments))
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ request('department') == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <div class="filter-item search-filter">
                        <div class="search-wrapper">
                            <i class="feather-search"></i>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search employee by name or ID..." 
                                   value="{{ request('search') }}"
                                   onkeyup="if(event.keyCode==13) this.form.submit();">
                        </div>
                    </div> --}}

                    <div class="filter-item" style="min-width: auto; display: flex; gap: 8px;">
                        {{-- <button type="submit" class="btn-sm-custom">
                            <i class="feather-search"></i> Filter
                        </button> --}}
                        <a href="{{ route('team.attendance-summary') }}" class="reset-button">
                            <i class="feather-refresh-cw"></i> Reset
                        </a>
                    </div>

                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('team.attendance-summary.export') }}?month={{ $selectedMonth }}&search={{ request('search') }}&department={{ request('department') }}"
                            class="btn-sm-custom-outline" target="_blank">
                            <i class="feather-download"></i>
                            Export CSV
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if(request()->hasAny(['month', 'search', 'department']))
                <div class="active-filters" style="margin-top: 10px; padding-top: 10px; border-top: 1.5px dashed #e2e8f0; display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
                    <span class="active-filters-label" style="font-size: 9.5px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; background: #f1f5f9; padding: 2px 7px; border-radius: 20px;">Active:</span>

                    @if(request('month') && request('month') != now()->format('Y-m'))
                        <span class="filter-tag" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 30px; padding: 3px 8px 3px 7px; font-size: 10px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; color: #334155;">
                            <i class="feather-calendar" style="color: #1e3a8a; font-size: 10px;"></i>
                            {{ \Carbon\Carbon::createFromFormat('Y-m', request('month'))->format('F Y') }}
                            <a href="{{ route('team.attendance-summary', array_merge(request()->except(['month', 'page']))) }}"
                               class="remove-tag" style="color: #94a3b8; margin-left: 2px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; padding: 2px; border-radius: 50%; line-height: 1; text-decoration: none;">
                                <i class="feather-x" style="font-size: 10px;"></i>
                            </a>
                        </span>
                    @endif

                    @if(request('department'))
                        @php
                            $deptName = isset($departments) ? $departments->firstWhere('id', (int)request('department'))?->name : '';
                        @endphp
                        @if($deptName)
                            <span class="filter-tag" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 30px; padding: 3px 8px 3px 7px; font-size: 10px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; color: #334155;">
                                <i class="feather-grid" style="color: #1e3a8a; font-size: 10px;"></i>
                                {{ $deptName }}
                                <a href="{{ route('team.attendance-summary', array_merge(request()->except(['department', 'page']))) }}"
                                   class="remove-tag" style="color: #94a3b8; margin-left: 2px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; padding: 2px; border-radius: 50%; line-height: 1; text-decoration: none;">
                                    <i class="feather-x" style="font-size: 10px;"></i>
                                </a>
                            </span>
                        @endif
                    @endif

                    @if(request('search'))
                        <span class="filter-tag" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 30px; padding: 3px 8px 3px 7px; font-size: 10px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; color: #334155;">
                            <i class="feather-search" style="color: #1e3a8a; font-size: 10px;"></i>
                            "{{ request('search') }}"
                            <a href="{{ route('team.attendance-summary', array_merge(request()->except(['search', 'page']))) }}"
                               class="remove-tag" style="color: #94a3b8; margin-left: 2px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; padding: 2px; border-radius: 50%; line-height: 1; text-decoration: none;">
                                <i class="feather-x" style="font-size: 10px;"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('team.attendance-summary') }}" class="filter-tag clear-all" style="background: #e3edfe; border-color: #1e3a8a; color: #1e3a8a; font-weight: 600; text-decoration: none; padding: 3px 10px; border-radius: 30px; font-size: 10px; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="feather-refresh-cw" style="color: currentColor; font-size: 10px;"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- Summary Table -->
        <div class="table-wrapper table-responsive">
            <table class="table" id="summaryTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="min-width: 200px;">Employee</th>
                        <th style="min-width: 120px;">Designation</th>
                        <th class="text-center" style="width: 70px;">Present</th>
                        <th class="text-center" style="width: 70px;">Absent</th>
                        <th class="text-center" style="width: 70px;">Leaves</th>
                        <th class="text-center" style="width: 70px;">Holidays</th>
                        <th class="text-center" style="width: 70px;">Week Offs</th>
                        <th class="text-center" style="width: 90px;">Working Days</th>
                        <th class="text-center" style="width: 90px;">Month Days</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summaryData as $index => $row)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <a href="{{ route('team.member-detail', ['id' => encrypt($row['user_id'])]) }}" style="text-decoration: none; color: inherit;">
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            {{ strtoupper(substr($row['name'], 0, 2)) }}
                                        </div>
                                        <div class="employee-details">
                                            <div class="employee-name">{{ $row['name'] }}
                                                <small>({{ $row['employee_id'] }})</small>
                                            </div>
                                            <div class="employee-email">{{ $row['email'] }}</div>
                                        </div>
                                    </div>
                                </a>
                            </td>
                            <td>{{ $row['designation'] ?? '--' }}</td>
                            <td class="text-center">
                                <span class="badge badge-success">{{ $row['present'] }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-danger">{{ $row['absent'] }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-warning">{{ $row['leaves'] }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-purple">{{ $row['holidays'] }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-secondary">{{ $row['weekoffs'] }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-dark">{{ $row['working_days'] }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-dark">{{ $row['total_month_days'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="feather-file-text"></i>
                                    <h4>No Data Found</h4>
                                    <p>No attendance records available for the selected filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(!empty($summaryData) && isset($totals))
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Total</strong></td>
                            <td class="text-center"><strong>{{ $totals['present'] ?? 0 }}</strong></td>
                            <td class="text-center"><strong>{{ $totals['absent'] ?? 0 }}</strong></td>
                            <td class="text-center"><strong>{{ $totals['leaves'] ?? 0 }}</strong></td>
                            <td class="text-center"><strong>{{ $totals['holidays'] ?? 0 }}</strong></td>
                            <td class="text-center"><strong>{{ $totals['weekoffs'] ?? 0 }}</strong></td>
                            <td class="text-center"><strong>{{ $totals['working_days'] ?? 0 }}</strong></td>
                            <td class="text-center"><strong>{{ $totals['month_days'] ?? 0 }}</strong></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000",
            "extendedTimeOut": "1000",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        // Auto-submit on month change
        document.querySelector('select[name="month"]')?.addEventListener('change', function() {
            this.closest('form').submit();
        });

        // Auto-submit on department change
        document.querySelector('select[name="department"]')?.addEventListener('change', function() {
            this.closest('form').submit();
        });

        // Search with Enter key
        document.querySelector('input[name="search"]')?.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });

        // Debounced search
        let searchTimeout;
        document.querySelector('input[name="search"]')?.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.closest('form').submit();
            }, 500);
        });

        // Export to CSV
        function exportToCSV() {
            let table = document.getElementById('summaryTable');
            if (!table) {
                toastr.error('Table not found');
                return;
            }

            let rows = table.querySelectorAll('tr');
            let csv = [];

            // Add headers
            let headers = [];
            let headerCells = rows[0].querySelectorAll('th');
            headerCells.forEach(cell => {
                let headerText = cell.innerText.trim();
                if (headerText) {
                    headers.push('"' + headerText.replace(/"/g, '""') + '"');
                }
            });
            csv.push(headers.join(','));

            // Add data rows
            let totalRows = rows.length;
            for (let i = 1; i < totalRows; i++) {
                let rowData = [];
                let cols = rows[i].querySelectorAll('td');

                if (cols.length === 0) continue;

                // Skip footer row
                let isFooter = cols.length > 0 && cols[0]?.innerText.trim().toLowerCase() === 'total';
                if (isFooter) continue;

                cols.forEach(col => {
                    let text = col.innerText.trim();
                    text = text.replace(/\s+/g, ' ');
                    text = text.replace(/"/g, '""');
                    rowData.push('"' + text + '"');
                });

                if (rowData.length > 0) {
                    csv.push(rowData.join(','));
                }
            }

            // Add footer row if exists
            let footerRow = rows[totalRows - 1];
            if (footerRow && footerRow.querySelectorAll('td').length > 0) {
                let footerData = [];
                let footerCols = footerRow.querySelectorAll('td');
                footerCols.forEach(col => {
                    let text = col.innerText.trim();
                    text = text.replace(/"/g, '""');
                    footerData.push('"' + text + '"');
                });
                if (footerData.length > 0) {
                    csv.push(footerData.join(','));
                }
            }

            // Download CSV
            let csvFile = new Blob(['\uFEFF' + csv.join('\n')], {
                type: 'text/csv;charset=utf-8'
            });
            let downloadLink = document.createElement('a');
            let month = '{{ $selectedMonth }}';
            downloadLink.download = 'attendance_summary_' + month + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);

            toastr.success('CSV exported successfully');
        }

        // Print report
        function printReport() {
            window.print();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printReport();
            }
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportToCSV();
            }
        });
    </script>
@endsection