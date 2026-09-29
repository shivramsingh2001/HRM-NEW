@extends('client.layout.master')

@section('style')
    <style>
        /* Same look as the other Reports pages: KPI tiles, one-row filter bar, compact table. */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .6rem; margin-bottom: .8rem; }
        .stats-card {
            background: white; border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px;
            display: flex; align-items: center; box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
        }
        .stats-icon { width: 30px; height: 30px; background: #e3edfe; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 8px; flex: none; }
        .stats-icon i { font-size: 13px; color: #1e3a8a; }
        .stats-info h3 { font-size: 15px; font-weight: 700; margin: 0 0 1px 0; color: #1a2236; line-height: 1.2; }
        .stats-info p { font-size: 9.5px; color: #6b7385; margin: 0; }

        .filter-section { background: #fff; border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .form-control-sm-custom { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 10px; font-size: 11px; height: 32px; background-color: #fff; }
        .form-control-sm-custom:focus { border-color: #1e3a8a; box-shadow: 0 0 0 .15rem rgba(30, 58, 138, .12); outline: none; }
        .btn-sm-custom-outline { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; text-decoration: none; }
        .btn-sm-custom-outline:hover { background: #e3edfe; color: #1e3a8a; }

        #reportTable { font-size: 11px; }
        #reportTable th { font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #6b7385; padding: 6px 10px; white-space: nowrap; background: #f7faff; }
        #reportTable td { padding: 6px 10px; vertical-align: middle; }

        /* sub-tabs to switch between the 5 leave reports without leaving the page */
        .rpt-subnav { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
        .rpt-subnav a {
            font-size: 11px; font-weight: 600; padding: 6px 14px; border-radius: 20px; text-decoration: none;
            color: #475569; background: #f4f6fb; border: 1px solid #dfe5f0;
        }
        .rpt-subnav a.active { background: #1e3a8a; color: #fff; border-color: #1e3a8a; }
        .rpt-subnav a:hover:not(.active) { background: #e3edfe; color: #1e3a8a; }

        /* ==================== Leave Balance tab — same card/table anatomy
           as Leave Credit Management (kpi5-* anatomy is centralized in
           theme-custom.css; only the text-initials avatar override and the
           blue-tinted badge variants are page-local, matching that page). */
        .balance-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 14px; }
        @media (max-width: 1200px) { .balance-stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .balance-stats-grid { grid-template-columns: 1fr; } }

        #balanceTable .employee-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 14px;
        }
        #balanceTable .badge { padding: 4px 10px; font-weight: 500; font-size: 11px; border-radius: 20px; display: inline-flex; align-items: center; gap: 4px; }
        #balanceTable .badge.bg-success { background: #e3edfe !important; color: #1d4ed8; }
        #balanceTable .badge.bg-danger { background: #e3edfe !important; color: #1e3a8a; }
        #balanceTable .badge.bg-secondary { background: #f1f5f9 !important; color: #475569; }
        #balanceTable .badge.bg-purple { background: #e0e7ff !important; color: #1e3a8a; }

        .empty-state {
            padding: 48px 24px;
            text-align: center;
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
        }
        .empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 16px; }
        .empty-state h4 { color: #334155; font-size: 18px; font-weight: 600; margin-bottom: 8px; }
        .empty-state p { color: #64748b; font-size: 14px; margin-bottom: 0; }

        .pagination-wrapper { padding: 16px 20px; border-top: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .pagination-info { font-size: 12px; color: #64748b; }
        .pagination-info strong { color: #1e293b; font-weight: 600; }
        .pagination { display: flex; gap: 5px; margin: 0; padding: 0; list-style: none; }
        .page-item { margin: 0; }
        .page-link { display: flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; color: #475569; font-size: 12px; font-weight: 500; text-decoration: none; transition: all .2s; }
        .page-link:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
        .page-item.active .page-link { background: #1e3a8a; border-color: #1e3a8a; color: #fff; }
        .page-item.disabled .page-link { background: #f1f5f9; border-color: #e2e8f0; color: #94a3b8; pointer-events: none; }
    </style>
@endsection

@section('content-area')
    @php
        $csvUrl = route('report.leave.show', array_merge(['report' => $report], request()->query(), ['export' => 'csv']));
        $needsPeriod = in_array($report, ['register', 'summary', 'regularization', 'wfh-travel']);
        $tileIcons = ['feather-users', 'feather-check-circle', 'feather-clock', 'feather-x-circle'];
    @endphp

    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">{{ $title }}</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('report.attendance.index') }}#leaveReportTab">Reports</a></li>
                <li class="breadcrumb-item">{{ $reports[$report] }}</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <a href="{{ $csvUrl }}" class="btn btn-sm btn-primary">
                <i class="feather-download me-1"></i> Export CSV
            </a>
        </div>
    </div>

    <div class="content-area-body" style="padding: 20px !important;">
        <div class="rpt-subnav">
            @foreach ($reports as $key => $label)
                <a href="{{ route('report.leave.show', $key) }}" class="{{ $key === $report ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>

        @if ($report === 'balance')
            <!-- Stats Cards — same kpi5-card anatomy as Leave Credit Management -->
            <div class="balance-stats-grid">
                <div class="kpi5-card">
                    <div class="kpi5-top">
                        <span class="kpi5-icon"><i class="fas fa-users"></i></span>
                        <span class="kpi5-pill">{{ $balanceStats['departmentsCount'] }} depts</span>
                    </div>
                    <div class="kpi5-value">{{ $balanceStats['employees'] }}</div>
                    <div class="kpi5-label">Employees</div>
                    <div class="kpi5-divider"></div>
                    <div class="kpi5-foot">
                        <div class="kpi5-stat"><span class="n">{{ $balanceStats['leaveTypesCount'] }}</span><span class="l">Leave Types</span></div>
                        <div class="kpi5-stat"><span class="n">{{ $balanceRows->total() }}</span><span class="l">Rows</span></div>
                    </div>
                </div>

                <div class="kpi5-card">
                    <div class="kpi5-top">
                        <span class="kpi5-icon"><i class="fas fa-coins"></i></span>
                        <span class="kpi5-pill">Days</span>
                    </div>
                    <div class="kpi5-value">{{ number_format($balanceStats['totalBalance'], 1) }}</div>
                    <div class="kpi5-label">Total Leave Balance</div>
                    <div class="kpi5-divider"></div>
                    <div class="kpi5-foot">
                        <div class="kpi5-stat"><span class="n">{{ $balanceStats['employees'] }}</span><span class="l">Employees</span></div>
                        <div class="kpi5-stat"><span class="n">{{ number_format($balanceStats['avgBalance'], 1) }}</span><span class="l">Avg/Employee</span></div>
                    </div>
                </div>

                <div class="kpi5-card">
                    <div class="kpi5-top">
                        <span class="kpi5-icon"><i class="fas fa-tags"></i></span>
                        <span class="kpi5-pill">Active</span>
                    </div>
                    <div class="kpi5-value">{{ $balanceStats['leaveTypesCount'] }}</div>
                    <div class="kpi5-label">Leave Types</div>
                    <div class="kpi5-divider"></div>
                    <div class="kpi5-foot">
                        <div class="kpi5-stat"><span class="n">{{ $balanceStats['departmentsCount'] }}</span><span class="l">Departments</span></div>
                        <div class="kpi5-stat"><span class="n">{{ $balanceRows->total() }}</span><span class="l">Rows</span></div>
                    </div>
                </div>

                <div class="kpi5-card">
                    <div class="kpi5-top">
                        <span class="kpi5-icon"><i class="fas fa-building"></i></span>
                        <span class="kpi5-pill">Depts</span>
                    </div>
                    <div class="kpi5-value">{{ $balanceStats['departmentsCount'] }}</div>
                    <div class="kpi5-label">Departments Covered</div>
                    <div class="kpi5-divider"></div>
                    <div class="kpi5-foot">
                        <div class="kpi5-stat"><span class="n">{{ $balanceStats['employees'] }}</span><span class="l">Employees</span></div>
                        <div class="kpi5-stat"><span class="n">{{ number_format($balanceStats['totalBalance'], 1) }}</span><span class="l">Balance</span></div>
                    </div>
                </div>
            </div>
        @else
            <div class="stats-grid">
                @foreach ($summary as $label => $value)
                    <div class="stats-card">
                        <div class="stats-icon"><i class="{{ $tileIcons[$loop->index % count($tileIcons)] }}"></i></div>
                        <div class="stats-info">
                            <h3>{{ $value }}</h3>
                            <p>{{ $label }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Filters (one row, no captions) --}}
        <div class="filter-section">
            <form action="{{ route('report.leave.show', $report) }}" method="GET" id="filterForm">
                <div class="filter-row">
                    @if ($needsPeriod)
                        <div class="filter-item">
                            <input type="date" name="from_date" value="{{ $from }}" class="form-control-sm-custom" title="From date" aria-label="From date">
                        </div>
                        <div class="filter-item">
                            <input type="date" name="to_date" value="{{ $to }}" class="form-control-sm-custom" title="To date" aria-label="To date">
                        </div>
                    @endif

                    <div class="filter-item">
                        <select name="department_id" class="form-control-sm-custom auto-submit">
                            <option value="">-- All Departments --</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id }}" @selected(($filters['department_id'] ?? '') == $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if (in_array($report, ['balance', 'register']))
                        <div class="filter-item">
                            <select name="leave_type_id" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Leave Types --</option>
                                @foreach ($leaveTypes as $t)
                                    <option value="{{ $t->id }}" @selected(($filters['leave_type_id'] ?? '') == $t->id)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($report === 'balance' && $branchesEnabled)
                        <div class="filter-item">
                            <select name="branch_id" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Branches --</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" @selected(($filters['branch_id'] ?? '') == $b->id)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($report === 'balance')
                        <div class="filter-item">
                            <input type="text" name="search" onchange="this.form.submit()" onkeydown="if (event.key === 'Enter') { event.preventDefault(); this.form.submit(); }" class="form-control-sm-custom" style="width: 200px;"
                                placeholder="Search employee, ID or email…" value="{{ $filters['search'] ?? '' }}">
                        </div>
                    @endif

                    @if (in_array($report, ['register', 'regularization']))
                        <div class="filter-item">
                            <select name="status" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Status --</option>
                                @if ($report === 'register')
                                    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'cancelled' => 'Cancelled'] as $k => $l)
                                        <option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>
                                    @endforeach
                                @else
                                    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $l)
                                        <option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    @endif

                    @if ($report === 'wfh-travel')
                        <div class="filter-item">
                            <select name="request_type_id" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Types --</option>
                                @foreach ($requestTypes as $t)
                                    <option value="{{ $t->id }}" @selected(($filters['request_type_id'] ?? '') == $t->id)>{{ $t->type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <select name="status" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Status --</option>
                                @foreach (['PENDING' => 'Pending', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected', 'CANCELLED' => 'Cancelled'] as $k => $l)
                                    <option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($needsPeriod || $report === 'balance')
                    @endif
                    <div class="filter-item"><a href="{{ route('report.leave.show', $report) }}" class="btn-sm-custom-outline" title="Reset filters" aria-label="Reset filters"><i class="feather-refresh-cw"></i></a></div>
                </div>
            </form>
        </div>

        @if ($report === 'balance')
            <!-- Employee Leave Balances table — same anatomy as Leave Credit Management -->
            <div class="card stretch stretch-full">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="feather-users me-2"></i>
                        Employee Leave Balances
                    </h5>
                    <div class="d-flex gap-2">
                        <span class="badge bg-primary">{{ $balanceRows->total() }} Rows</span>
                        <span class="badge bg-success">{{ $balanceStats['employees'] }} Employees</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="balanceTable">
                            <thead>
                                <tr>
                                    <th>Sr.No</th>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Designation</th>
                                    @if ($branchesEnabled)
                                        <th>Branch</th>
                                    @endif
                                    <th>Leave Type</th>
                                    <th>Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($balanceRows as $r)
                                    @php
                                        $balance = (float) $r->balance;
                                        $balanceClass = $balance > 0 ? 'bg-success' : ($balance < 0 ? 'bg-danger' : 'bg-secondary');
                                    @endphp
                                    <tr>
                                        <td>{{ $balanceRows->firstItem() + $loop->index }}</td>
                                        <td>
                                            <div class="employee-info">
                                                <div class="employee-avatar">
                                                    {{ strtoupper(substr($r->name ?? 'NA', 0, 2)) }}
                                                </div>
                                                <div class="employee-details">
                                                    <div class="employee-name">{{ $r->name ?? 'N/A' }} <small class="text-muted">({{ $r->employee_id ?? 'N/A' }})</small></div>
                                                    <div class="employee-id">{{ $r->email ?? '' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $r->department ?? '—' }}</td>
                                        <td>{{ $r->designation ?? '—' }}</td>
                                        @if ($branchesEnabled)
                                            <td>{{ $r->branch ?? '—' }}</td>
                                        @endif
                                        <td><span class="badge bg-purple">{{ $r->leave_type }}</span></td>
                                        <td>
                                            <span class="badge {{ $balanceClass }}" style="font-size: 12px; padding: 6px 12px;">
                                                <i class="feather-{{ $balance > 0 ? 'arrow-up' : 'minus' }} me-1"></i>
                                                {{ number_format($balance, 2) }} days
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $branchesEnabled ? 7 : 6 }}" class="text-center py-5">
                                            <div class="empty-state">
                                                <i class="feather-users"></i>
                                                <h4>No Records Found</h4>
                                                <p class="text-muted">Nothing to show for this selection.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($balanceRows->hasPages())
                        <div class="pagination-wrapper">
                            <div class="pagination-info">
                                Showing <strong>{{ $balanceRows->firstItem() }}</strong> to
                                <strong>{{ $balanceRows->lastItem() }}</strong> of
                                <strong>{{ $balanceRows->total() }}</strong> entries
                            </div>
                            <nav aria-label="Leave balance pagination">
                                <ul class="pagination">
                                    @if ($balanceRows->onFirstPage())
                                        <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i></span></li>
                                    @else
                                        <li class="page-item"><a class="page-link" href="{{ $balanceRows->previousPageUrl() }}" rel="prev"><i class="fas fa-chevron-left"></i></a></li>
                                    @endif

                                    @foreach ($balanceRows->getUrlRange(1, $balanceRows->lastPage()) as $page => $url)
                                        @if ($page == $balanceRows->currentPage())
                                            <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                                        @else
                                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                                        @endif
                                    @endforeach

                                    @if ($balanceRows->hasMorePages())
                                        <li class="page-item"><a class="page-link" href="{{ $balanceRows->nextPageUrl() }}" rel="next"><i class="fas fa-chevron-right"></i></a></li>
                                    @else
                                        <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-right"></i></span></li>
                                    @endif
                                </ul>
                            </nav>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="card stretch stretch-full">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="reportTable">
                            <thead>
                                <tr>
                                    @foreach ($headers as $h)
                                        <th>{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        @foreach ($row as $cell)
                                            <td>{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($headers) }}" class="text-center py-4 text-muted">Nothing to show for
                                            this selection.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('script-area')
    <script>
        $(function() {
            const form = $('#filterForm');
            form.find('select.auto-submit').on('change', () => form.submit());
            form.find('input[type="date"]').on('change', function() {
                const from = form.find('input[name="from_date"]').val();
                const to = form.find('input[name="to_date"]').val();
                if (from && to && from > to) {
                    if (window.toastr) toastr.error('From date cannot be greater than To date');
                    $(this).val('');
                    return;
                }
                form.submit();
            });
        });
    </script>
@endsection
