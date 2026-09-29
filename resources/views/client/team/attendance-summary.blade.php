@extends('client.layout.master')

@section('title', 'Attendance Summary Report')

@section('style')
<style>
  

    /* ==================== FILTER SECTION ==================== */
    .filter-wrapper {
        background: white;
        border-radius: 12px;
        border: 1px solid #edf2f7;
        padding: 16px 20px;
        margin-bottom: 24px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }

    .filter-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .filter-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
    }

    .filter-title i {
        color: #4f46e5;
        font-size: 16px;
    }

    .month-selector {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .month-select {
        width: 200px;
        height: 40px;
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        background: #f8fafc;
        cursor: pointer;
    }

    .month-select:focus {
        border-color: #4f46e5;
        outline: none;
    }

    .export-btn {
        height: 40px;
        padding: 0 20px;
        background: #10b981;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .export-btn:hover {
        background: #059669;
        color:white;
    }

    .export-btn i {
        font-size: 16px;
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
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #475569;
        border-bottom-width: 1px;
        padding: 14px 16px;
        white-space: nowrap;
    }

    .table td {
        vertical-align: middle;
        font-size: 13px;
        padding: 12px 16px;
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
        padding: 14px 16px;
        border-top: 2px solid #e2e8f0;
    }

    /* Employee Info */
    .employee-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .employee-avatar {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: #eef2ff;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #4f46e5;
        font-weight: 600;
        font-size: 14px;
        text-transform: uppercase;
    }

    .employee-details {
        line-height: 1.3;
    }

    .employee-name {
        font-weight: 600;
        color: #1e293b;
        font-size: 13px;
    }

    .employee-email {
        font-size: 11px;
        color: #64748b;
    }

    /* Badges */
    .badge {
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
    }

    .badge-success {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-warning {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-info {
        background: #dbeafe;
        color: #1e40af;
    }

    .badge-purple {
        background: #ede9fe;
        color: #5b21b6;
    }

    .badge-secondary {
        background: #f1f5f9;
        color: #475569;
    }

    /* Progress Bar */
    .attendance-progress {
        width: 100px;
        height: 6px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        margin-top: 4px;
    }

    .progress-bar {
        height: 100%;
        background: #10b981;
        border-radius: 10px;
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
        
        .month-selector {
            width: 100%;
            flex-direction: column;
        }
        
        .month-select {
            width: 100%;
        }
        
        .export-btn {
            width: 100%;
            justify-content: center;
        }
        
        .table {
            min-width: 1200px;
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
            <li class="breadcrumb-item"><a href="{{ route('team.index') }}">Team</a></li>
            <li class="breadcrumb-item active">Attendance Summary</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-light" onclick="window.location.reload()">
                    <i class="feather-refresh-cw me-2"></i>Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<div class="main-content" style="padding: 20px !important;">
  

    <!-- Filter Section -->
    <div class="filter-wrapper">
        <div class="filter-header">
            <div class="filter-title">
                <i class="feather-filter"></i>
                Filter Summary Report
            </div>
            <div class="month-selector">
                <form action="{{ route('team.attendance-summary') }}" method="GET" id="monthForm" class="d-flex gap-2">
                    <select name="month" class="month-select" onchange="this.form.submit()">
                        @foreach($months as $value => $name)
                            <option value="{{ $value }}" {{ $selectedMonth == $value ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                    @if ($branchesEnabled ?? false)
                        <select name="branch_id" class="month-select" onchange="this.form.submit()" aria-label="Branch">
                            <option value="">All Branches</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" {{ (int) ($branchId ?? 0) === (int) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </form>
                <a href="{{ route('team.attendance-summary.export', array_filter(['month' => $selectedMonth, 'branch_id' => $branchId ?? null])) }}" class="export-btn">
                    <i class="feather-download"></i>
                    Export CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Table -->
    <div class="table-wrapper table-responsive">
        <table class="table" id="summaryTable">
            <thead>
                <tr>
                    <th>Sr. No.</th>
                    <th>Employee</th>
                    {{-- <th>Department</th> --}}
                    <th>Designation</th>
                    @if ($branchesEnabled ?? false)<th>Branch</th>@endif
                    <th class="text-center">Present</th>
                    <th class="text-center">Absent</th>
                    <th class="text-center">Leaves</th>
                    <th class="text-center">Holidays</th>
                    <th class="text-center">Week Offs</th>
                    <th class="text-center">Working Days</th>
                    <th class="text-center">Month Days</th>
                    {{-- <th class="text-center">Attendance %</th> --}}
                </tr>
            </thead>
            <tbody>
                @forelse($summaryData as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <a href="{{ route('team.member-detail', ['id' => encrypt($row['user_id'])]) }}">
                                <div class="employee-info">
                                        <div class="employee-avatar">
                                            {{ strtoupper(substr($row['name'], 0, 2)) }}
                                        </div>
                                        <div class="employee-details">
                                            <div class="employee-name">{{ $row['name'] }} <small>({{ $row['employee_id'] }})</small></div>
                                            <div class="employee-email">{{ $row['email'] }}</div>
                                        </div>
                                </div>
                            </a>
                        </td>
                        {{-- <td>{{ $row['department'] }}</td> --}}
                        <td>{{ $row['designation'] }}</td>
                        @if ($branchesEnabled ?? false)<td>{{ $row['branch'] ?? '—' }}</td>@endif
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
                        <td class="text-center">{{ $row['working_days'] }}</td>
                        <td class="text-center">{{ $row['total_month_days'] }}</td>
                        {{-- <td class="text-center">
                            <div class="d-flex align-items-center gap-2">
                                <span>{{ $row['attendance_percentage'] }}%</span>
                                <div class="attendance-progress">
                                    <div class="progress-bar" style="width: {{ $row['attendance_percentage'] }}%"></div>
                                </div>
                            </div>
                        </td> --}}
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="text-center py-5">
                            <div class="empty-state">
                                <i class="feather-file-text" style="font-size: 48px;"></i>
                                <h4 class="mt-3">No Data Found</h4>
                                <p class="text-muted">No attendance records available for the selected month.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
           
        </table>
    </div>
</div>
@endsection

@section('script-area')
<script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });

    // Export to CSV (alternative method)
    function exportToCSV() {
        let table = document.getElementById('summaryTable');
        let rows = table.querySelectorAll('tr');
        let csv = [];
        
        // Get headers
        let headers = [];
        let headerCells = rows[0].querySelectorAll('th');
        headerCells.forEach(cell => {
            headers.push('"' + cell.innerText.replace(/"/g, '""') + '"');
        });
        csv.push(headers.join(','));
        
        // Get data rows
        for (let i = 1; i < rows.length - 1; i++) { // Skip footer row
            let rowData = [];
            let cols = rows[i].querySelectorAll('td');
            
            cols.forEach(col => {
                // Clean the data (remove badges, progress bars etc.)
                let text = col.innerText.trim();
                // If it contains percentage, extract just the number
                if (text.includes('%')) {
                    text = text.split('%')[0].trim();
                }
                rowData.push('"' + text.replace(/"/g, '""') + '"');
            });
            
            csv.push(rowData.join(','));
        }
        
        // Add footer row if exists
        if (rows.length > 2) {
            let footerRow = [];
            let footerCells = rows[rows.length - 1].querySelectorAll('td');
            footerCells.forEach(cell => {
                let text = cell.innerText.trim();
                if (text.includes('%')) {
                    text = text.split('%')[0].trim();
                }
                footerRow.push('"' + text.replace(/"/g, '""') + '"');
            });
            csv.push(footerRow.join(','));
        }
        
        // Download CSV
        let csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
        let downloadLink = document.createElement('a');
        downloadLink.download = 'attendance_summary_{{ $selectedMonth }}.csv';
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.click();
        
        toastr.success('CSV exported successfully');
    }

    // Print report
    function printReport() {
        window.print();
    }
</script>
@endsection