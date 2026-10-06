@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== STATS CARDS — same anatomy as the dashboard's
           Total Employees KPI card ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }

        /* .kpi5-* is centralized in client.layout.head — no local copy. */

        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }





        .clear-all-link {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 12px;
            text-decoration: none;
            padding: 4px 10px;
            border-radius: 20px;
            transition: all 0.2s;
        }

        .clear-all-link:hover {
            background: #EFF6FF;
            color: #0D6EFD;
        }

        .clear-all-link i {
            font-size: 14px;
        }



        .filter-select {
            width: 100%;
            height: 36px;
            padding: 6px 28px 6px 10px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 8px center;
            background-size: 14px;
            appearance: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-input {
            width: 100%;
            height: 36px;
            padding: 6px 10px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            transition: all 0.2s;
        }

        .apply-btn {
            height: 36px;
            padding: 0 16px;
            background: #0D6EFD;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .apply-btn:hover {
            background: #0D6EFD;
        }



        .active-filters {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .active-filters-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .filter-tag {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            padding: 3px 10px 3px 8px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }

        .filter-tag i {
            color: var(--icon-color, #0D6EFD);
            font-size: 11px;
        }

        .filter-tag .remove-tag {
            color: #94a3b8;
            margin-left: 2px;
            cursor: pointer;
            transition: color 0.2s;
        }

        .filter-tag .remove-tag:hover {
            color: #0D6EFD;
        }

        .filter-tag.clear-all {
            background: #EFF6FF;
            border-color: #0D6EFD;
            color: #0D6EFD;
            font-weight: 600;
            text-decoration: none;
            padding: 3px 10px;
        }





        /* ==================== BADGES ==================== */
        .badge {
            padding: 4px 10px;
            font-weight: 500;
            font-size: 11px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge.bg-success {
            background: #EFF6FF !important;
            color: #0B5ED7;
        }

        .badge.bg-danger {
            background: #EFF6FF !important;
            color: #0D6EFD;
        }

        .badge.bg-info {
            background: #e0f2fe !important;
            color: #0369a1;
        }

        .badge.bg-warning {
            background: #EFF6FF !important;
            color: #0D6EFD;
        }

        .badge.bg-purple {
            background: #e0e7ff !important;
            color: #0D6EFD;
        }

        .badge.bg-secondary {
            background: #f1f5f9 !important;
            color: #475569;
        }

        /* ==================== FREQUENCY BADGES ==================== */
        .frequency-badge {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 500;
        }

        .frequency-weekly {
            background: #e0f2fe;
            color: #0369a1;
        }

        .frequency-monthly {
            background: #EFF6FF;
            color: #0B5ED7;
        }

        .frequency-yearly {
            background: #e0e7ff;
            color: #0D6EFD;
        }

        .frequency-no {
            background: #f1f5f9;
            color: #475569;
        }

        /* ==================== EMPLOYEE COLUMN (table) ====================
           Matches Monthly Payroll's own employee column exactly — gradient
           initials avatar overriding the shared .employee-avatar class,
           which is otherwise styled for an <img>, not a text div. */
        .employee-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }







        /* ==================== PROCESS CARD ==================== */
        .process-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            border: 1px solid #edf2f7;
            padding: 20px;
            margin-bottom: 24px;
        }

        .process-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
        }

        .process-title i {
            color: var(--icon-color, #0D6EFD);
            font-size: 18px;
        }










        /* ==================== EMPTY STATE ==================== */
        .empty-state {
            padding: 48px 24px;
            text-align: center;
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            margin: 24px;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-state h4 {
            color: #334155;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
        }

        /* ==================== EMPLOYEE AVATAR ==================== */
        .avatar-sm {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .avatar-md {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }

        .avatar-title {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bg-soft-primary {
            background: rgba(13, 110, 253, 0.1);
            color: #0D6EFD;
        }

        .bg-soft-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .bg-soft-warning {
            background: rgba(96, 165, 250, 0.1);
            color: #60a5fa;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 992px) {



            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 768px) {





            .apply-btn,
            .reset-btn {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ==================== COMPACT MODAL (Manual Credit) — chrome
           (max-width/modal-content/header/body/card padding) is centralized
           in client.layout.head; only this modal's own extras stay here. */
        .compact-modal .modal-header .fs-18 { font-size: 13px !important; }
        .compact-modal .form-group { margin-bottom: 10px; }

        /* Base styling for the modal's form controls — these classes
           (form-control-modern / btn-modern / employee-info / balance-preview)
           had no definitions anywhere on this page, so the browser was
           rendering them completely unstyled (no borders, no color, no
           button background). Single blue theme throughout. */
        .compact-modal label,
        .compact-modal .form-label {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 3px;
            font-size: 11.5px;
            font-weight: 600;
            color: #475569;
        }
        .compact-modal .form-label i { color: var(--icon-color, #0D6EFD); font-size: 11px; }
        .compact-modal .required-star { color: #0D6EFD; }
        .compact-modal .info-tooltip { color: #94a3b8; font-size: 10px; }

        .compact-modal .form-control,
        .compact-modal .form-control-modern {
            width: 100%;
            font-size: 11.5px;
            height: 34px;
            padding: 6px 10px;
            border: 1px solid #dfe5f0;
            border-radius: 8px;
            background: #f8fafc;
            color: #1a2236;
            transition: all .2s;
        }
        .compact-modal .form-control-modern:focus {
            border-color: #0D6EFD;
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .1);
            background: #fff;
        }
        .compact-modal textarea.form-control-modern { height: auto; min-height: 60px; resize: vertical; }

        .compact-modal .employee-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: #f8fafc;
            border: 1px solid #eaeef5;
            border-radius: 8px;
        }
        .compact-modal .employee-avatar {
            width: 30px; height: 30px; flex: none;
            background: #0D6EFD; color: #fff; border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 11px;
        }
        .compact-modal .employee-details { flex: 1; min-width: 0; }
        /* Employee picker options (Add Manual Credit) */
        .emp-opt { display: flex; align-items: center; gap: 8px; }
        .emp-opt-avatar {
            width: 26px; height: 26px; border-radius: 50%; flex: none;
            background: #EFF6FF; color: #0D6EFD; font-size: 10px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }
        .emp-opt-text { display: flex; flex-direction: column; min-width: 0; flex: 1; line-height: 1.25; }
        .emp-opt-name { font-size: 11.5px; font-weight: 600; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .emp-opt-email { font-size: 10px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .emp-opt-balance { flex: none; font-size: 10px; font-weight: 600; color: #0D6EFD; background: #EFF6FF; padding: 1px 7px; border-radius: 10px; }
        .emp-opt-dropdown .select2-results__option { padding: 6px 10px; }
        /* Hover keeps the text colours; only a light blue row background. */
        .emp-opt-dropdown .select2-results__option--highlighted,
        .emp-opt-dropdown .select2-results__option--highlighted[aria-selected] { background: #EFF6FF !important; color: #0f172a !important; }
        .emp-opt-dropdown .select2-results__option--highlighted .emp-opt-balance { background: #fff; }
        .emp-opt-dropdown .select2-search__field { font-size: 11.5px; padding: 5px 8px; border-radius: 6px; }
        /* The closed box: the picked name stays inside it, on one line. */
        .emp-opt-select .select2-selection--single { height: 34px !important; padding: 0 28px 0 10px !important; display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 8px; background-image: none; }
        .emp-opt-select .select2-selection--single .select2-selection__rendered { padding: 0 !important; line-height: 32px !important; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
        .emp-opt-select .select2-selection--single .select2-selection__arrow { height: 32px !important; top: 1px !important; right: 6px !important; }

        .compact-modal .employee-name { font-weight: 700; color: #1a2236; font-size: 11.5px; }
        .compact-modal .employee-meta { font-size: 9.5px; color: #6b7385; }

        .compact-modal .balance-preview {
            padding: 10px 12px;
            margin: 10px 0;
            background: #f4f6fb;
            border: 1px dashed #dfe5f0;
            border-radius: 10px;
        }
        .compact-modal .balance-preview-title {
            display: flex; align-items: center; gap: 6px;
            font-size: 10px; font-weight: 700; color: #475569;
            text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px;
        }
        .compact-modal .balance-amount { font-size: 18px; font-weight: 800; color: #1a2236; line-height: 1.2; }
        .compact-modal .balance-amount small { font-size: 10px; font-weight: 400; color: #6b7385; }

        .compact-modal .action-buttons { display: flex; margin-top: 10px; padding-top: 10px; gap: 8px; border-top: 1px solid #eaeef5; }
        .compact-modal .btn-modern {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px;
            font-size: 12px;
            border-radius: 8px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all .2s;
        }
        .compact-modal .btn-primary-modern { background: #0D6EFD; color: #fff; }
        .compact-modal .btn-primary-modern:hover:not(:disabled) { background: #0D6EFD; }
        .compact-modal .btn-primary-modern:disabled { opacity: .6; cursor: not-allowed; }
        .compact-modal .btn-secondary-modern {
            background: #f4f6fb;
            border-color: #dfe5f0;
            color: #475569;
        }
        .compact-modal .btn-secondary-modern:hover {
            background: #EFF6FF;
            border-color: #0D6EFD;
            color: #0D6EFD;
        }
    </style>
@endsection
@php
    $user = Auth::user();
    $role = $user->role;
@endphp

@section('content-area')
    <x-ui.page-header title="Leave Credit Management">
        <x-slot:actions>
            <div class="dropdown">
                <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10"
                    data-bs-auto-close="outside">
                    <i class="feather-download"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <a href="#" class="dropdown-item" onclick="exportToCSV()">
                        <i class="bi bi-filetype-csv me-3"></i>
                        <span>Export CSV</span>
                    </a>
                </div>
            </div>
            <a href="javascript:void(0)" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                data-bs-target="#manualCreditModal">
                <i class="feather-plus me-2"></i>
                <span>Manual Credit</span>
            </a>
            <a href="{{ route('leave-credit.reports') }}" class="btn btn-sm btn-info">
                <i class="feather-bar-chart-2 me-2"></i>
                <span>Reports</span>
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 18px !important;">
        <!-- Stats Cards -->
        <div class="stats-grid">
            <!-- Total Employees Card -->
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="fas fa-users"></i></span>
                    <span class="kpi5-pill">{{ $usersWithBalance }} w/ balance</span>
                </div>
                <div class="kpi5-value">{{ $totalUsers }}</div>
                <div class="kpi5-label">Total Employees</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $usersWithBalance }}</span><span class="l">Balance</span></div>
                    <div class="kpi5-stat"><span class="n">{{ $usersWithoutBalance->count() }}</span><span class="l">Pending</span></div>
                </div>
            </div>

            <!-- Total Balance Card -->
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="fas fa-coins"></i></span>
                    <span class="kpi5-pill">Days</span>
                </div>
                <div class="kpi5-value">{{ number_format($totalBalance ?? 0, 1) }}</div>
                <div class="kpi5-label">Total Leave Balance</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $usersWithBalance }}</span><span class="l">Employees</span></div>
                    <div class="kpi5-stat"><span class="n">{{ number_format($totalUsed ?? 0, 1) }}</span><span class="l">Used</span></div>
                </div>
            </div>

            <!-- Total Used Card -->
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="fas fa-calendar-check"></i></span>
                    <span class="kpi5-pill">{{ $totalTransactions ?? 0 }} txns</span>
                </div>
                <div class="kpi5-value">{{ number_format($totalUsed ?? 0, 1) }}</div>
                <div class="kpi5-label">Total Used (days)</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $totalTransactions ?? 0 }}</span><span class="l">Txns</span></div>
                    <div class="kpi5-stat"><span class="n">{{ number_format($totalBalance ?? 0, 1) }}</span><span class="l">Balance</span></div>
                </div>
            </div>

            <!-- Leave Types Card -->
            @php
                $weeklyCount = $leaveTypes->where('credit_type', 'weekly')->count();
                $monthlyCount = $leaveTypes->where('credit_type', 'monthly')->count();
                $yearlyCount = $leaveTypes->where('credit_type', 'yearly')->count();
            @endphp
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="fas fa-tags"></i></span>
                    <span class="kpi5-pill">Active</span>
                </div>
                <div class="kpi5-value">{{ $leaveTypes->count() }}</div>
                <div class="kpi5-label">Leave Types</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $monthlyCount }}</span><span class="l">Monthly</span></div>
                    <div class="kpi5-stat"><span class="n">{{ $yearlyCount }}</span><span class="l">Yearly</span></div>
                </div>
            </div>

            <!-- Pending Initialization Card -->
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="fas fa-hourglass-half"></i></span>
                    <span class="kpi5-pill">Pending</span>
                </div>
                <div class="kpi5-value">{{ $usersWithoutBalance->count() }}</div>
                <div class="kpi5-label">Need Initialization</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $totalUsers }}</span><span class="l">Total</span></div>
                    <div class="kpi5-stat"><span class="n">{{ $usersWithBalance }}</span><span class="l">Initialized</span></div>
                </div>
            </div>
        </div>
        @if($role == "admin" && $role == "manager")
        <!-- Process Credit Form -->
        <div class="process-card">
            <div class="process-title">
                <i class="feather-play-circle"></i>
                Process Auto Credit
            </div>
            <form action="{{ route('leave-credit.process') }}" method="POST" id="processCreditForm">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            {{-- <label class="fw-semibold mb-2">Frequency <span class="text-danger">*</span></label> --}}
                            <select name="frequency" class="filter-select" required>
                                <option value="">Select Frequency</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            {{-- <label class="fw-semibold mb-2">Credit Date</label> --}}
                            <input type="date" name="credit_date" class="filter-input" value="{{ date('Y-m-d') }}">
                            <small class="text-muted d-block mt-1">Leave empty for current date</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            {{-- <label class="fw-semibold mb-2">&nbsp;</label> --}}
                            <button type="submit" class="apply-btn w-100"
                                onclick="return confirm('Process auto credit for all employees?')">
                                <i class="feather-play"></i> Process Credits
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        @endif

        <!-- All Employees Leave Balance Table with Pagination -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="feather-users me-2"></i>
                            Employee Leave Balances
                        </h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-primary">{{ $allUsers->total() }} Total</span>
                            <span class="badge bg-success">{{ $usersWithBalanceCount }} With Balance</span>
                            <span class="badge bg-warning">{{ $usersWithoutBalance->count() }} No Balance</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Joining Date</th>
                                        <th>Current Balance</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($allUsers as $user)
                                        @php
                                            $balance = optional($user->leaveBalance)->sum('balance') ?? 0;
                                            $balanceClass =
                                            $balance > 0 ? 'bg-success' : ($balance < 0 ? 'bg-danger'
                                                        : 'bg-secondary');
                                            $hasBalance = $user->leaveBalance !== null;
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="employee-info">
                                                    <div class="employee-avatar">
                                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                                    </div>
                                                    <div class="employee-details">
                                                        <div class="employee-name">{{ $user->name ?? 'N/A' }} <small class="text-muted">({{ $user->employee_id ?? 'N/A' }})</small></div>
                                                        <div class="employee-id">{{ $user->email ?? '' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                @if ($user->joining_date)
                                                    <span class="badge bg-info">
                                                        {{ date('d M Y', strtotime($user->joining_date)) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $balanceClass }}"
                                                    style="font-size: 12px; padding: 6px 12px;">
                                                    <i
                                                        class="feather-{{ $balance > 0 ? 'arrow-up' : 'minus' }} me-1"></i>
                                                    {{ number_format($balance, 2) }} days
                                                </span>
                                            </td>
                                            <td>
                                                @if ($hasBalance)
                                                    <span class="badge bg-success">
                                                        <i class="feather-check-circle me-1"></i> Active
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning">
                                                        <i class="feather-alert-triangle me-1"></i> No Balance
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('leave-credit.transactions', $user->id) }}"
                                                    class="action-btn" title="View Transactions">
                                                    <i class="feather-eye"></i>
                                                </a>
                                                @if (!$hasBalance)
                                                    <form action="{{ route('leave-credit.new-joiner', $user->id) }}"
                                                        method="POST" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="action-btn initialize"
                                                            onclick="return confirm('Initialize leave balance for this user?')"
                                                            title="Initialize Balance">
                                                            <i class="feather-edit text-primary"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-users"></i>
                                                    <h4>No Employees Found</h4>
                                                    <p class="text-muted">No active employees in the system</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination Links -->
                        @if ($allUsers->hasPages())
                            <div class="pagination-wrapper">
                                <div class="pagination-info">
                                    Showing <strong>{{ $allUsers->firstItem() }}</strong> to 
                                    <strong>{{ $allUsers->lastItem() }}</strong> of 
                                    <strong>{{ $allUsers->total() }}</strong> entries
                                </div>
                                
                                <nav aria-label="Employee pagination">
                                    <ul class="pagination">
                                        {{-- Previous Page Link --}}
                                        @if ($allUsers->onFirstPage())
                                            <li class="page-item disabled">
                                                <span class="page-link">
                                                    <i class="fas fa-chevron-left"></i>
                                                </span>
                                            </li>
                                        @else
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $allUsers->previousPageUrl() }}" rel="prev">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        @endif

                                        {{-- Pagination Elements --}}
                                        @foreach ($allUsers->getUrlRange(1, $allUsers->lastPage()) as $page => $url)
                                            @if ($page == $allUsers->currentPage())
                                                <li class="page-item active">
                                                    <span class="page-link">{{ $page }}</span>
                                                </li>
                                            @else
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                                </li>
                                            @endif
                                        @endforeach

                                        {{-- Next Page Link --}}
                                        @if ($allUsers->hasMorePages())
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $allUsers->nextPageUrl() }}" rel="next">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        @else
                                            <li class="page-item disabled">
                                                <span class="page-link">
                                                    <i class="fas fa-chevron-right"></i>
                                                </span>
                                            </li>
                                        @endif
                                    </ul>
                                </nav>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Without Balance Section (if you want to keep it separate) -->
        @if ($usersWithoutBalance->count() > 0)
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="feather-alert-circle me-2"></i>
                                Users Needing Balance Initialization
                            </h5>
                            <span class="badge bg-warning">{{ $usersWithoutBalance->count() }} Pending</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Email</th>
                                            <th>Joining Date</th>
                                            <th>Available Leave Types</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($usersWithoutBalance as $user)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm me-2">
                                                            <span class="avatar-title bg-soft-warning rounded-circle">
                                                                {{ strtoupper(substr($user->name, 0, 2)) }}
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <strong>{{ $user->name }}</strong>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ $user->email }}</td>
                                                <td>
                                                    @if ($user->joining_date)
                                                        <span class="badge bg-info">
                                                            {{ date('d M Y', strtotime($user->joining_date)) }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @foreach ($leaveTypes as $type)
                                                        <span
                                                            class="frequency-badge frequency-{{ $type->credit_type }} me-1">
                                                            {{ $type->name }}
                                                        </span>
                                                    @endforeach
                                                </td>
                                                <td class="text-center">
                                                    <form action="{{ route('leave-credit.new-joiner', $user->id) }}"
                                                        method="POST" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="action-btn initialize"
                                                            onclick="return confirm('Initialize leave balance for this user?')"
                                                            title="Initialize">
                                                            <i class="feather-edit"></i>
                                                        </button>
                                                    </form>
                                                    <a href="{{ route('leave-credit.transactions', $user->id) }}"
                                                        class="action-btn" title="View Details">
                                                        <i class="feather-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Recent Credit Transactions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="feather-clock me-2"></i>
                            Recent Credit Transactions
                        </h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info">
                                <i class="feather-list me-1"></i>Total: {{ $recentTransactions->count() }}
                            </span>
                            <span class="badge bg-primary">
                                <i class="feather-calendar me-1"></i>Last 10
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Frequency</th>
                                        <th>Credited</th>
                                        <th>Before</th>
                                        <th>After</th>
                                        <th>Remarks</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentTransactions as $transaction)
                                        <tr>
                                            <td>
                                                <span
                                                    class="fw-semibold">{{ $transaction->created_at->format('d M Y') }}</span>
                                                <br>
                                                <small
                                                    class="text-muted">{{ $transaction->created_at->format('H:i') }}</small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-md me-2">
                                                        <span class="avatar-title bg-soft-primary rounded-circle">
                                                            {{ strtoupper(substr($transaction->user->name ?? 'NA', 0, 2)) }}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <strong>{{ $transaction->user->name ?? 'N/A' }}
                                                            (<small>{{ $transaction->user->employee_id ?? 'N/A' }}</small>)</strong>
                                                        <br>
                                                        <small
                                                            class="text-muted">{{ $transaction->user->email ?? '' }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-purple">{{ $transaction->leaveType->name ?? 'N/A' }}</span>
                                            </td>
                                            <td>
                                                @if ($transaction->leaveType)
                                                    <span
                                                        class="frequency-badge frequency-{{ $transaction->leaveType->credit_type }}">
                                                        {{ ucfirst($transaction->leaveType->credit_type) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-success">+{{ number_format($transaction->total_leaves, 2) }}</span>
                                            </td>
                                            <td>{{ number_format($transaction->before_leaves, 2) }}</td>
                                            <td><strong>{{ number_format($transaction->after_leaves, 2) }}</strong></td>
                                            <td>
                                                <span class="text-muted"
                                                    style="font-size: 11px;">{{ $transaction->remarks }}</span>
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('leave-credit.transactions', $transaction->user_id) }}"
                                                    class="action-btn" title="View Details">
                                                    <i class="feather-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-inbox"></i>
                                                    <h4>No Transactions Found</h4>
                                                    <p class="text-muted">No leave credit transactions have been recorded
                                                        yet</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Manual Credit Modal -->
    <div class="modal fade-scale" id="manualCreditModal" tabindex="-1" aria-labelledby="manualCreditModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add Manual Credit</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <div id="alertContainer"></div>
                            <form action="{{ route('leave-credit.manual.store') }}" method="POST" id="manualCreditForm">
                                @csrf

                                <div class="form-group">
                                    <label class="form-label">Select Employee *</label>
                                    {{-- No "select2" class: initialised in script-area with dropdownParent (search needs it inside a modal) --}}
                                    <select name="user_id" class="form-control" id="employeeSelect" required>
                                        <option value="">Search and select employee...</option>
                                        @foreach ($users as $u)
                                            <option value="{{ $u->id }}"
                                                data-name="{{ $u->name }}"
                                                data-empid="{{ $u->employee_id }}"
                                                data-balance="{{ (float) $u->leaveBalance->sum('balance') }}"
                                                data-balances="{{ $u->leaveBalance->pluck('balance', 'leave_type_id')->toJson() }}"
                                                data-joining="{{ $u->joining_date ? date('d M Y', strtotime($u->joining_date)) : 'N/A' }}"
                                                data-email="{{ $u->email }}">
                                                {{ $u->name }} ({{ $u->email }}) - Current:
                                                {{ (float) $u->leaveBalance->sum('balance') }} days
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div id="employeePreview" style="display: none;" class="mb-2">
                                    <div class="employee-info">
                                        <div class="employee-avatar" id="employeeInitials">JD</div>
                                        <div class="employee-details">
                                            <div class="employee-name" id="employeeName"></div>
                                            <div class="employee-meta" id="employeeMeta"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Leave Type *</label>
                                    <select name="leave_type_id" class="form-control" id="leaveTypeSelect" required>
                                        <option value="">Select Leave Type</option>
                                        @foreach ($leaveTypes as $type)
                                            <option value="{{ $type->id }}" data-credit="{{ $type->credit_value }}"
                                                data-frequency="{{ $type->credit_type }}"
                                                data-description="{{ $type->description }}">
                                                {{ $type->name }} ({{ $type->credit_value }} {{ $type->credit_type }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted" id="leaveTypeDescription"></small>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Credit Value (Days) *</label>
                                    <input type="number" name="credit_value" class="form-control-modern" id="creditValue"
                                        step="0.5" min="0.5" required placeholder="Enter credit value">
                                </div>

                                <div class="balance-preview">
                                    <div class="balance-preview-title">Balance Preview</div>
                                    <div class="row align-items-center">
                                        <div class="col-6">
                                            <div class="balance-amount" id="currentBalance">0.00</div>
                                            <small class="text-muted" id="currentBalanceLabel">Current</small>
                                        </div>
                                        <div class="col-6">
                                            <div class="balance-amount" id="newBalance">0.00</div>
                                            <small class="text-muted">After Credit</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Remarks</label>
                                    <textarea name="remarks" class="form-control-modern" id="remarks" rows="2"
                                        placeholder="Enter reason for manual credit (optional)"></textarea>
                                </div>

                                <div class="action-buttons">
                                    <button type="submit" class="btn-modern btn-primary-modern" id="submitBtn">
                                        Add Credit
                                    </button>
                                    <a href="javascript:void(0)" class="btn-modern btn-secondary-modern"
                                        data-bs-dismiss="modal">
                                        Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        // Configure toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000"
        };

        $(document).ready(function() {
            // Handle form submission with AJAX
            $('#processCreditForm').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var url = form.attr('action');
                var data = form.serialize();

                // Show loading state
                var submitBtn = form.find('button[type="submit"]');
                var originalText = submitBtn.html();
                submitBtn.html('<i class="feather-loader me-2"></i> Processing...').prop('disabled', true);

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            toastr.error(response.message);
                            submitBtn.html(originalText).prop('disabled', false);
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = 'An error occurred while processing';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        toastr.error(errorMessage);
                        submitBtn.html(originalText).prop('disabled', false);
                    }
                });
            });
        });

        // Export to CSV
        function exportToCSV() {
            let table = document.querySelector('.table:last-of-type');
            if (!table) {
                toastr.error('Table not found');
                return;
            }

            let rows = table.querySelectorAll('tr');
            let csv = [];

            // Add headers
            let headers = [];
            let headerCells = rows[0].querySelectorAll('th');
            for (let i = 0; i < headerCells.length; i++) {
                let headerText = headerCells[i].innerText.trim();
                if (headerText && headerText !== 'Action') {
                    headers.push('"' + headerText.replace(/"/g, '""') + '"');
                }
            }
            csv.push(headers.join(','));

            // Add data rows
            for (let i = 1; i < rows.length; i++) {
                let rowData = [];
                let cols = rows[i].querySelectorAll('td');

                for (let j = 0; j < cols.length - 1; j++) { // Exclude last column (Action)
                    let cellText = cols[j].innerText.replace(/"/g, '""').trim();
                    // Clean up the text (remove extra spaces, newlines)
                    cellText = cellText.replace(/\s+/g, ' ').trim();
                    rowData.push('"' + cellText + '"');
                }

                if (rowData.length > 0) {
                    csv.push(rowData.join(','));
                }
            }

            let csvFile = new Blob([csv.join('\n')], {
                type: 'text/csv'
            });
            let downloadLink = document.createElement('a');
            downloadLink.download = 'leave_credits_' + new Date().getTime() + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();

            toastr.success('CSV exported successfully');
        }

        // ==================== Manual Credit Modal ====================
        function getInitials(name) {
            if (!name) return 'NA';
            return name.split(' ').map(word => word[0]).join('').toUpperCase().substring(0, 2);
        }

        function formatNumber(num) {
            num = parseFloat(num) || 0;
            return num.toFixed(2);
        }

        function updateNewBalance() {
            const currentBalance = parseFloat($('#currentBalance').text()) || 0;
            const creditValue = parseFloat($('#creditValue').val()) || 0;
            $('#newBalance').text(formatNumber(currentBalance + creditValue));
        }

        // Employee picker: avatar + name (ID) + email, with the current balance on the right.
        // dropdownParent keeps the search box typeable inside the Bootstrap modal.
        function employeeOption(item) {
            if (!item.id) return item.text;
            const data = $(item.element).data();
            const $row = $(
                '<span class="emp-opt"><span class="emp-opt-avatar"></span>' +
                '<span class="emp-opt-text"><span class="emp-opt-name"></span><span class="emp-opt-email"></span></span>' +
                '<span class="emp-opt-balance"></span></span>'
            );
            $row.find('.emp-opt-avatar').text(getInitials(data.name));
            $row.find('.emp-opt-name').text(data.name + (data.empid ? ' (' + data.empid + ')' : ''));
            $row.find('.emp-opt-email').text(data.email || '');
            $row.find('.emp-opt-balance').text(formatNumber(data.balance) + ' days');
            return $row;
        }

        $('#employeeSelect').select2({
            placeholder: 'Search and select employee...',
            width: '100%',
            dropdownParent: $('#manualCreditModal'),
            dropdownCssClass: 'emp-opt-dropdown',
            templateResult: employeeOption,
            templateSelection: item => item.id ? $(item.element).data('name') : item.text,
        });
        $('#employeeSelect').next('.select2-container').addClass('emp-opt-select');

        // "Current" = the balance of the leave type being credited (the credit goes to
        // that one type); before a type is picked, the employee's total across all types.
        function showCurrentBalance() {
            const selected = $('#employeeSelect option:selected');
            if (!$('#employeeSelect').val()) {
                $('#currentBalance').text('0.00');
                $('#newBalance').text('0.00');
                return;
            }
            const typeId = $('#leaveTypeSelect').val();
            const balances = selected.data('balances') || {};
            const current = typeId ? balances[typeId] : selected.data('balance');
            $('#currentBalance').text(formatNumber(current));
            updateNewBalance();
        }

        $('#employeeSelect').on('change', function() {
            const selected = $(this).find('option:selected');
            const userId = $(this).val();

            if (userId) {
                const name = selected.data('name');
                const email = selected.data('email');
                const joiningDate = selected.data('joining');

                $('#employeePreview').show();
                $('#employeeInitials').text(getInitials(name));
                $('#employeeName').text(name);
                $('#employeeMeta').html(
                    `<i class="fas fa-envelope me-1"></i> ${email} | <i class="fas fa-calendar me-1"></i> Joined: ${joiningDate}`
                );
                $('#creditValue').val('');
                showCurrentBalance();
            } else {
                $('#employeePreview').hide();
                $('#currentBalance').text('0.00');
                $('#newBalance').text('0.00');
                $('#creditValue').val('');
            }
        });

        $('#leaveTypeSelect').on('change', function() {
            const selected = $(this).find('option:selected');
            const creditValue = selected.data('credit') || 0;
            const description = selected.data('description');
            const frequency = selected.data('frequency');

            $('#leaveTypeDescription').text(description ? `📝 ${description}` : `⏱️ Frequency: ${frequency || 'N/A'}`);

            const currentCredit = $('#creditValue').val();
            if (!currentCredit && creditValue > 0) {
                $('#creditValue').val(creditValue);
            }

            showCurrentBalance();
        });

        $('#creditValue').on('input', updateNewBalance);

        $('#manualCreditForm').on('submit', function(e) {
            e.preventDefault();

            if (!$('select[name="user_id"]').val()) {
                toastr.error('Please select an employee');
                return false;
            }

            const creditValue = parseFloat($('#creditValue').val());
            if (!creditValue || creditValue <= 0) {
                toastr.error('Please enter a valid credit value');
                return false;
            }

            var form = $(this);
            var submitBtn = $('#submitBtn');
            var originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    submitBtn.prop('disabled', false).html(originalText);
                    if (response.success) {
                        toastr.success(response.message);
                        $('#manualCreditModal').modal('hide');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    submitBtn.prop('disabled', false).html(originalText);
                    if (xhr.status === 422 && xhr.responseJSON?.errors) {
                        $.each(xhr.responseJSON.errors, function(key, value) {
                            toastr.error(value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'An error occurred while processing');
                    }
                }
            });

            return false;
        });

        $('#manualCreditModal').on('hidden.bs.modal', function() {
            $('#manualCreditForm')[0].reset();
            $('#employeeSelect').val('').trigger('change.select2');
            $('#employeePreview').hide();
            $('#currentBalance').text('0.00');
            $('#newBalance').text('0.00');
            $('#leaveTypeDescription').text('');
        });
    </script>
@endsection