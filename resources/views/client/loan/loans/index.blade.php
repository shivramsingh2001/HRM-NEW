@extends('client.layout.master')

@section('title', 'Loan Requests')

@section('style')
    <style>
        /* Loan-specific statuses beyond the shared .status-badge mapping in
           theme-custom.css (pending/approved/active/cancelled already map
           there — this only adds the two loan-only words it doesn't know),
           kept single-blue instead of gray/red. */
        .status-badge[data-status="closed"] {
            background: #f1f5f9;
            color: #475569;
        }
        .status-badge[data-status="default"] {
            background: #0D6EFD;
            color: #ffffff;
        }








        .badge.bg-info {
            background: #dbeafe !important;
            color: #0D6EFD;
        }






        .clear-all-link {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #6b7385;
            font-size: 12px;
            text-decoration: none;
            padding: 4px 10px;
            border-radius: 20px;
            transition: all 0.2s;
        }

        .clear-all-link:hover { background: #EFF6FF; color: #0D6EFD; }




        .filter-select,
        .filter-item .form-control {
            width: 100%;
            height: 36px;
            padding: 6px 28px 6px 10px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background-color: #f8fafc;
            transition: all 0.2s;
        }

        .filter-select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 14px;
            appearance: none;
            cursor: pointer;
        }


        .filter-select:focus,
        .filter-item .form-control:focus {
            background-color: white;
            border-color: #0D6EFD;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
            outline: none;
        }

        .filter-select:hover,
        .filter-item .form-control:hover { background-color: white; border-color: #94a3b8; }



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
            color: #6b7385;
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
        }

        .filter-tag i { color: var(--icon-color, #0D6EFD); font-size: 11px; }

        .filter-tag .remove-tag {
            color: #94a3b8;
            margin-left: 2px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
        }

        .filter-tag .remove-tag:hover { color: #0D6EFD; }

        .filter-tag.clear-all {
            background: #EFF6FF;
            border-color: #0D6EFD;
            color: #0D6EFD;
            font-weight: 600;
            text-decoration: none;
            padding: 3px 10px;
        }

        .emi-preview {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            border: 1px solid #e2e8f0;
        }

        .emi-amount {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-mid);
        }

        .approval-note {
            background: #dbeafe;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 12px;
            margin-top: 15px;
        }

        .detail-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
        }

        .detail-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 5px;
            letter-spacing: 0.3px;
        }

        .detail-value {
            font-size: 10px;
            font-weight: 600;
            color: #1e293b;
        }

        .detail-value-small {
            font-size: 10px;
            font-weight: 500;
            color: #475569;
        }

        .progress-bar-custom {
            height: 8px;
            border-radius: 10px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #0D6EFD;
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .schedule-table th {
            font-size: 11px;
            background: #f8fafc;
            padding: 10px 12px;
        }

        .schedule-table td {
            font-size: 12px;
            padding: 10px 12px;
            vertical-align: middle;
        }

        .repayment-status {
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 10px;
            font-weight: 500;
        }

        .repayment-paid {
            background: #EFF6FF;
            color: #0D6EFD;
        }

        .repayment-pending {
            background: #dbeafe;
            color: #0D6EFD;
        }

        .repayment-overdue {
            background: #0D6EFD;
            color: #ffffff;
        }

        /* ==================== ADD/EDIT LOAN MODALS - small font,
           small margin/padding, same treatment as the Overtime modals ==================== */
        #addLoanModal .modal-header,
        #editLoanModal .modal-header {
            background: #fff !important;
            border-bottom: 1px solid #edf2f7 !important;
            padding: 10px 16px !important;
        }

        #addLoanModal .modal-header .fs-18,
        #editLoanModal .modal-header .fs-18 {
            font-size: 13px !important;
            color: #1e293b !important;
        }

        #addLoanModal .modal-header .close-icon,
        #editLoanModal .modal-header .close-icon {
            width: 26px;
            height: 26px;
        }

        #addLoanModal label,
        #editLoanModal label {
            font-size: 11px !important;
        }

        #addLoanModal .form-control,
        #editLoanModal .form-control {
            font-size: 11.5px !important;
            padding: 6px 10px !important;
        }

        #addLoanModal .mb-3,
        #editLoanModal .mb-3 {
            margin-bottom: 10px !important;
        }

        #addLoanModal small,
        #editLoanModal small {
            font-size: 10.5px !important;
        }

        #addLoanModal .btn,
        #editLoanModal .btn {
            font-size: 11.5px !important;
            padding: 6px 14px !important;
        }

        #addLoanModal .emi-preview,
        #editLoanModal .emi-preview {
            padding: 10px !important;
            margin-top: 10px !important;
        }

        #addLoanModal .emi-amount,
        #editLoanModal .emi-amount {
            font-size: 15px !important;
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Loan Management" current="Loan Requests">
        <x-slot:actions>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addLoanModal">
                <i class="feather-plus me-2"></i>
                <span>New Request</span>
            </button>
        </x-slot:actions>
    </x-ui.page-header>



    <div class="main-content" style="padding: 20px !important;">
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-calendar"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statistics['emi_loans'] ?? 0 }}</div>
                    <div class="stats-label">Total EMI Loans</div>
                </div>
            </div>

            <div class="stats-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-zap"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statistics['lumpsum_loans'] ?? 0 }}</div>
                    <div class="stats-label">Total Lump Sum Loans</div>
                </div>
            </div>

            <div class="stats-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-credit-card"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">₹{{ number_format($statistics['total_emi_paid'] ?? 0, 2) }}</div>
                    <div class="stats-label">Total EMI Collected</div>
                </div>
            </div>

            <div class="stats-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-alert-circle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">₹{{ number_format($statistics['outstanding_amount'] ?? 0, 2) }}</div>
                    <div class="stats-label">Outstanding Amount</div>
                </div>
            </div>
        </div>

        <!-- Compact Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Loan Requests
                    @php
                        $activeFilterCount = collect(request()->only(['status', 'search']))->filter()->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['status', 'search']))
                    <a href="{{ route('loan.requests.index') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ route('loan.requests.index') }}" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            <option value="default" {{ request('status') == 'default' ? 'selected' : '' }}>Default</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <input type="text" class="form-control" name="search" placeholder="Search loan number..."
                            value="{{ request('search') }}">
                    </div>
                    <div class="filter-item narrow">
                        <a href="{{ route('loan.requests.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            @if (request()->hasAny(['status', 'search']))
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>

                    @if (request('status'))
                        <span class="filter-tag">
                            <i class="feather-activity"></i>
                            Status: {{ ucfirst(request('status')) }}
                            <a href="{{ route('loan.requests.index', request()->except(['status', 'page'])) }}" class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('search'))
                        <span class="filter-tag">
                            <i class="feather-search"></i>
                            Search: {{ request('search') }}
                            <a href="{{ route('loan.requests.index', request()->except(['search', 'page'])) }}" class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('loan.requests.index') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Loan Number</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>EMI / LumpSum</th>
                                        <th>Tenure</th>
                                        <th>Status</th>
                                        <th>Applied Date</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($loans as $index => $loan)
                                        <tr>
                                            <td>{{ $loans->firstItem() + $index }}</td>
                                            <td>{{ $loan->loan_number }}</td>
                                            <td>{{ $loan->loanCategory->name ?? 'N/A' }}</td>
                                            <td>₹ {{ number_format($loan->amount, 2) }}</td>
                                            <td>
                                                @if ($loan->repayment_type == 'lumpsum')
                                                    <span class="badge bg-info">Lump Sum</span>
                                                    <small class="d-block text-muted">Due:
                                                        {{ $loan->lumpsum_due_date ? \Carbon\Carbon::parse($loan->lumpsum_due_date)->format('d M Y') : 'N/A' }}</small>
                                                @else
                                                    ₹ {{ number_format($loan->emi_amount, 2) }}/month
                                                @endif
                                            </td>
                                            <td>
                                                @if ($loan->repayment_type == 'lumpsum')
                                                    {{ $loan->tenure_months }} months (Lump Sum)
                                                @else
                                                    {{ $loan->tenure_months }} months
                                                @endif
                                            </td>
                                            <td>
                                                <x-ui.status-badge :status="$loan->status" />
                                            </td>
                                            <td>{{ $loan->created_at->format('d M Y') }}</td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button class="action-btn view view-loan" data-id="{{ $loan->id }}"
                                                        title="View Details" data-bs-toggle="tooltip">
                                                        <i class="feather-eye"></i>
                                                    </button>
                                                    @if ($loan->isPending())
                                                        <button class="action-btn edit edit-loan"
                                                            data-id="{{ $loan->id }}"
                                                            data-category="{{ $loan->loan_type_id }}"
                                                            data-amount="{{ $loan->amount }}"
                                                            data-tenure="{{ $loan->tenure_months }}"
                                                            data-purpose="{{ $loan->purpose }}"
                                                            data-description="{{ $loan->description }}"
                                                            data-repayment_type="{{ $loan->repayment_type }}"
                                                            data-lumpsum_tenure="{{ $loan->tenure_months }}"
                                                            title="Edit" data-bs-toggle="tooltip">
                                                            <i class="feather-edit-3"></i>
                                                        </button>
                                                        <button class="action-btn delete cancel-loan"
                                                            data-id="{{ $loan->id }}" title="Cancel" data-bs-toggle="tooltip">
                                                            <i class="feather-x-circle"></i>
                                                        </button>
                                                    @endif

                                                    {{-- PAY NOW BUTTON - Only for Lump Sum Active Loans --}}
                                                    @if ($loan->isLumpsum() && $loan->isActive())
                                                        <button class="action-btn pay pay-lumpsum-btn"
                                                            data-id="{{ $loan->id }}"
                                                            data-number="{{ $loan->loan_number }}"
                                                            data-amount="{{ $loan->lumpsum_amount ?? $loan->amount }}"
                                                            title="Pay Lump Sum Amount" data-bs-toggle="tooltip">
                                                            <i class="feather-credit-card"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <i class="feather-file-text" style="font-size: 48px; color: #cbd5e1;"></i>
                                                <h4 class="mt-3">No Loan Requests Found</h4>
                                                <p class="text-muted">You haven't submitted any loan requests yet</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if ($loans->hasPages())
                        <x-ui.pagination-footer :paginator="$loans" label="loans" />
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- ==================== ADD LOAN MODAL ==================== -->
    <div class="modal fade-scale" id="addLoanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">New Loan Request</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="addLoanForm">
                                @csrf
                                <div id="addFormError" class="alert alert-danger d-none"></div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Loan Category *</label>
                                        <select class="form-control" name="loan_type_id" id="add_loan_type_id" required>
                                            <option value="">Select Category</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}"
                                                    data-interest="{{ $category->default_interest_rate }}"
                                                    data-max-amount="{{ $category->max_amount }}"
                                                    data-max-tenure="{{ $category->max_tenure_months }}"
                                                    data-requires-approval="{{ $category->requires_approval }}">
                                                    {{ $category->name }}
                                                    @if ($category->requires_approval)
                                                        (Requires Approval)
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-danger error-text loan_type_id_error"></small>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Repayment Type *</label>
                                        <select class="form-control" name="repayment_type" id="add_repayment_type"
                                            required>
                                            <option value="emi">Monthly EMI (Salary Deduction)</option>
                                            <option value="lumpsum">Lump Sum (One-time Payment)</option>
                                        </select>
                                        <small class="text-danger error-text repayment_type_error"></small>
                                    </div>

                                    <!-- EMI Fields -->
                                    <div id="emiFields">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-semibold">Loan Amount *</label>
                                                <input type="number" class="form-control" name="amount"
                                                    id="add_amount" step="0.01" min="1000">
                                                <small class="text-danger error-text amount_error"></small>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-semibold">Tenure (Months) *</label>
                                                <input type="number" class="form-control" name="tenure_months"
                                                    id="add_tenure_months" min="1" max="60">
                                                <small class="text-danger error-text tenure_months_error"></small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Lumpsum Fields -->
                                    <div id="lumpsumFields" style="display: none;">
                                        <div class="col-md-12 mb-3">
                                            <div class="alert alert-info">
                                                <i class="feather-info me-2"></i>
                                                <strong>Lump Sum Loan Details:</strong>
                                                <ul class="mt-2 mb-0 fs-10">
                                                    <li>Full amount will be disbursed at once</li>
                                                    <li>No monthly EMI deductions from salary</li>
                                                    <li>Full amount (including interest) due after selected period</li>
                                                    <li>One-time payment at maturity</li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-semibold">Loan Amount *</label>
                                                <input type="number" class="form-control" name="lumpsum_amount"
                                                    id="lumpsum_amount" step="0.01" min="1000">
                                                <small class="text-danger error-text lumpsum_amount_error"></small>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-semibold">Repayment Period (Months) *</label>
                                                <input type="number" class="form-control" name="lumpsum_tenure_months"
                                                    id="lumpsum_tenure_months" min="1" max="24"
                                                    value="12">
                                                <small class="text-muted fs-10 mb-0 pb-0">After how many months will the
                                                    full amount be
                                                    due?</small>
                                                <small class="text-danger error-text lumpsum_tenure_months_error"></small>
                                            </div>
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <div class="emi-preview" id="lumpsumPreview" style="display: none;">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <small class="text-muted">Maturity Date</small>
                                                        <div class="fw-bold" id="maturity_date">-</div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <small class="text-muted">Total Payable</small>
                                                        <div class="fw-bold text-primary" id="total_payable_lumpsum">₹ 0
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row mt-2">
                                                    <div class="col-md-6">
                                                        <small class="text-muted">Principal Amount</small>
                                                        <div id="principal_amount_lumpsum">₹ 0</div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <small class="text-muted">Interest Amount</small>
                                                        <div id="interest_amount_lumpsum">₹ 0</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label fw-semibold">Purpose *</label>
                                        <input type="text" class="form-control" name="purpose" id="add_purpose"
                                            required>
                                        <small class="text-danger error-text purpose_error"></small>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label fw-semibold">Description (Optional)</label>
                                        <textarea class="form-control" name="description" id="add_description" rows="2"></textarea>
                                        <small class="text-danger error-text description_error"></small>
                                    </div>
                                </div>

                                <div class="emi-preview" id="addEmiPreview" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <small class="text-muted">Monthly EMI</small>
                                            <div class="emi-amount" id="addPreviewEmi">₹ 0</div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Total Payable</small>
                                            <div class="fw-bold" id="addPreviewTotal">₹ 0</div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Interest Amount</small>
                                            <div class="fw-bold" id="addPreviewInterest">₹ 0</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="approval-note" id="addApprovalNote" style="display: none;">
                                    <i class="feather-shield me-2"></i>
                                    This loan requires approval before disbursement.
                                </div>

                                <div class="mt-2">
                                    <button type="submit" class="btn btn-primary">Submit Request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== EDIT LOAN MODAL ==================== -->
    <div class="modal fade-scale" id="editLoanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Loan Request</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="editLoanForm">
                                @csrf
                                @method('PUT')
                                <div id="editFormError" class="alert alert-danger d-none"></div>

                                <input type="hidden" name="id" id="edit_loan_id">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Loan Category *</label>
                                        <select class="form-control" name="loan_type_id" id="edit_loan_type_id" required>
                                            <option value="">Select Category</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}"
                                                    data-interest="{{ $category->default_interest_rate }}"
                                                    data-max-amount="{{ $category->max_amount }}"
                                                    data-max-tenure="{{ $category->max_tenure_months }}">
                                                    {{ $category->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-danger error-text edit_loan_type_id_error"></small>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Repayment Type *</label>
                                        <select class="form-control" name="repayment_type" id="edit_repayment_type"
                                            required>
                                            <option value="emi">Monthly EMI (Salary Deduction)</option>
                                            <option value="lumpsum">Lump Sum (One-time Payment)</option>
                                        </select>
                                        <small class="text-danger error-text edit_repayment_type_error"></small>
                                    </div>

                                    <!-- EMI Fields for Edit -->
                                    <div id="editEmiFields">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-semibold">Loan Amount *</label>
                                                <input type="number" class="form-control" name="amount"
                                                    id="edit_amount" step="0.01" min="1000">
                                                <small class="text-danger error-text edit_amount_error"></small>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-semibold">Tenure (Months) *</label>
                                                <input type="number" class="form-control" name="tenure_months"
                                                    id="edit_tenure_months" min="1" max="60">
                                                <small class="text-danger error-text edit_tenure_months_error"></small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Lumpsum Fields for Edit -->
                                    <div id="editLumpsumFields" style="display: none;">
                                        <div class="col-md-12 mb-3">
                                            <div class="alert alert-info">
                                                <i class="feather-info me-2"></i>
                                                <strong>Lump Sum Loan Details:</strong>
                                                <ul class="mt-2 mb-0 fs-10">
                                                    <li>Full amount will be disbursed at once</li>
                                                    <li>No monthly EMI deductions from salary</li>
                                                    <li>Full amount (including interest) due after selected period</li>
                                                    <li>One-time payment at maturity</li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-semibold">Loan Amount *</label>
                                                <input type="number" class="form-control" name="lumpsum_amount"
                                                    id="edit_lumpsum_amount" step="0.01" min="1000">
                                                <small class="text-danger error-text edit_lumpsum_amount_error"></small>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-semibold">Repayment Period (Months) *</label>
                                                <input type="number" class="form-control" name="lumpsum_tenure_months"
                                                    id="edit_lumpsum_tenure_months" min="1" max="24">
                                                <small
                                                    class="text-danger error-text edit_lumpsum_tenure_months_error"></small>
                                            </div>
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <div class="emi-preview" id="editLumpsumPreview" style="display: none;">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <small class="text-muted">Maturity Date</small>
                                                        <div class="fw-bold" id="edit_maturity_date">-</div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <small class="text-muted">Total Payable</small>
                                                        <div class="fw-bold text-primary" id="edit_total_payable_lumpsum">
                                                            ₹ 0</div>
                                                    </div>
                                                </div>
                                                <div class="row mt-2">
                                                    <div class="col-md-6">
                                                        <small class="text-muted">Principal Amount</small>
                                                        <div id="edit_principal_amount_lumpsum">₹ 0</div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <small class="text-muted">Interest Amount</small>
                                                        <div id="edit_interest_amount_lumpsum">₹ 0</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label fw-semibold">Purpose *</label>
                                        <input type="text" class="form-control" name="purpose" id="edit_purpose"
                                            required>
                                        <small class="text-danger error-text edit_purpose_error"></small>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label fw-semibold">Description (Optional)</label>
                                        <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                                        <small class="text-danger error-text edit_description_error"></small>
                                    </div>
                                </div>

                                <div class="emi-preview" id="editEmiPreview" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <small class="text-muted">Monthly EMI</small>
                                            <div class="emi-amount" id="editPreviewEmi">₹ 0</div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Total Payable</small>
                                            <div class="fw-bold" id="editPreviewTotal">₹ 0</div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Interest Amount</small>
                                            <div class="fw-bold" id="editPreviewInterest">₹ 0</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">Update Request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== SHOW LOAN DETAIL MODAL ==================== -->
    <div class="modal fade-scale" id="showLoanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Loan Details</span>
                        <span class="fs-12 fw-normal text-muted" id="show_loan_number"></span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <div id="loanDetailLoading" class="text-center py-5">
                                <i class="feather-loader fa-spin" style="font-size: 32px;"></i>
                                <p class="mt-2">Loading loan details...</p>
                            </div>
                            <div id="loanDetailContent" style="display: none;">
                                <!-- ROW 1: Basic Info -->
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="detail-card">
                                            <div class="detail-label">Loan Number</div>
                                            <div class="detail-value" id="show_loan_number_text">-</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="detail-card">
                                            <div class="detail-label">Loan Amount</div>
                                            <div class="detail-value" id="show_amount">₹ 0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="detail-card">
                                            <div class="detail-label">Repayment Type</div>
                                            <div class="detail-value" id="show_repayment_type">-</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="detail-card">
                                            <div class="detail-label">Tenure (Months)</div>
                                            <div class="detail-value" id="show_tenure">0 months</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ROW 2: EMI/Lumpsum & Financial Details -->
                                <div class="row">
                                    <div class="col-md-3" id="emiInfoCell">
                                        <div class="detail-card">
                                            <div class="detail-label">Monthly EMI</div>
                                            <div class="detail-value" id="show_emi">₹ 0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3" id="lumpsumAmountCell" style="display: none;">
                                        <div class="detail-card">
                                            <div class="detail-label">Lump Sum Amount</div>
                                            <div class="detail-value" id="show_lumpsum_amount">₹ 0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3" id="lumpsumDueDateCell" style="display: none;">
                                        <div class="detail-card">
                                            <div class="detail-label">Due Date</div>
                                            <div class="detail-value" id="show_lumpsum_due_date">-</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="detail-card">
                                            <div class="detail-label">Interest Rate</div>
                                            <div class="detail-value" id="show_interest_rate">0%</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="detail-card">
                                            <div class="detail-label">Status</div>
                                            <div class="detail-value" id="show_status"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ROW 3: Dates & Purpose -->
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="detail-card">
                                            <div class="detail-label">Applied Date</div>
                                            <div class="detail-value" id="show_applied_date">-</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3" id="approvedByCell" style="display: none;">
                                        <div class="detail-card">
                                            <div class="detail-label">Approved By</div>
                                            <div class="detail-value-small" id="show_approved_by">-</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3" id="approvedDateCell" style="display: none;">
                                        <div class="detail-card">
                                            <div class="detail-label">Approved Date</div>
                                            <div class="detail-value-small" id="show_approved_date">-</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3" id="disbursedDateCell" style="display: none;">
                                        <div class="detail-card">
                                            <div class="detail-label">Disbursed Date</div>
                                            <div class="detail-value-small" id="show_disbursed_date">-</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Purpose & Description (Full Width) -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="detail-card">
                                            <div class="detail-label">Purpose</div>
                                            <div class="detail-value-small" id="show_purpose">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row" id="show_description_card" style="display: none;">
                                    <div class="col-md-12">
                                        <div class="detail-card">
                                            <div class="detail-label">Description</div>
                                            <div class="detail-value-small" id="show_description">-</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Repayment Progress -->
                                <div class="row" id="show_progress_card" style="display: none;">
                                    <div class="col-md-12">
                                        <div class="detail-card">
                                            <div class="detail-label">Repayment Progress</div>
                                            <div class="progress-bar-custom mb-2">
                                                <div class="progress-fill" id="show_progress_bar" style="width: 0%">
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="detail-value-small">Paid: <span id="show_paid_amount">₹
                                                        0</span></span>
                                                <span class="detail-value-small">Remaining: <span
                                                        id="show_remaining_amount">₹ 0</span></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pay Now Button in Modal for Lump Sum Loans -->
                                <div id="modalPayButton" style="display: none;" class="mt-3">
                                    <button class="btn btn-success w-100 pay-from-modal" data-id="" data-number=""
                                        data-amount="">
                                        <i class="feather-credit-card me-2"></i> Pay Now
                                    </button>
                                </div>

                                <!-- Repayment Schedule -->
                                <div class="mt-3">
                                    <h6 class="fw-bold mb-2"><i class="feather-calendar me-2"></i>Repayment Schedule</h6>
                                    <div class="table-responsive">
                                        <table class="table schedule-table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Due Date</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Paid Date</th>
                                                </tr>
                                            </thead>
                                            <tbody id="show_schedule_body">
                                                <tr>
                                                    <td colspan="5" class="text-center">Loading schedule...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== LUMP SUM PAYMENT MODAL ==================== -->
    <div class="modal fade" id="lumpsumPaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lump Sum Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    {{-- <div class="text-center mb-3">
                        <i class="feather-credit-card text-primary" style="font-size: 48px;"></i>
                    </div> --}}
                    <p class="text-center mb-3">
                        You are about to make a lump sum payment for loan <strong id="lumpsumLoanNumber"></strong>
                    </p>

                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Loan Amount</small>
                                <div class="fw-bold">₹ <span id="lumpsumLoanAmount"></span></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Due Date</small>
                                <div class="fw-bold" id="lumpsumDueDate"></div>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Interest Amount</small>
                                <div class="fw-bold text-warning" id="lumpsumInterest"></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Total Payable</small>
                                <div class="fw-bold text-success" id="lumpsumTotalPayable"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label for="payment_mode">Payment Mode <span class="text-danger">*</span></label>
                        <select class="form-control" id="payment_mode" required>
                            <option value="">Select Payment Mode</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="cash">Cash</option>
                            <option value="online">Online Payment</option>
                        </select>
                    </div>

                    <div class="form-group mt-3" id="transaction_ref_group" style="display: none;">
                        <label for="transaction_reference">Transaction Reference / Cheque Number</label>
                        <input type="text" class="form-control" id="transaction_reference"
                            placeholder="Enter transaction ID or cheque number">
                    </div>

                    <div class="form-group mt-3">
                        <label for="payment_remarks">Remarks (Optional)</label>
                        <textarea class="form-control" id="payment_remarks" rows="2" placeholder="Any additional remarks..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmLumpsumPayment">
                        <i class="feather-credit-card me-1"></i> Make Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== CANCEL LOAN MODAL ==================== -->
    <div class="modal fade" id="cancelLoanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Loan Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="cancel_loan_id">
                    <div class="form-group">
                        <label>Cancellation Reason *</label>
                        <textarea class="form-control" id="cancellation_reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="confirmCancel">Yes, Cancel</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            let cancelId = null;
            let addSelectedInterest = 0;
            let editSelectedInterest = 0;
            let lumpsumLoanId = null;

            // Auto-submit on filter select change
            $('.filter-select').on('change', function() {
                $('#filterForm').submit();
            });

            // Debounced auto-submit for the loan-number search box
            let searchTimeout;
            $('#filterForm input[name="search"]').on('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    $('#filterForm').submit();
                }, 500);
            });

            // ==================== ADD MODAL FUNCTIONS ====================
            function toggleAddRepaymentType() {
                let repaymentType = $('#add_repayment_type').val();

                if (repaymentType === 'lumpsum') {
                    $('#emiFields').hide();
                    $('#lumpsumFields').show();
                    $('#addEmiPreview').hide();
                    $('#add_amount').prop('required', false);
                    $('#add_tenure_months').prop('required', false);
                    $('#lumpsum_amount').prop('required', true);
                    $('#lumpsum_tenure_months').prop('required', true);
                    calculateLumpsum();
                } else {
                    $('#emiFields').show();
                    $('#lumpsumFields').hide();
                    $('#lumpsumPreview').hide();
                    $('#addEmiPreview').show();
                    $('#add_amount').prop('required', true);
                    $('#add_tenure_months').prop('required', true);
                    $('#lumpsum_amount').prop('required', false);
                    $('#lumpsum_tenure_months').prop('required', false);
                    calculateAddEMI();
                }
            }

            // ==================== EDIT MODAL FUNCTIONS ====================
            function toggleEditRepaymentType() {
                let repaymentType = $('#edit_repayment_type').val();

                if (repaymentType === 'lumpsum') {
                    $('#editEmiFields').hide();
                    $('#editLumpsumFields').show();
                    $('#editEmiPreview').hide();
                    $('#edit_amount').prop('required', false);
                    $('#edit_tenure_months').prop('required', false);
                    $('#edit_lumpsum_amount').prop('required', true);
                    $('#edit_lumpsum_tenure_months').prop('required', true);
                    calculateEditLumpsum();
                } else {
                    $('#editEmiFields').show();
                    $('#editLumpsumFields').hide();
                    $('#editLumpsumPreview').hide();
                    $('#editEmiPreview').show();
                    $('#edit_amount').prop('required', true);
                    $('#edit_tenure_months').prop('required', true);
                    $('#edit_lumpsum_amount').prop('required', false);
                    $('#edit_lumpsum_tenure_months').prop('required', false);
                    calculateEditEMI();
                }
            }

            // ==================== LUMPSUM CALCULATION (ADD) ====================
            function calculateLumpsum() {
                let amount = parseFloat($('#lumpsum_amount').val());
                let tenureMonths = parseInt($('#lumpsum_tenure_months').val());
                let interestRate = addSelectedInterest;

                if (amount && tenureMonths && amount > 0 && tenureMonths > 0) {
                    let interestAmount = (amount * interestRate * tenureMonths) / 1200;
                    let totalPayable = amount + interestAmount;

                    let maturityDate = new Date();
                    maturityDate.setMonth(maturityDate.getMonth() + tenureMonths);
                    let formattedDate = maturityDate.toLocaleDateString('en-IN', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    });

                    $('#maturity_date').text(formattedDate);
                    $('#total_payable_lumpsum').text('₹ ' + totalPayable.toFixed(2));
                    $('#principal_amount_lumpsum').text('₹ ' + amount.toFixed(2));
                    $('#interest_amount_lumpsum').text('₹ ' + interestAmount.toFixed(2));
                    $('#lumpsumPreview').show();
                } else {
                    $('#lumpsumPreview').hide();
                }
            }

            // ==================== LUMPSUM CALCULATION (EDIT) ====================
            function calculateEditLumpsum() {
                let amount = parseFloat($('#edit_lumpsum_amount').val());
                let tenureMonths = parseInt($('#edit_lumpsum_tenure_months').val());
                let interestRate = editSelectedInterest;

                if (amount && tenureMonths && amount > 0 && tenureMonths > 0) {
                    let interestAmount = (amount * interestRate * tenureMonths) / 1200;
                    let totalPayable = amount + interestAmount;

                    let maturityDate = new Date();
                    maturityDate.setMonth(maturityDate.getMonth() + tenureMonths);
                    let formattedDate = maturityDate.toLocaleDateString('en-IN', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    });

                    $('#edit_maturity_date').text(formattedDate);
                    $('#edit_total_payable_lumpsum').text('₹ ' + totalPayable.toFixed(2));
                    $('#edit_principal_amount_lumpsum').text('₹ ' + amount.toFixed(2));
                    $('#edit_interest_amount_lumpsum').text('₹ ' + interestAmount.toFixed(2));
                    $('#editLumpsumPreview').show();
                } else {
                    $('#editLumpsumPreview').hide();
                }
            }

            // ==================== EMI CALCULATION (ADD) ====================
            function calculateAddEMI() {
                let amount = parseFloat($('#add_amount').val());
                let tenure = parseInt($('#add_tenure_months').val());

                if (amount && tenure && amount > 0 && tenure > 0) {
                    let emi;
                    if (addSelectedInterest == 0) {
                        emi = amount / tenure;
                    } else {
                        let monthlyRate = addSelectedInterest / 100 / 12;
                        emi = (amount * monthlyRate * Math.pow(1 + monthlyRate, tenure)) /
                            (Math.pow(1 + monthlyRate, tenure) - 1);
                    }

                    let totalPayable = emi * tenure;
                    let totalInterest = totalPayable - amount;

                    $('#addPreviewEmi').text('₹ ' + emi.toFixed(2));
                    $('#addPreviewTotal').text('₹ ' + totalPayable.toFixed(2));
                    $('#addPreviewInterest').text('₹ ' + totalInterest.toFixed(2));
                    $('#addEmiPreview').show();
                } else {
                    $('#addEmiPreview').hide();
                }
            }

            // ==================== EMI CALCULATION (EDIT) ====================
            function calculateEditEMI() {
                let amount = parseFloat($('#edit_amount').val());
                let tenure = parseInt($('#edit_tenure_months').val());

                if (amount && tenure && amount > 0 && tenure > 0) {
                    let emi;
                    if (editSelectedInterest == 0) {
                        emi = amount / tenure;
                    } else {
                        let monthlyRate = editSelectedInterest / 100 / 12;
                        emi = (amount * monthlyRate * Math.pow(1 + monthlyRate, tenure)) /
                            (Math.pow(1 + monthlyRate, tenure) - 1);
                    }

                    let totalPayable = emi * tenure;
                    let totalInterest = totalPayable - amount;

                    $('#editPreviewEmi').text('₹ ' + emi.toFixed(2));
                    $('#editPreviewTotal').text('₹ ' + totalPayable.toFixed(2));
                    $('#editPreviewInterest').text('₹ ' + totalInterest.toFixed(2));
                    $('#editEmiPreview').show();
                } else {
                    $('#editEmiPreview').hide();
                }
            }

            // ==================== ADD MODAL EVENTS ====================
            $('#add_repayment_type').change(function() {
                toggleAddRepaymentType();
            });

            $('#add_loan_type_id').change(function() {
                let option = $(this).find(':selected');
                addSelectedInterest = parseFloat(option.data('interest')) || 0;
                let maxAmount = option.data('max-amount');
                let maxTenure = option.data('max-tenure');
                let requiresApproval = option.data('requires-approval');

                if (maxAmount) {
                    $('#add_amount').attr('max', maxAmount);
                    $('#lumpsum_amount').attr('max', maxAmount);
                }
                if (maxTenure) {
                    $('#add_tenure_months').attr('max', maxTenure);
                }

                if (requiresApproval) {
                    $('#addApprovalNote').show();
                } else {
                    $('#addApprovalNote').hide();
                }

                calculateAddEMI();
                calculateLumpsum();
            });

            $('#add_amount, #add_tenure_months').on('keyup change', function() {
                calculateAddEMI();
            });

            $('#lumpsum_amount, #lumpsum_tenure_months').on('keyup change', function() {
                calculateLumpsum();
            });

            // ==================== EDIT MODAL EVENTS ====================
            $('#edit_repayment_type').change(function() {
                toggleEditRepaymentType();
            });

            $('#edit_loan_type_id').change(function() {
                let option = $(this).find(':selected');
                editSelectedInterest = parseFloat(option.data('interest')) || 0;
                let maxAmount = option.data('max-amount');
                let maxTenure = option.data('max-tenure');

                if (maxAmount) {
                    $('#edit_amount').attr('max', maxAmount);
                    $('#edit_lumpsum_amount').attr('max', maxAmount);
                }
                if (maxTenure) {
                    $('#edit_tenure_months').attr('max', maxTenure);
                }

                calculateEditEMI();
                calculateEditLumpsum();
            });

            $('#edit_amount, #edit_tenure_months').on('keyup change', function() {
                calculateEditEMI();
            });

            $('#edit_lumpsum_amount, #edit_lumpsum_tenure_months').on('keyup change', function() {
                calculateEditLumpsum();
            });

            // ==================== EDIT LOAN - OPEN MODAL ====================
            $('.edit-loan').click(function() {
                let loanId = $(this).data('id');
                let categoryId = $(this).data('category');
                let amount = $(this).data('amount');
                let tenure = $(this).data('tenure');
                let purpose = $(this).data('purpose');
                let description = $(this).data('description');
                let repaymentType = $(this).data('repayment_type');

                $('#edit_loan_id').val(loanId);
                $('#edit_loan_type_id').val(categoryId);
                $('#edit_repayment_type').val(repaymentType);
                $('#edit_purpose').val(purpose);
                $('#edit_description').val(description);

                if (repaymentType === 'lumpsum') {
                    $('#edit_lumpsum_amount').val(amount);
                    $('#edit_lumpsum_tenure_months').val(tenure);
                    $('#edit_amount').val('');
                    $('#edit_tenure_months').val('');
                } else {
                    $('#edit_amount').val(amount);
                    $('#edit_tenure_months').val(tenure);
                    $('#edit_lumpsum_amount').val('');
                    $('#edit_lumpsum_tenure_months').val('');
                }

                let option = $('#edit_loan_type_id').find(':selected');
                editSelectedInterest = parseFloat(option.data('interest')) || 0;

                $('.error-text').text('');
                $('#editFormError').addClass('d-none');

                toggleEditRepaymentType();
                $('#editLoanModal').modal('show');
            });

            // ==================== EDIT LOAN SUBMIT ====================
            $('#editLoanForm').submit(function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#editFormError').addClass('d-none');

                let loanId = $('#edit_loan_id').val();
                let repaymentType = $('#edit_repayment_type').val();
                let formData = {
                    loan_type_id: $('#edit_loan_type_id').val(),
                    repayment_type: repaymentType,
                    purpose: $('#edit_purpose').val(),
                    description: $('#edit_description').val(),
                    _token: '{{ csrf_token() }}',
                    _method: 'PUT'
                };

                if (repaymentType === 'lumpsum') {
                    formData.amount = $('#edit_lumpsum_amount').val();
                    formData.lumpsum_tenure_months = $('#edit_lumpsum_tenure_months').val();
                    formData.tenure_months = $('#edit_lumpsum_tenure_months').val();
                } else {
                    formData.amount = $('#edit_amount').val();
                    formData.tenure_months = $('#edit_tenure_months').val();
                }

                $.ajax({
                    url: '{{ url('loan/requests') }}/' + loanId,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#editLoanModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.edit_' + key + '_error').text(value[0]);
                            });
                            toastr.error('Please fix the validation errors');
                        } else {
                            $('#editFormError').removeClass('d-none').text(xhr.responseJSON
                                ?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // ==================== ADD LOAN SUBMIT ====================
            $('#addLoanForm').submit(function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#addFormError').addClass('d-none');

                let repaymentType = $('#add_repayment_type').val();
                let formData = {
                    loan_type_id: $('#add_loan_type_id').val(),
                    repayment_type: repaymentType,
                    purpose: $('#add_purpose').val(),
                    description: $('#add_description').val(),
                    _token: '{{ csrf_token() }}'
                };

                if (repaymentType === 'lumpsum') {
                    formData.amount = $('#lumpsum_amount').val();
                    formData.lumpsum_tenure_months = $('#lumpsum_tenure_months').val();
                    formData.tenure_months = $('#lumpsum_tenure_months').val();
                } else {
                    formData.amount = $('#add_amount').val();
                    formData.tenure_months = $('#add_tenure_months').val();
                }

                $.ajax({
                    url: '{{ url('loan/requests') }}',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#addLoanModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.' + key + '_error').text(value[0]);
                            });
                            toastr.error('Please fix the validation errors');
                        } else {
                            $('#addFormError').removeClass('d-none').text(xhr.responseJSON
                                ?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // ==================== SHOW LOAN DETAIL ====================
            $('.view-loan').click(function() {
                let loanId = $(this).data('id');

                $('#loanDetailLoading').show();
                $('#loanDetailContent').hide();
                $('#showLoanModal').modal('show');

                $.ajax({
                    url: '{{ url('loan/requests') }}/' + loanId,
                    type: 'GET',
                    success: function(response) {
                        $('#loanDetailLoading').hide();

                        if (response.success) {
                            let loan = response.data;

                            $('#show_loan_number_text').text(loan.loan_number);
                            $('#show_amount').text('₹ ' + parseFloat(loan.amount)
                                .toLocaleString('en-IN', {
                                    minimumFractionDigits: 2
                                }));
                            $('#show_interest_rate').text(loan.interest_rate + '%');
                            $('#show_applied_date').text(new Date(loan.created_at)
                                .toLocaleDateString('en-IN'));
                            $('#show_purpose').text(loan.purpose || '-');
                            $('#show_repayment_type').text(loan.repayment_type === 'lumpsum' ?
                                'Lump Sum' : 'Monthly EMI');
                            $('#show_tenure').text(loan.tenure_months + ' months');

                            // Handle EMI vs Lumpsum display
                            if (loan.repayment_type === 'lumpsum') {
                                $('#emiInfoCell').hide();
                                $('#lumpsumAmountCell').show();
                                $('#lumpsumDueDateCell').show();
                                $('#show_lumpsum_amount').text('₹ ' + parseFloat(loan
                                    .lumpsum_amount || loan.amount).toLocaleString(
                                    'en-IN', {
                                        minimumFractionDigits: 2
                                    }));
                                $('#show_lumpsum_due_date').text(loan.lumpsum_due_date ?
                                    new Date(loan.lumpsum_due_date).toLocaleDateString(
                                        'en-IN') : '-');

                                // Show Pay Now button in modal for active lumpsum loans
                                if (loan.status === 'active') {
                                    $('#modalPayButton').show();
                                    $('.pay-from-modal').data('id', loan.id);
                                    $('.pay-from-modal').data('number', loan.loan_number);
                                    $('.pay-from-modal').data('amount', loan.lumpsum_amount ||
                                        loan.amount);
                                } else {
                                    $('#modalPayButton').hide();
                                }
                            } else {
                                $('#emiInfoCell').show();
                                $('#lumpsumAmountCell').hide();
                                $('#lumpsumDueDateCell').hide();
                                $('#modalPayButton').hide();
                                $('#show_emi').text('₹ ' + parseFloat(loan.emi_amount)
                                    .toLocaleString('en-IN', {
                                        minimumFractionDigits: 2
                                    }));
                            }

                            // Show approval info if available
                            if (loan.approved_by) {
                                $('#approvedByCell').show();
                                $('#approvedDateCell').show();
                                $('#disbursedDateCell').show();
                                $('#show_approved_by').text(loan.approved_by.name || '-');
                                $('#show_approved_date').text(loan.approved_at ? new Date(loan
                                    .approved_at).toLocaleDateString('en-IN') : '-');
                                $('#show_disbursed_date').text(loan.disbursed_at ? new Date(loan
                                    .disbursed_at).toLocaleDateString('en-IN') : '-');
                            } else {
                                $('#approvedByCell').hide();
                                $('#approvedDateCell').hide();
                                $('#disbursedDateCell').hide();
                            }

                            // Handle description
                            if (loan.description) {
                                $('#show_description').text(loan.description);
                                $('#show_description_card').show();
                            } else {
                                $('#show_description_card').hide();
                            }

                            // Handle status badge
                            let statusClass = `status-badge status-${loan.status}`;
                            $('#show_status').html('<span class="' + statusClass + '">' + loan
                                .status.charAt(0).toUpperCase() + loan.status.slice(1) +
                                '</span>');

                            // Handle repayment schedule
                            if (loan.repayments && loan.repayments.length > 0) {
                                let scheduleHtml = '';
                                let paidAmount = 0;
                                let totalAmount = 0;

                                loan.repayments.forEach(function(repayment) {
                                    let statusClass = repayment.status === 'paid' ?
                                        'repayment-paid' : (repayment.status ===
                                            'overdue' ? 'repayment-overdue' :
                                            'repayment-pending');
                                    let statusText = repayment.status.charAt(0)
                                        .toUpperCase() + repayment.status.slice(1);
                                    let amount = repayment.total_amount || repayment
                                        .emi_amount;

                                    totalAmount += parseFloat(amount);
                                    if (repayment.status === 'paid') {
                                        paidAmount += parseFloat(amount);
                                    }

                                    scheduleHtml += `
                            <tr>
                                <td>${repayment.installment_number}</td>
                                <td>${new Date(repayment.due_date).toLocaleDateString('en-IN')}</td>
                                <td>₹ ${parseFloat(amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                                <td><span class="repayment-status ${statusClass}">${statusText}</span></td>
                                <td>${repayment.paid_date ? new Date(repayment.paid_date).toLocaleDateString('en-IN') : '-'}</td>
                            </tr>
                        `;
                                });

                                let progressPercent = totalAmount > 0 ? (paidAmount /
                                    totalAmount) * 100 : 0;
                                $('#show_progress_bar').css('width', progressPercent + '%');
                                $('#show_paid_amount').text('₹ ' + paidAmount.toLocaleString(
                                    'en-IN', {
                                        minimumFractionDigits: 2
                                    }));
                                $('#show_remaining_amount').text('₹ ' + (totalAmount -
                                    paidAmount).toLocaleString('en-IN', {
                                    minimumFractionDigits: 2
                                }));
                                $('#show_progress_card').show();

                                $('#show_schedule_body').html(scheduleHtml);
                            } else {
                                $('#show_progress_card').hide();
                                $('#show_schedule_body').html(
                                    '<tr><td colspan="5" class="text-center">No repayment schedule available</td></tr>'
                                );
                            }

                            $('#loanDetailContent').show();
                        }
                    },
                    error: function() {
                        $('#loanDetailLoading').hide();
                        $('#loanDetailContent').show();
                        $('#show_schedule_body').html(
                            '<tr><td colspan="5" class="text-center text-danger">Error loading loan details</td></tr>'
                        );
                    }
                });
            });
            // ==================== LUMP SUM PAYMENT - TABLE BUTTON ====================
            $('.pay-lumpsum-btn').click(function() {
                lumpsumLoanId = $(this).data('id');
                let loanNumber = $(this).data('number');
                let amount = $(this).data('amount');

                $('#lumpsumLoanNumber').text(loanNumber);
                $('#lumpsumLoanAmount').text(parseFloat(amount).toLocaleString('en-IN', {
                    minimumFractionDigits: 2
                }));
                $('#lumpsumTotalPayable').text(parseFloat(amount).toLocaleString('en-IN', {
                    minimumFractionDigits: 2
                }));

                // Fetch full loan details for due date and interest
                $.ajax({
                    url: '{{ url('loan/requests') }}/' + lumpsumLoanId,
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            let loan = response.data;
                            let dueDate = loan.lumpsum_due_date ? new Date(loan
                                .lumpsum_due_date).toLocaleDateString('en-IN') : '-';
                            let interest = (loan.lumpsum_amount || loan.amount) - loan.amount;

                            $('#lumpsumDueDate').text(dueDate);
                            $('#lumpsumInterest').text('₹ ' + parseFloat(interest)
                                .toLocaleString('en-IN', {
                                    minimumFractionDigits: 2
                                }));
                        }
                    }
                });

                resetPaymentModal();
                $('#lumpsumPaymentModal').modal('show');
            });

            // ==================== LUMP SUM PAYMENT - MODAL BUTTON ====================
            $('.pay-from-modal').click(function() {
                lumpsumLoanId = $(this).data('id');
                let loanNumber = $(this).data('number');
                let amount = $(this).data('amount');

                $('#lumpsumLoanNumber').text(loanNumber);
                $('#lumpsumLoanAmount').text(parseFloat(amount).toLocaleString('en-IN', {
                    minimumFractionDigits: 2
                }));
                $('#lumpsumTotalPayable').text(parseFloat(amount).toLocaleString('en-IN', {
                    minimumFractionDigits: 2
                }));

                $.ajax({
                    url: '{{ url('loan/requests/umpsum-payment') }}/' + lumpsumLoanId,
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            let loan = response.data;
                            let dueDate = loan.lumpsum_due_date ? new Date(loan
                                .lumpsum_due_date).toLocaleDateString('en-IN') : '-';
                            let interest = (loan.lumpsum_amount || loan.amount) - loan.amount;

                            $('#lumpsumDueDate').text(dueDate);
                            $('#lumpsumInterest').text('₹ ' + parseFloat(interest)
                                .toLocaleString('en-IN', {
                                    minimumFractionDigits: 2
                                }));
                        }
                    }
                });

                $('#showLoanModal').modal('hide');
                resetPaymentModal();
                $('#lumpsumPaymentModal').modal('show');
            });

            function resetPaymentModal() {
                $('#payment_mode').val('');
                $('#transaction_reference').val('');
                $('#payment_remarks').val('');
                $('#transaction_ref_group').hide();
            }

            // Show/hide transaction reference based on payment mode
            $('#payment_mode').change(function() {
                let mode = $(this).val();
                if (mode === 'bank_transfer' || mode === 'cheque') {
                    $('#transaction_ref_group').show();
                } else {
                    $('#transaction_ref_group').hide();
                    $('#transaction_reference').val('');
                }
            });

            // Process Lump Sum Payment
            $('#confirmLumpsumPayment').click(function() {
                let paymentMode = $('#payment_mode').val();
                if (!paymentMode) {
                    toastr.error('Please select payment mode');
                    return;
                }

                if (!lumpsumLoanId) {
                    toastr.error('Loan information not found');
                    return;
                }

                $.ajax({
                    url: '{{ url('loan/requests/lumpsum-payment') }}/' + lumpsumLoanId,
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        payment_mode: paymentMode,
                        transaction_reference: $('#transaction_reference').val(),
                        remarks: $('#payment_remarks').val()
                    },
                    beforeSend: function() {
                        $('#confirmLumpsumPayment').prop('disabled', true).html(
                            '<i class="feather-loader me-1"></i> Processing...');
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#lumpsumPaymentModal').modal('hide');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = xhr.responseJSON?.message ||
                            'Payment failed. Please try again.';
                        toastr.error(errorMsg);
                    },
                    complete: function() {
                        $('#confirmLumpsumPayment').prop('disabled', false).html(
                            '<i class="feather-credit-card me-1"></i> Make Payment');
                    }
                });
            });

            // ==================== CANCEL LOAN ====================
            $('.cancel-loan').click(function() {
                cancelId = $(this).data('id');
                $('#cancellation_reason').val('');
                $('#cancelLoanModal').modal('show');
            });

            $('#confirmCancel').click(function() {
                let reason = $('#cancellation_reason').val();
                if (!reason) {
                    toastr.error('Please provide cancellation reason');
                    return;
                }

                $.ajax({
                    url: '{{ url('loan/requests') }}/' + cancelId + '/cancel',
                    type: 'POST',
                    data: {
                        cancellation_reason: reason,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#cancelLoanModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                    }
                });
            });

            // Initialize
            toggleAddRepaymentType();
        });
    </script>
@endsection
