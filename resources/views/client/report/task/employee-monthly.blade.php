@extends('client.layout.master')

@section('title', 'Employee Monthly Task Report')

@section('style')
    <style>
        /* ==================== FILTER SECTION ==================== */
        .filter-wrapper {
            background: white;
            border-radius: 10px;
            border: 1px solid #edf2f7;
            padding: 12px 14px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #1e293b;
        }

        .filter-title i { color: var(--primary-mid); font-size: 14px; }

        .filter-title .badge-count {
            background: var(--primary-light);
            color: var(--primary-mid);
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .filter-item { flex: 0 0 auto; }
        .filter-item.grow { flex: 1; min-width: 150px; }

        /* Inputs */
        .filter-input,
        .filter-select {
            width: 100%;
            height: 34px;
            padding: 4px 10px;
            font-size: 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            transition: all 0.2s;
            color: #0f172a;
            font-weight: 500;
        }

        .filter-select {
            padding-right: 28px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 8px center;
            background-size: 12px;
            appearance: none;
            cursor: pointer;
        }

        .filter-input:hover,
        .filter-select:hover { background-color: white; border-color: #cbd5e1; }

        .filter-input:focus,
        .filter-select:focus {
            border-color: var(--primary-mid);
            outline: none;
            background-color: white;
            box-shadow: var(--shadow-focus);
        }

        /* Buttons */
        .btn-sm-custom-outline,
        .reset-button {
            height: 34px;
            padding: 0 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            text-decoration: none;
            background: white;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
        }

        .btn-sm-custom-outline:hover,
        .reset-button:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .btn-sm-custom-outline i,
        .reset-button i { font-size: 12px; }

        /* ==================== STATS CARDS ==================== */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 8px;
            margin-bottom: 14px;
        }

        .stat-mini {
            background: white;
            border: 1px solid #edf2f7;
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }

        .stat-mini:hover {
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.04);
            border-color: #d1d5db;
        }

        .stat-mini-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }

        .stat-mini-icon.primary  { background: var(--primary-light); color: var(--primary-mid); }
        .stat-mini-icon.warning  { background: #fef3c7; color: #f59e0b; }
        .stat-mini-icon.info     { background: #dbeafe; color: #3b82f6; }
        .stat-mini-icon.secondary{ background: #f1f5f9; color: #475569; }
        .stat-mini-icon.success  { background: #d1fae5; color: #10b981; }
        .stat-mini-icon.purple   { background: #ede9fe; color: #8b5cf6; }
        .stat-mini-icon.danger   { background: #fee2e2; color: #ef4444; }
        .stat-mini-icon.dark     { background: #e2e8f0; color: #0f172a; }

        .stat-mini-info { line-height: 1.1; }
        .stat-mini-value {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .stat-mini-label {
            font-size: 10px;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ==================== TABLE ==================== */
        .table-wrapper {
            background: white;
            border-radius: 10px;
            border: 1px solid #edf2f7;
            overflow: hidden;
        }

        .table { margin-bottom: 0; }

        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #475569;
            border-bottom-width: 1px;
            padding: 10px 12px;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            font-size: 12px;
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .table tbody tr:hover { background-color: #f8fafc; }

        .table tfoot {
            background-color: #FFFFFF;
            font-weight: 600;
        }

        .table tfoot td {
            padding: 10px 12px;
            border-top: 2px solid #e2e8f0;
            font-size: 12px;
        }

        /* Employee Info */
        .employee-info {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: inherit;
        }

        .employee-avatar {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-mid);
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            flex-shrink: 0;
            overflow: hidden;
        }

        .employee-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .employee-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 12px;
            line-height: 1.2;
        }

        .employee-name small {
            font-weight: 400;
            color: #94a3b8;
            font-size: 10px;
        }

        .employee-email {
            font-size: 10px;
            color: #64748b;
            line-height: 1.2;
        }

        /* Badges */
        .badge {
            padding: 3px 8px;
            border-radius: 14px;
            font-size: 10px;
            font-weight: 700;
            display: inline-block;
            min-width: 26px;
            text-align: center;
            line-height: 1.4;
        }

        .badge-success   { background: #d1fae5; color: #065f46; }
        .badge-primary   { background: #dbeafe; color: #336ef3; }
        .badge-warning   { background: #fef3c7; color: #92400e; }
        .badge-danger    { background: #fee2e2; color: #991b1b; }
        .badge-info      { background: #dbeafe; color: #1e40af; }
        .badge-purple    { background: #ede9fe; color: #5b21b6; }
        .badge-secondary { background: #f1f5f9; color: #475569; }
        .badge-dark      { background: #e2e8f0; color: #0f172a; }

        /* Empty State */
        .empty-state { padding: 50px 20px; text-align: center; }
        .empty-state i { font-size: 42px; color: #cbd5e1; }
        .empty-state h4 { color: #0f172a; font-size: 16px; margin-top: 14px; }
        .empty-state p  { color: #94a3b8; font-size: 13px; }

        /* ==================== PAGINATION ==================== */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
            padding: 10px 4px;
        }

        .pagination-info {
            font-size: 12px;
            color: #64748b;
        }

        .pagination-info strong { color: #0f172a; }

        .pagination-wrapper nav {
            margin-left: auto;
        }

        .pagination-wrapper .pagination {
            margin: 0;
            gap: 4px;
        }

        .pagination-wrapper .page-link {
            border-radius: 6px !important;
            font-size: 12px;
            padding: 5px 10px;
            color: #475569;
            border: 1px solid #e2e8f0;
            min-width: 32px;
            text-align: center;
            transition: all 0.2s;
            line-height: 1.4;
        }

        .pagination-wrapper .page-link:hover {
            background: var(--primary-light);
            color: var(--primary-mid);
            border-color: #c7d2fe;
        }

        .pagination-wrapper .page-item.active .page-link {
            background: var(--primary-mid);
            border-color: var(--primary-mid);
            color: white;
            font-weight: 600;
        }

        .pagination-wrapper .page-item.disabled .page-link {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-strip { grid-template-columns: repeat(2, 1fr); }
            .filter-row  { flex-direction: column; width: 100%; }
            .filter-item { width: 100%; }
            .btn-sm-custom-outline, .reset-button {
                width: 100%;
                justify-content: center;
            }
            .table { min-width: 1000px; }
            .pagination-wrapper { justify-content: center; }
            .pagination-wrapper nav { margin-left: 0; }
        }

        @media print {
            .filter-wrapper { display: none; }
            .pagination-wrapper { display: none; }
            .table th {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .badge, .employee-avatar {
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
                <h5 class="m-b-10">Employee Monthly Task Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('report.attendance.index') }}">Reports</a></li>
                <li class="breadcrumb-item active">Employee Monthly Task</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <span class="badge badge-info-custom">
                    <i class="feather-calendar me-1"></i>
                    {{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}
                </span>
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 18px !important;">

        {{-- ==================== FILTERS ==================== --}}
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Report
                    @php
                        $activeFilters = 0;
                        if(request('date_from')) $activeFilters++;
                        if(request('date_to')) $activeFilters++;
                        if(request('user_id') && request('user_id') != 'all') $activeFilters++;
                        if(request('project_id') && request('project_id') != 'all') $activeFilters++;
                        if(request('branch_id') && request('branch_id') != 'all') $activeFilters++;
                        if(request('search')) $activeFilters++;
                    @endphp
                    @if($activeFilters > 0)
                        <span class="badge-count">{{ $activeFilters }} active</span>
                    @endif
                </div>
            </div>

            <form action="{{ route('report.task.employee-monthly') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item grow">
                        <input type="date" name="date_from" class="filter-input auto-submit"
                               value="{{ request('date_from', $from->format('Y-m-d')) }}"
                               placeholder="From Date">
                    </div>

                    <div class="filter-item grow">
                        <input type="date" name="date_to" class="filter-input auto-submit"
                               value="{{ request('date_to', $to->format('Y-m-d')) }}"
                               placeholder="To Date">
                    </div>

                    <div class="filter-item grow">
                        <select name="user_id" class="filter-select auto-submit">
                            <option value="all">All Employees</option>
                            @foreach($allUsers as $u)
                                <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                                    {{ $u->name }} ({{ $u->employee_id }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-item grow">
                        <select name="project_id" class="filter-select auto-submit">
                            <option value="all">All Projects</option>
                            @foreach($projectList as $p)
                                <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-item grow">
                        <select name="branch_id" class="filter-select auto-submit">
                            <option value="all">All Branches</option>
                            @foreach($branchList as $b)
                                <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>
                                    {{ $b->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-item grow">
                        <input type="text" name="search" class="filter-input" placeholder="Search employee, ID or email…"
                               value="{{ request('search') }}">
                    </div>

                    <div class="filter-item">
                        <button type="submit" class="btn-sm-custom-outline"><i class="feather-search"></i> Search</button>
                    </div>

                    <div class="filter-item">
                        <a href="{{ route('report.task.employee-monthly') }}" class="reset-button">
                            <i class="feather-refresh-cw"></i> Reset
                        </a>
                    </div>

                    <div class="filter-item">
                        <a href="{{ route('report.task.employee-monthly.export', request()->query()) }}"
                           class="btn-sm-custom-outline" target="_blank">
                            <i class="feather-download"></i> Export CSV
                        </a>
                    </div>
                </div>
            </form>
        </div>

        {{-- ==================== STATS STRIP ==================== --}}
        <div class="stats-strip">
            <div class="stat-mini">
                <div class="stat-mini-icon primary"><i class="feather-list"></i></div>
                <div class="stat-mini-info">
                    <div class="stat-mini-value">{{ $grandTotal['total'] }}</div>
                    <div class="stat-mini-label">Total</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon warning"><i class="feather-clock"></i></div>
                <div class="stat-mini-info">
                    <div class="stat-mini-value">{{ $grandTotal['pending'] }}</div>
                    <div class="stat-mini-label">Pending</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon info"><i class="feather-loader"></i></div>
                <div class="stat-mini-info">
                    <div class="stat-mini-value">{{ $grandTotal['in_progress'] }}</div>
                    <div class="stat-mini-label">In Progress</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon secondary"><i class="feather-pause-circle"></i></div>
                <div class="stat-mini-info">
                    <div class="stat-mini-value">{{ $grandTotal['hold'] }}</div>
                    <div class="stat-mini-label">Hold</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon success"><i class="feather-check-circle"></i></div>
                <div class="stat-mini-info">
                    <div class="stat-mini-value">{{ $grandTotal['completed'] }}</div>
                    <div class="stat-mini-label">Completed</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon purple"><i class="feather-award"></i></div>
                <div class="stat-mini-info">
                    <div class="stat-mini-value">{{ $grandTotal['approved'] }}</div>
                    <div class="stat-mini-label">Approved</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon danger"><i class="feather-x-circle"></i></div>
                <div class="stat-mini-info">
                    <div class="stat-mini-value">{{ $grandTotal['rejected'] }}</div>
                    <div class="stat-mini-label">Rejected</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon dark"><i class="feather-slash"></i></div>
                <div class="stat-mini-info">
                    <div class="stat-mini-value">{{ $grandTotal['cancelled'] }}</div>
                    <div class="stat-mini-label">Cancelled</div>
                </div>
            </div>
        </div>

        {{-- ==================== TABLE ==================== --}}
        <div class="table-wrapper table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 44px;">#</th>
                        <th style="min-width: 200px;">Employee</th>
                        <th style="min-width: 110px;">Branch</th>
                        <th class="text-center" style="width: 60px;">Total</th>
                        <th class="text-center" style="width: 70px;">Pending</th>
                        <th class="text-center" style="width: 85px;">In Progress</th>
                        <th class="text-center" style="width: 60px;">Hold</th>
                        <th class="text-center" style="width: 80px;">Completed</th>
                        <th class="text-center" style="width: 80px;">Approved</th>
                        <th class="text-center" style="width: 80px;">Rejected</th>
                        <th class="text-center" style="width: 80px;">Cancelled</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $index => $u)
                        <tr>
                            <td>{{ $users->firstItem() + $index }}</td>
                            <td>
                                <div class="employee-info">
                                    <div class="employee-avatar">
                                        @if(!empty($u->profile_image))
                                            <img src="{{ asset($u->profile_image) }}" alt="{{ $u->name }}">
                                        @else
                                            {{ strtoupper(substr($u->name, 0, 2)) }}
                                        @endif
                                    </div>
                                    <div>
                                        <div class="employee-name">
                                            {{ $u->name }}
                                            <small>({{ $u->employee_id }})</small>
                                        </div>
                                        <div class="employee-email">{{ $u->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $u->branch_name ?? '—' }}</td>
                            <td class="text-center"><span class="badge badge-primary">{{ $u->stat_total }}</span></td>
                            <td class="text-center"><span class="badge badge-warning">{{ $u->stat_pending }}</span></td>
                            <td class="text-center"><span class="badge badge-info">{{ $u->stat_in_progress }}</span></td>
                            <td class="text-center"><span class="badge badge-secondary">{{ $u->stat_hold }}</span></td>
                            <td class="text-center"><span class="badge badge-success">{{ $u->stat_completed }}</span></td>
                            <td class="text-center"><span class="badge badge-purple">{{ $u->stat_approved }}</span></td>
                            <td class="text-center"><span class="badge badge-danger">{{ $u->stat_rejected }}</span></td>
                            <td class="text-center"><span class="badge badge-dark">{{ $u->stat_cancelled }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="feather-file-text"></i>
                                    <h4>No Data Found</h4>
                                    <p>No task records available for the selected filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($users->count() > 0)
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Grand Total (All)</strong></td>
                            <td class="text-center"><strong>{{ $grandTotal['total'] }}</strong></td>
                            <td class="text-center"><strong>{{ $grandTotal['pending'] }}</strong></td>
                            <td class="text-center"><strong>{{ $grandTotal['in_progress'] }}</strong></td>
                            <td class="text-center"><strong>{{ $grandTotal['hold'] }}</strong></td>
                            <td class="text-center"><strong>{{ $grandTotal['completed'] }}</strong></td>
                            <td class="text-center"><strong>{{ $grandTotal['approved'] }}</strong></td>
                            <td class="text-center"><strong>{{ $grandTotal['rejected'] }}</strong></td>
                            <td class="text-center"><strong>{{ $grandTotal['cancelled'] }}</strong></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        {{-- ==================== PAGINATION ==================== --}}
        @if($users->hasPages() || $users->total() > 0)
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Showing <strong>{{ $users->firstItem() ?? 0 }}</strong>
                    to <strong>{{ $users->lastItem() ?? 0 }}</strong>
                    of <strong>{{ $users->total() }}</strong> employees
                </div>
                <nav>
                    {{ $users->links('pagination::bootstrap-4') }}
                </nav>
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
            "timeOut": "3000"
        };

        // Auto-submit on select or date change (filters + date inputs)
        document.querySelectorAll('.auto-submit').forEach(el => {
            el.addEventListener('change', () => el.closest('form').submit());
        });
    </script>
@endsection