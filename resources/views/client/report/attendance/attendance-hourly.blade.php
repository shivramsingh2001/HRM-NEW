@extends('client.layout.master')

@section('style')
    <style>
        /* Hourly Report Styles */
        .hourly-report-container {
            background: white;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .hourly-header {
            padding: 14px 17px;
            border-bottom: 1px solid #eef2f6;
            background: #fafbfc;
        }

        .hourly-table-wrapper {
            overflow-x: auto;
            padding: 0;
            max-height: 600px;
            overflow-y: auto;
            position: relative;
        }

        .hourly-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            min-width: 800px;
        }

        .hourly-table th {
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

        /* Sticky Header Columns - First 4 columns (S.No, Emp Code, Name, Designation) */
        .hourly-table th:first-child,
        .hourly-table td:first-child {
            position: sticky;
            left: 0;
            z-index: 15;
            background: white;
            min-width: 40px;
            max-width: 40px;
        }

        .hourly-table th:nth-child(2),
        .hourly-table td:nth-child(2) {
            position: sticky;
            left: 40px;
            z-index: 15;
            background: white;
            min-width: 80px;
            max-width: 80px;
        }

        .hourly-table th:nth-child(3),
        .hourly-table td:nth-child(3) {
            position: sticky;
            left: 120px;
            z-index: 15;
            background: white;
            min-width: 150px;
            max-width: 150px;
            text-align: left !important;
        }

        .hourly-table th:nth-child(4),
        .hourly-table td:nth-child(4) {
            position: sticky;
            left: 270px;
            z-index: 15;
            background: white;
            min-width: 120px;
            max-width: 120px;
            text-align: left !important;
        }

        /* Department column - sticky as 5th column */
        .hourly-table th:nth-child(5),
        .hourly-table td:nth-child(5) {
            position: sticky;
            left: 390px;
            z-index: 15;
            background: white;
            min-width: 120px;
            max-width: 120px;
            text-align: left !important;
        }

        /* Header background for sticky columns */
        .hourly-table th:first-child,
        .hourly-table th:nth-child(2),
        .hourly-table th:nth-child(3),
        .hourly-table th:nth-child(4),
        .hourly-table th:nth-child(5) {
            background: #f1f5f9;
            z-index: 25;
        }

        /* Row hover effect for sticky columns */
        .hourly-table tbody tr:hover td:first-child,
        .hourly-table tbody tr:hover td:nth-child(2),
        .hourly-table tbody tr:hover td:nth-child(3),
        .hourly-table tbody tr:hover td:nth-child(4),
        .hourly-table tbody tr:hover td:nth-child(5) {
            background-color: #f1f5f9 !important;
        }

        .hourly-table td {
            padding: 4px 6px;
            border: 1px solid #eef2f6;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
            font-size: 10px;
        }

        .hourly-table tbody tr:hover td:not(:first-child):not(:nth-child(2)):not(:nth-child(3)):not(:nth-child(4)):not(:nth-child(5)) {
            background-color: #f8fafc;
        }

        /* Cell styling based on values */
        .cell-present {
            color: #0D6EFD;
            font-weight: 600;
        }

        .cell-absent {
            color: #475569;
            font-weight: 600;
        }

        .cell-leave {
            color: #0D6EFD;
            font-weight: 600;
        }

        .cell-weekoff {
            color: #0D6EFD;
            font-weight: 600;
        }

        .cell-holiday {
            color: #3b82f6;
            font-weight: 600;
        }

        .cell-checkedin {
            color: #0D6EFD;
            font-weight: 600;
        }

        .total-hours-cell {
            font-weight: 700;
            color: #0D6EFD;
            background: #EFF6FF !important;
            min-width: 80px;
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        .stat-card.weekoff .stat-number {
            color: #0D6EFD;
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
        .legend-dot.checkedin {
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
            
            .hourly-table th:nth-child(3),
            .hourly-table td:nth-child(3) {
                min-width: 120px;
                max-width: 120px;
            }
            
            .hourly-table th:nth-child(4),
            .hourly-table td:nth-child(4) {
                min-width: 100px;
                max-width: 100px;
            }
            
            .hourly-table th:nth-child(5),
            .hourly-table td:nth-child(5) {
                min-width: 100px;
                max-width: 100px;
            }
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Attendance Hourly Report" :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')]]">
        <x-slot:actions>
            <div class="dropdown">
                <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10">
                    <i class="feather-download"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <a href="{{ route('report.attendance.hourly.export', request()->query()) }}" class="dropdown-item"
                        target="_blank">
                        <i class="bi bi-filetype-csv me-3"></i>
                        <span>Export CSV</span>
                    </a>
                    <a href="#" class="dropdown-item" onclick="exportToExcel()">
                        <i class="bi bi-file-earmark-excel me-3"></i>
                        <span>Export Excel</span>
                    </a>
                </div>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">
        <!-- Filter Section -->
        <x-ui.filter-card title="Filter Report">
            <form action="{{ route('report.attendance.hourly.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <input type="month" name="month" class="filter-input" 
                               value="{{ request('month', now()->format('Y-m')) }}" 
                               onchange="this.form.submit()">
                    </div>

                    <div class="filter-item">
                        <select name="department" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
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
                            @foreach($branches ?? [] as $b)
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
                        <a href="{{ route('report.attendance.hourly.index') }}" class="reset-btn" title="Reset filters" aria-label="Reset filters">
                            <i class="feather-refresh-cw"></i></a>
                    </div>
                </div>
            </form>
        </x-ui.filter-card>

        <!-- Hourly Table -->
        <div class="hourly-report-container">
            <div class="hourly-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">
                        <i class="feather-calendar me-2" style="color: var(--icon-color, #0D6EFD);"></i>
                        Attendance Report - {{ $selectedDate->format('F Y') }}
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
                        <span class="legend-dot weekoff"></span> Week Off
                    </span>
                    <span class="legend-item">
                        <span class="legend-dot holiday"></span> Holiday
                    </span>
                    <span class="legend-item">
                        <span class="legend-dot checkedin"></span> Checked In
                    </span>
                </div>
            </div>

            <div class="hourly-table-wrapper">
                <table class="hourly-table" id="hourlyTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Emp Code</th>
                            <th>Name</th>
                            <th>Designation</th>
                            <th>Department</th>
                            @feature('branches')<th>Branch</th>@endfeature
                            @foreach ($dayNames as $day => $name)
                                <th class="{{ in_array($name, ['Sat', 'Sun']) ? 'weekend-header' : '' }}">
                                    <span>{{ $name }}</span>
                                    <br><small>{{ $day }}</small>
                                </th>
                            @endforeach
                            <th>Total Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData as $index => $row)
                            <tr>
                                <td>{{ ($paginator->currentPage() - 1) * $paginator->perPage() + $loop->iteration }}</td>
                                <td>{{ $row['employee_id'] }}</td>
                                <td style="text-align: left; font-weight: 600;">{{ $row['name'] }}</td>
                                <td style="text-align: left;">{{ $row['designation'] }}</td>
                                <td style="text-align: left;">{{ $row['department'] }}</td>
                                @feature('branches')<td style="text-align: left;">{{ $row['branch'] ?? '—' }}</td>@endfeature
                                
                                @foreach ($row['days'] as $dayData)
                                    <td class="
                                        @if($dayData['value'] == 'WO') cell-weekoff
                                        @elseif($dayData['value'] == 'L') cell-leave
                                        @elseif($dayData['value'] == 'H') cell-holiday
                                        @elseif($dayData['value'] == 'C/I') cell-checkedin
                                        @elseif($dayData['value'] == '--' || $dayData['value'] == 'A' || $dayData['value'] == 'a') cell-absent
                                        @elseif(strpos($dayData['value'], ':') !== false) cell-present
                                        @endif
                                    ">
                                        {{ $dayData['value'] }}
                                    </td>
                                @endforeach
                                
                                <td class="total-hours-cell">{{ $row['total_hours'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $daysInMonth + 8 }}" class="text-center py-5">
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

            @if(method_exists($paginator, 'links') && $paginator->hasPages())
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