@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ALL-BLUE THEME ==================== */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .6rem; margin-bottom: .8rem; }
    .stats-card {
        background: white; border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px;
        display: flex; align-items: center; box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
    }
    .stats-icon { width: 30px; height: 30px; background: #e3edfe; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 8px; flex: none; }
    .stats-icon i { font-size: 13px; color: #1e3a8a; }
    .stats-info h3 { font-size: 15px; font-weight: 700; margin: 0 0 1px 0; color: #1a2236; line-height: 1.2; }
    .stats-info p { font-size: 9.5px; color: #6b7385; margin: 0; }

    .ot-filter-bar { background: #fff; border: 1px solid #eef2f6; border-radius: 10px; padding: 10px 12px; margin-bottom: .8rem; }
    .ot-filter-bar .form-control, .ot-filter-bar select {
        font-size: 10.5px; padding: 4px 8px; height: auto; border-radius: 7px; border: 1px solid #dfe5f0;
    }
    .ot-filter-bar .form-control:focus, .ot-filter-bar select:focus { border-color: #1e3a8a; box-shadow: 0 0 0 .15rem rgba(30,58,138,.12); }
    .ot-filter-bar .btn-apply { background: #1e3a8a; border-color: #1e3a8a; color: #fff; font-size: 10.5px; padding: 4px 14px; border-radius: 7px; }
    .ot-filter-bar .btn-apply:hover { background: #16295e; border-color: #16295e; }

    #overtimeTable { font-size: 10.5px; }
    #overtimeTable th { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; color: #6b7385; background: #f7faff; padding: 6px 8px; border-bottom: 1px solid #e3edfe; }
    #overtimeTable td { padding: 6px 8px; vertical-align: middle; }
    #overtimeTable tr:hover td { background: #fafcff; }
    #overtimeTable .hours-pill { font-size: 9px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
    #overtimeTable .hours-approved { background: #1e3a8a; color: #fff; }
    #overtimeTable .hours-pending { background: #93c5fd; color: #1e3a8a; }
    #overtimeTable .hours-rejected { background: #e2e8f0; color: #475569; }
    #overtimeTable .cost-value { font-weight: 700; color: #1e3a8a; }
</style>
@endsection

@section('content-area')
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">Overtime Report (Monthly)</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('report.attendance.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Overtime Monthly</li>
            </ul>
        </div>
    </div>

    <div class="content-area-body" style="padding: 20px !important;">
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-users"></i></div>
                <div class="stats-info">
                    <h3>{{ $stats['total_employees'] }}</h3>
                    <p>Employees with OT</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-list"></i></div>
                <div class="stats-info">
                    <h3>{{ $stats['total_requests'] }}</h3>
                    <p>Total OT Requests</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-check-circle"></i></div>
                <div class="stats-info">
                    <h3>{{ number_format($stats['total_approved_hours'], 1) }}</h3>
                    <p>Approved Hours</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-clock"></i></div>
                <div class="stats-info">
                    <h3>{{ number_format($stats['total_pending_hours'], 1) }}</h3>
                    <p>Pending Hours</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fa fa-inr"></i></div>
                <div class="stats-info">
                    <h3>₹{{ number_format($stats['total_estimated_cost'], 0) }}</h3>
                    <p>Est. OT Payout ({{ rtrim(rtrim($stats['multiplier'], '0'), '.') }}x)</p>
                </div>
            </div>
        </div>

        <form method="GET" class="ot-filter-bar row g-2 align-items-end">
            <div class="col-md-3">
                <label class="d-block text-muted mb-1" style="font-size:9.5px;">Month</label>
                <input type="month" name="month" class="form-control" value="{{ $selectedMonth }}">
            </div>
            <div class="col-md-3">
                <label class="d-block text-muted mb-1" style="font-size:9.5px;">Search Employee</label>
                <input type="text" name="search" class="form-control" placeholder="Name or employee ID" value="{{ $search }}">
            </div>
            <div class="col-md-3">
                <label class="d-block text-muted mb-1" style="font-size:9.5px;">Department</label>
                <select name="department" class="form-control">
                    <option value="">All Departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" {{ (string) $departmentFilter === (string) $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="d-block text-muted mb-1" style="font-size:9.5px;">Status</label>
                <select name="status" class="form-control">
                    <option value="">Any Status</option>
                    <option value="approved" {{ $statusFilter === 'approved' ? 'selected' : '' }}>Has Approved</option>
                    <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Has Pending</option>
                    <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Has Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="d-block text-muted mb-1" style="font-size:9.5px;">Branch</label>
                <select name="branch_id" class="form-control">
                    <option value="">All Branches</option>
                    @foreach ($branches ?? [] as $b)
                        <option value="{{ $b->id }}" {{ (string) ($branchFilter ?? '') === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-apply w-100">Apply</button>
            </div>
        </form>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0" id="overtimeTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Branch</th>
                                <th class="text-center">Requests</th>
                                <th class="text-center">Approved</th>
                                <th class="text-center">Pending</th>
                                <th class="text-center">Rejected</th>
                                <th class="text-end">Rate (₹/hr)</th>
                                <th class="text-end">Est. Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reportData as $row)
                                <tr>
                                    <td>
                                        <div class="employee-info">
                                            <div class="employee-avatar"
                                                style="background:#1e3a8a;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">
                                                {{ strtoupper(substr($row['name'], 0, 2)) }}</div>
                                            <div class="employee-details">
                                                <div class="employee-name">
                                                    {{ $row['name'] }}
                                                    <small class="text-muted">({{ $row['employee_id'] ?? 'N/A' }})</small>
                                                </div>
                                                <div class="employee-email">{{ $row['designation'] ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $row['department'] ?? '—' }}</td>
                                    <td>{{ $row['branch'] ?? '—' }}</td>
                                    <td class="text-center">{{ $row['request_count'] }}</td>
                                    <td class="text-center">
                                        <span class="hours-pill hours-approved">{{ number_format($row['approved_hours'], 1) }}h ({{ $row['approved_count'] }})</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="hours-pill hours-pending">{{ number_format($row['pending_hours'], 1) }}h ({{ $row['pending_count'] }})</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="hours-pill hours-rejected">{{ number_format($row['rejected_hours'], 1) }}h ({{ $row['rejected_count'] }})</span>
                                    </td>
                                    <td class="text-end">₹{{ number_format($row['hourly_rate'], 2) }}</td>
                                    <td class="text-end cost-value">₹{{ number_format($row['estimated_cost'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No overtime requests found for this month.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="alert-info-blue mt-3" style="background:#eef3fd;border:1px solid #bfd3f7;color:#1e3a8a;border-radius:10px;font-size:10.5px;padding:8px 12px;">
            <i class="feather-info me-1"></i> Estimated cost = approved hours &times; hourly rate &times; the tenant's overtime multiplier ({{ rtrim(rtrim($stats['multiplier'], '0'), '.') }}x). Hourly rate is derived from
            each employee's current payroll assignment — this is an estimate for planning, not a payroll-authoritative figure.
        </div>
    </div>
@endsection
