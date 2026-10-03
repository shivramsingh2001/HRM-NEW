@extends('client.layout.master')
@php
    use Carbon\Carbon;
@endphp

@section('style')
    <style>
        /* Overall Report Styles */
        .overall-report-container {
            background: white;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .overall-header {
            padding: 14px 17px;
            border-bottom: 1px solid #eef2f6;
            background: #fafbfc;
        }

        .overall-table-wrapper {
            overflow-x: auto;
            padding: 0;
            overflow-y: auto;
            position: relative;
        }

        .overall-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            min-width: 1200px;
        }

        .overall-table th {
            background: #f8fafc;
            font-weight: 600;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #475569;
            padding: 7px 6px;
            border: 1px solid #e2e8f0;
            text-align: center;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 20;
        }

        /* Sticky columns: Sr. No. + Employee */
        .overall-table th:first-child,
        .overall-table td:first-child {
            position: sticky;
            left: 0;
            z-index: 15;
            background: white;
            min-width: 44px;
            max-width: 44px;
        }

        .overall-table th:nth-child(2),
        .overall-table td:nth-child(2) {
            position: sticky;
            left: 44px;
            z-index: 15;
            background: white;
            min-width: 210px;
            text-align: left !important;
        }

        .overall-table th:nth-child(3),
        .overall-table td:nth-child(3),
        .overall-table th:nth-child(4),
        .overall-table td:nth-child(4) {
            text-align: left !important;
        }

        /* Header background for sticky columns */
        .overall-table th:first-child,
        .overall-table th:nth-child(2) {
            background: #f1f5f9;
            z-index: 25;
        }

        .overall-table td {
            padding: 4px 6px;
            border: 1px solid #eef2f6;
            /* text-align: center; */
            vertical-align: middle;
            white-space: nowrap;
            font-size: 10px;
        }

        .overall-table tbody tr:hover td {
            background-color: #f8fafc !important;
        }

        .overall-table tbody tr:hover td:first-child,
        .overall-table tbody tr:hover td:nth-child(2),
        .overall-table tbody tr:hover td:nth-child(3),
         {
            background-color: #f1f5f9 !important;
        }

        /* Cell styling */
        .cell-present {
            color: #0D6EFD;
            font-weight: 600;
            background: #EFF6FF !important;
        }

        .cell-absent {
            color: #475569;
            font-weight: 600;
            background: #e2e8f0 !important;
        }

        .cell-leave {
            color: #0D6EFD;
            font-weight: 600;
            background: #bfd3f7 !important;
        }

        .cell-weekoff {
            color: #0D6EFD;
            font-weight: 600;
            background: #EFF6FF !important;
        }

        .cell-holiday {
            color: #0D6EFD;
            font-weight: 600;
            background: #dbeafe !important;
        }

        .cell-halfday {
            color: #0D6EFD;
            font-weight: 600;
            background: #EFF6FF !important;
        }

        .summary-cell {
            font-weight: 700;
            color: #0D6EFD;
            background: #EFF6FF !important;
        }





        .filter-select,
        .filter-input {
            width: 100%;
            height: 40px;
            padding: 6px 10px;
            font-size: 10.5px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            transition: all 0.3s;
            color: #0f172a;
        }

        .filter-select:focus,
        .filter-input:focus {
            border-color: #0D6EFD;
            outline: none;
            background: white;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
        }

        .apply-btn {
            height: 40px;
            padding: 0 20px;
            background: #0D6EFD;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 10.5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .apply-btn:hover {
            background: #0B5ED7;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }



        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 17px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 11px 14px;
            border: 1px solid #eef2f6;
            text-align: center;
        }

        .stat-number {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
        }

        .stat-label {
            font-size: 10px;
            color: #64748b;
            font-weight: 500;
            margin-top: 3px;
        }

        .stat-card.present .stat-number {
            color: #0D6EFD;
        }

        .stat-card.absent .stat-number {
            color: #475569;
        }

        .stat-card.leave .stat-number {
            color: #0D6EFD;
        }

        .stat-card.halfday .stat-number {
            color: #0D6EFD;
        }

        .stat-card.weekoff .stat-number {
            color: #0D6EFD;
        }

        .stat-card.holiday .stat-number {
            color: #3b82f6;
        }

        .stat-card.total .stat-number {
            color: #0D6EFD;
        }

        /* Legend */
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            padding: 12px 0;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            color: #475569;
        }

        .legend-dot {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }

        .legend-dot.present {
            background: #EFF6FF;
        }

        .legend-dot.absent {
            background: #e2e8f0;
        }

        .legend-dot.leave {
            background: #bfd3f7;
        }

        .legend-dot.weekoff {
            background: #EFF6FF;
        }

        .legend-dot.holiday {
            background: #dbeafe;
        }

        .legend-dot.halfday {
            background: #EFF6FF;
        }

        .weekend-header {
            background: #f1f5f9 !important;
        }

        .weekend-header span {
            color: #94a3b8;
        }

        .empty-state {
            padding: 28px 14px;
            text-align: center;
        }

        .empty-state i {
            font-size: 32px;
            color: #cbd5e1;
        }








        @media (max-width: 768px) {



            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Overall Attendance Report" :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')]]">
        <x-slot:actions>
            <a href="{{ route('report.attendance.overall.export', request()->query()) }}" class="btn btn-sm btn-primary"
                target="_blank">
                <i class="feather-download me-1"></i> Export CSV
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">
        <!-- Filter Section -->
        <x-ui.filter-card title="Filter Report">
            <form action="{{ route('report.attendance.overall.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <input type="month" name="month" class="filter-input"
                            value="{{ request('month', now()->format('Y-m')) }}" onchange="this.form.submit()">
                    </div>

                    <div class="filter-item">
                        <select name="department" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}"
                                    {{ request('department') == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @feature('branches')
                    <div class="filter-item">
                        <select name="branch_id" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Branches</option>
                            @foreach ($branches ?? [] as $b)
                                <option value="{{ $b->id }}"
                                    {{ request('branch_id') == $b->id ? 'selected' : '' }}>
                                    {{ $b->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endfeature

                    <div class="filter-item search-filter">
                        <input type="text" name="search" class="filter-input"
                               placeholder="Search employee..."
                               value="{{ request('search') }}"
                               onkeyup="if(event.keyCode==13) this.form.submit();">
                    </div>


                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('report.attendance.overall.index') }}" class="reset-btn" title="Reset filters" aria-label="Reset filters">
                            <i class="feather-refresh-cw"></i></a>
                    </div>
                </div>
            </form>
        </x-ui.filter-card>

        <!-- Stats -->
        {{-- <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-number">{{ $stats['total_employees'] ?? 0 }}</div>
                <div class="stat-label">Total Employees</div>
            </div>
            <div class="stat-card present">
                <div class="stat-number">{{ $stats['total_present'] ?? 0 }}</div>
                <div class="stat-label">Total Present</div>
            </div>
            <div class="stat-card absent">
                <div class="stat-number">{{ $stats['total_absent'] ?? 0 }}</div>
                <div class="stat-label">Total Absent</div>
            </div>
            <div class="stat-card leave">
                <div class="stat-number">{{ $stats['total_leave'] ?? 0 }}</div>
                <div class="stat-label">Total Leave</div>
            </div>
            <div class="stat-card halfday">
                <div class="stat-number">{{ $stats['total_halfday'] ?? 0 }}</div>
                <div class="stat-label">Total Halfday</div>
            </div>
            <div class="stat-card holiday">
                <div class="stat-number">{{ $stats['total_holiday'] ?? 0 }}</div>
                <div class="stat-label">Total Holiday</div>
            </div>
            <div class="stat-card weekoff">
                <div class="stat-number">{{ $stats['total_weekoff'] ?? 0 }}</div>
                <div class="stat-label">Total WeekOff</div>
            </div>
        </div> --}}

        <!-- Overall Table -->
        <div class="overall-report-container">
            <div class="overall-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">
                        <i class="feather-calendar me-2" style="color: var(--icon-color, #0D6EFD);"></i>
                        Overall Attendance Report - {{ $selectedDate->format('F Y') }}
                    </h6>
                    <small class="text-muted">Total Days: {{ $daysInMonth }}</small>
                </div>
                <div class="legend">
                    <span class="legend-item">
                        <span class="legend-dot present"></span> Present
                    </span>
                    <span class="legend-item">
                        <span class="legend-dot absent"></span> Absent
                    </span>
                    <span class="legend-item">
                        <span class="legend-dot leave"></span> Leave
                    </span>
                    <span class="legend-item">
                        <span class="legend-dot weekoff"></span> WeekOff
                    </span>
                    <span class="legend-item">
                        <span class="legend-dot holiday"></span> Holiday
                    </span>
                    <span class="legend-item">
                        <span class="legend-dot halfday"></span> Halfday
                    </span>
                </div>
            </div>

            <div class="overall-table-wrapper">
                <table class="overall-table" id="overallTable">
                    <thead>
                        <tr>
                            <th>Sr. No.</th>
                            <th>Employee Name</th>
                            <th>Designation</th>
                            <th>Department</th>
                            @feature('branches')<th>Branch</th>@endfeature
                            @foreach ($dateLabels as $day => $date)
                                <th
                                    class="{{ in_array(Carbon::parse($date)->format('D'), ['Sat', 'Sun']) ? 'weekend-header' : '' }}">
                                    <span>{{ Carbon::parse($date)->format('D') }}</span>
                                    <br><small>{{ $day }}</small>
                                </th>
                            @endforeach
                            <th>Total Present</th>
                            <th>Total Absent</th>
                            <th>Total Leave</th>
                            <th>Total Halfday</th>
                            <th>Total Holiday</th>
                            <th>Total WeekOff</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $srStart = (is_object($paginator ?? null) && method_exists($paginator, 'firstItem')) ? ($paginator->firstItem() ?? 1) : 1; @endphp
                        @forelse($reportData as $index => $row)
                            <tr>
                                <td>{{ $srStart + $loop->index }}</td>
                                <td>
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            {{ strtoupper(substr($row['employee_name'] ?? 'N/A', 0, 2)) }}
                                        </div>
                                        <div class="employee-details">
                                            <div class="employee-name-text">{{ $row['employee_name'] ?? 'N/A' }}
                                                <small class="employee-id-text">( {{ $row['employee_id'] ?? 'N/A' }} )</small>
                                            </div>
                                            <div class="employee-email-text">
                                                {{$row['employee_email'] ?? 'N/A' }}</div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td style="text-align: left;">{{ $row['designation'] }}</td>
                                <td style="text-align: left;">{{ $row['department'] }}</td>
                                @feature('branches')<td style="text-align: left;">{{ $row['branch'] ?? '—' }}</td>@endfeature

                                @foreach ($row['days'] as $day => $status)
                                    <td
                                        class="
                                        @if ($status == 'P') cell-present
                                        @elseif($status == 'Absent') cell-absent
                                        @elseif($status == 'Leave') cell-leave
                                        @elseif($status == 'WeekOff') cell-weekoff
                                        @elseif($status == 'Holiday') cell-holiday
                                        @elseif($status == 'Halfday') cell-halfday @endif
                                    ">
                                        {{ $status }}
                                    </td>
                                @endforeach

                                <td class="summary-cell">{{ $row['total_present'] }}</td>
                                <td class="summary-cell">{{ $row['total_absent'] }}</td>
                                <td class="summary-cell">{{ $row['total_leave'] }}</td>
                                <td class="summary-cell">{{ $row['total_halfday'] }}</td>
                                <td class="summary-cell">{{ $row['total_holiday'] }}</td>
                                <td class="summary-cell">{{ $row['total_weekoff'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $daysInMonth + 12 }}" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="feather-calendar"></i>
                                        <h5 class="mt-3">No Data Found</h5>
                                        <p class="text-muted">No attendance records found for the selected month.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (method_exists($paginator, 'links') && $paginator->hasPages())
                <x-ui.pagination-footer :paginator="$paginator" label="entries" />
            @endif
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
        $('input[name="month"]').on('change', function() {
            $('#filterForm').submit();
        });

        // Auto-submit on department change
        $('select[name="department"]').on('change', function() {
            $('#filterForm').submit();
        });

        // Search with Enter key
        $('input[name="search"]').on('keyup', function(e) {
            if (e.key === 'Enter') {
                $('#filterForm').submit();
            }
        });

        // Debounced search
        let searchTimeout;
        $('input[name="search"]').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                $('#filterForm').submit();
            }, 500);
        });
    </script>
@endsection
