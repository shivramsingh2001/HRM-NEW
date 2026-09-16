{{-- resources/views/client/loan/category/index.blade.php --}}

@extends('client.layout.master')

@section('style')
    <style>
        /* .stats-grid/.stats-card/.stats-icon-wrapper/.stats-content/.stats-amount-main/.stats-label
           are centralized in client.layout.head (single blue-only theme) — no local copy. */

        .stats-count {
            display: flex;
            align-items: baseline;
            gap: 4px;
        }

        .count-number {
            font-size: 11px;
            font-weight: 600;
        }

        .count-text {
            font-size: 11px;
            color: #64748b;
            font-weight: 400;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ==================== TABLE STYLES ==================== */
        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #475569;
            border-bottom-width: 1px;
            padding: 12px 16px;
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
            background: #d1fae5 !important;
            color: #065f46;
        }

        .badge.bg-danger {
            background: #fee2e2 !important;
            color: #991b1b;
        }

        .badge.bg-warning {
            background: #fef3c7 !important;
            color: #92400e;
        }

        /* Amount styling */
        .amount-text {
            font-weight: 600;
            color: #1e293b;
        }

        /* Interest rate styling */
        .rate-text {
            font-weight: 500;
            color: #4f46e5;
        }

        /* Sort order badge */
        .sort-badge {
            background: #f1f5f9;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 10px;
            font-weight: 600;
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Code styling */
        .code-badge {
            font-family: 'Courier New', monospace;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #4f46e5;
            display: inline-block;
        }

        /* Action Buttons */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #64748b;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            margin: 0 2px;
        }

        .action-btn:hover {
            background: white;
            transform: translateY(-2px);
        }

        .action-btn.edit:hover {
            color: #10b981;
            border-color: #10b981;
        }

        .action-btn.delete:hover {
            color: #ef4444;
            border-color: #ef4444;
        }

        .action-btn i {
            font-size: 14px;
        }

        /* Empty State */
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

        /* Modal Styles */
        .modal-header .close-icon {
            cursor: pointer;
        }

        .error-text {
            font-size: 11px;
            margin-top: 4px;
            display: block;
        }

        /* EMI Calculator Result Box */
        .result-box {
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            margin-top: 16px;
            border: 1px solid #e2e8f0;
        }

        .result-box h5 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #1e293b;
        }

        .result-box p {
            margin-bottom: 8px;
            font-size: 13px;
        }

        .result-box p strong {
            color: #4f46e5;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .filter-row {
                gap: 8px;
            }

            .filter-item {
                flex: 1 1 calc(50% - 8px);
                min-width: 120px;
            }
        }

        @media (max-width: 768px) {
            .filter-wrapper {
                padding: 12px;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-item {
                width: 100%;
            }

            .filter-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .apply-btn,
            .reset-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* ==================== COMPACT MODAL (Add / Edit Category) — core chrome
           (max-width/header/body/card/row/label/btn) is centralized in
           theme-custom.css; only this page's own font-density extras stay
           here, matching the announcement page's pattern. ==================== */
        .compact-modal .modal-header .fs-18 { font-size: 13px !important; }
        .compact-modal .form-group { margin-bottom: 0; }
        .compact-modal .form-control,
        .compact-modal .form-check-label { font-size: 11.5px; }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Loan Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Loan Categories</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex d-md-none">
                    <a href="#" class="page-header-right-close-toggle">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back</span>
                    </a>
                </div>
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
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
                    <a href="#" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                        data-bs-target="#addCategoryModal">
                        <i class="feather-plus me-2"></i>
                        <span>Add Category</span>
                    </a>
                </div>
            </div>
            <div class="d-md-none d-flex align-items-center">
                <a href="#" class="page-header-right-open-toggle">
                    <i class="feather-align-right fs-20"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stats-card total-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-tag"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $categories->count() ?? 0 }}</div>
                    <div class="stats-label">Total Categories</div>
                </div>
            </div>

            <div class="stats-card active-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-check-circle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $categories->where('status', 1)->count() ?? 0 }}</div>
                    <div class="stats-label">Active</div>
                </div>
            </div>

            <div class="stats-card inactive-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-x-circle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $categories->where('status', 0)->count() ?? 0 }}</div>
                    <div class="stats-label">Inactive</div>
                </div>
            </div>

            <div class="stats-card total-loans-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-dollar-sign"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">₹ {{ number_format($totalLoanAmount ?? 0, 0) }}</div>
                    <div class="stats-label">Total Disbursed</div>
                </div>
            </div>
        </div>

        <!-- Categories Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Loan Categories</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info">
                                <i class="feather-list me-1"></i>Total: {{ $categories->count() }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table" id="categoriesTable">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Name</th>
                                        <th>Code</th>
                                        <th>Max Amount</th>
                                        <th>Interest Rate</th>
                                        <th>Max Tenure</th>
                                        <th>Total Loans</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($categories as $index => $category)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                <strong>{{ $category->name }}</strong>
                                                @if ($category->requires_approval)
                                                    <i class="feather-shield text-info ms-1" title="Requires Approval"></i>
                                                @endif
                                            </td>
                                            <td><span class="code-badge">{{ $category->code }}</span></td>
                                            <td>
                                                @if ($category->max_amount)
                                                    <span class="amount-text">₹
                                                        {{ number_format($category->max_amount, 2) }}</span>
                                                @else
                                                    <span class="text-muted">No Limit</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($category->default_interest_rate > 0)
                                                    <span class="rate-text">{{ $category->default_interest_rate }}%</span>
                                                @else
                                                    <span class="text-muted">0% (Interest Free)</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($category->max_tenure_months)
                                                    <span class="sort-badge">
                                                        <i class="feather-calendar"></i>
                                                        {{ $category->max_tenure_months }} months
                                                    </span>
                                                @else
                                                    <span class="text-muted">No Limit</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-purple">
                                                    <i class="feather-file-text"></i>
                                                    {{ $category->loans_count ?? 0 }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($category->status)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <a href="#" class="action-btn edit edit-category"
                                                        data-id="{{ $category->id }}" data-name="{{ $category->name }}"
                                                        data-code="{{ $category->code }}"
                                                        data-max_amount="{{ $category->max_amount }}"
                                                        data-default_interest_rate="{{ $category->default_interest_rate }}"
                                                        data-max_tenure_months="{{ $category->max_tenure_months }}"
                                                        data-requires_approval="{{ $category->requires_approval }}"
                                                        data-sort_order="{{ $category->sort_order }}"
                                                        data-status="{{ $category->status }}" title="Edit Category">
                                                        <i class="feather-edit-3"></i>
                                                    </a>
                                                    <a href="#" class="action-btn"
                                                        onclick="openEmiCalculator({{ $category->id }}, '{{ $category->name }}')"
                                                        title="Calculate EMI">
                                                        <i class="fa fa-calculator"></i>
                                                    </a>
                                                    <a href="#" class="action-btn delete delete-category"
                                                        data-id="{{ $category->id }}" data-name="{{ $category->name }}"
                                                        title="Delete Category">
                                                        <i class="feather-trash-2"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-tag"></i>
                                                    <h4>No Loan Categories Found</h4>
                                                    <p class="text-muted">Create your first loan category to get started
                                                    </p>

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
    <!-- Add Category Modal -->
    <div class="modal fade-scale" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModal"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add New Category</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="addCategoryForm">
                                @csrf
                                <div id="addFormError" class="alert alert-danger d-none"></div>

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="name">Category Name *</label>
                                            <input type="text" class="form-control" name="name" id="name"
                                                placeholder="Enter the Name" required>
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="code">Category Code</label>
                                            <input type="text" class="form-control" name="code" id="code"
                                                placeholder="Optional, e.g. PERS">
                                            <small class="text-danger error-text code_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="max_amount">Maximum Amount</label>
                                            <input type="number" class="form-control" name="max_amount" id="max_amount"
                                                step="0.01" placeholder="Leave empty for no limit">
                                            <small class="text-danger error-text max_amount_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="default_interest_rate">Interest Rate
                                                (%)</label>
                                            <input type="number" class="form-control" name="default_interest_rate"
                                                id="default_interest_rate" step="0.01" value="0">
                                            <small class="text-danger error-text default_interest_rate_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="max_tenure_months">Maximum Tenure
                                                (Months)</label>
                                            <input type="number" class="form-control" name="max_tenure_months"
                                                id="max_tenure_months" placeholder="Leave empty for no limit">
                                            <small class="text-danger error-text max_tenure_months_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="sort_order">Sort Order</label>
                                            <input type="number" class="form-control" name="sort_order"
                                                id="sort_order" min="0" placeholder="0">
                                            <small class="text-danger error-text sort_order_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="requires_approval"
                                                    name="requires_approval" value="1" checked>
                                                <label class="form-check-label" for="requires_approval">
                                                    Requires Approval
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="status"
                                                    name="status" value="1" checked>
                                                <label class="form-check-label" for="status">
                                                    Active
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">Save Category</button>
                                    </div>
                                    <div class="col-6">
                                        <a href="#" class="btn btn-modal-cancel float-end"
                                            data-bs-dismiss="modal">
                                            Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade-scale" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModal"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Category</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="editCategoryForm">
                                @csrf
                                <div id="editFormError" class="alert alert-danger d-none"></div>

                                <input type="hidden" name="id" id="edit_id">

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_name">Category Name *</label>
                                            <input type="text" class="form-control" name="name" id="edit_name"
                                                required>
                                            <small class="text-danger error-text edit_name_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_code">Category Code *</label>
                                            <input type="text" class="form-control" name="code" id="edit_code"
                                                required>
                                            <small class="text-danger error-text edit_code_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_max_amount">Maximum Amount</label>
                                            <input type="number" class="form-control" name="max_amount"
                                                id="edit_max_amount" step="0.01">
                                            <small class="text-danger error-text edit_max_amount_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_default_interest_rate">Interest Rate
                                                (%)</label>
                                            <input type="number" class="form-control" name="default_interest_rate"
                                                id="edit_default_interest_rate" step="0.01">
                                            <small class="text-danger error-text edit_default_interest_rate_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_max_tenure_months">Maximum Tenure
                                                (Months)</label>
                                            <input type="number" class="form-control" name="max_tenure_months"
                                                id="edit_max_tenure_months">
                                            <small class="text-danger error-text edit_max_tenure_months_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_sort_order">Sort Order</label>
                                            <input type="number" class="form-control" name="sort_order"
                                                id="edit_sort_order" min="0">
                                            <small class="text-danger error-text edit_sort_order_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox"
                                                    id="edit_requires_approval" name="requires_approval" value="1">
                                                <label class="form-check-label" for="edit_requires_approval">
                                                    Requires Approval
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="edit_status"
                                                    name="status" value="1">
                                                <label class="form-check-label" for="edit_status">
                                                    Active
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">Update Category</button>
                                    </div>
                                    <div class="col-6">
                                        <a href="#" class="btn btn-modal-cancel float-end"
                                            data-bs-dismiss="modal">
                                            Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- EMI Calculator Modal -->
    <div class="modal fade-scale" id="emiCalculatorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">EMI Calculator</span>
                        <span class="fs-12 fw-normal text-muted" id="calc_category_name_display"></span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <input type="hidden" id="calc_category_id">

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <div class="form-group">
                                        <label class="fw-semibold" for="calc_amount">Loan Amount *</label>
                                        <input type="number" class="form-control" id="calc_amount"
                                            placeholder="Enter amount" step="0.01">
                                    </div>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <div class="form-group">
                                        <label class="fw-semibold" for="calc_tenure">Tenure (Months) *</label>
                                        <input type="number" class="form-control" id="calc_tenure"
                                            placeholder="Enter months">
                                    </div>
                                </div>

                                <div class="result-box" id="calcResult" style="display: none;">
                                    <h5><i class="feather-calculator me-2"></i>Calculation Result:</h5>
                                    <p><strong>Monthly EMI:</strong> <span id="emi_result"
                                            class="text-primary fw-bold"></span></p>
                                    <p><strong>Total Payable:</strong> <span id="total_payable_result"></span></p>
                                    <p><strong>Total Interest:</strong> <span id="total_interest_result"></span></p>
                                </div>

                                <div class="col-12 mt-3">
                                    <button class="btn btn-primary w-100" id="calculateEmiBtn">
                                        <i class="feather-calculator me-2"></i>Calculate EMI
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="feather-alert-triangle text-warning" style="font-size: 48px;"></i>
                    <p class="mt-3">Are you sure you want to delete <strong id="delete_category_name"></strong>?</p>
                    <p class="text-muted small">This action cannot be undone if no loans are associated.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="confirmDelete">Yes, Delete</button>
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

        let deleteCategoryId = null;

        $(document).ready(function() {
            // Add Category Form Submission
            $('#addCategoryForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');

                let formData = {
                    name: $('#name').val(),
                    code: $('#code').val(),
                    max_amount: $('#max_amount').val(),
                    default_interest_rate: $('#default_interest_rate').val(),
                    max_tenure_months: $('#max_tenure_months').val(),
                    sort_order: $('#sort_order').val(),
                    requires_approval: $('#requires_approval').is(':checked') ? 1 : 0,
                    status: $('#status').is(':checked') ? 1 : 0,
                    _token: '{{ csrf_token() }}'
                };

                $.ajax({
                    url: "{{ route('loan.categories.store') }}",
                    type: "POST",
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#addCategoryModal').modal('hide');
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
                            $('#addFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });
            });

            // Edit Category - Open Modal
            $(document).on('click', '.edit-category', function(e) {
                e.preventDefault();

                $('#edit_id').val($(this).data('id'));
                $('#edit_name').val($(this).data('name'));
                $('#edit_code').val($(this).data('code'));
                $('#edit_max_amount').val($(this).data('max_amount'));
                $('#edit_default_interest_rate').val($(this).data('default_interest_rate'));
                $('#edit_max_tenure_months').val($(this).data('max_tenure_months'));
                $('#edit_sort_order').val($(this).data('sort_order'));
                $('#edit_requires_approval').prop('checked', $(this).data('requires_approval') == 1);
                $('#edit_status').prop('checked', $(this).data('status') == 1);

                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                $('#editCategoryModal').modal('show');
            });

            // Edit Category Form Submission
            $('#editCategoryForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();
                let formData = {
                    name: $('#edit_name').val(),
                    code: $('#edit_code').val(),
                    max_amount: $('#edit_max_amount').val(),
                    default_interest_rate: $('#edit_default_interest_rate').val(),
                    max_tenure_months: $('#edit_max_tenure_months').val(),
                    sort_order: $('#edit_sort_order').val(),
                    requires_approval: $('#edit_requires_approval').is(':checked') ? 1 : 0,
                    status: $('#edit_status').is(':checked') ? 1 : 0,
                    _token: '{{ csrf_token() }}',
                    _method: 'PUT'
                };

                $.ajax({
                    url: "{{ route('loan.categories.update', '') }}/" + id,
                    type: "POST",
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#editCategoryModal').modal('hide');
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
                            $('#editFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });
            });

            // Delete Category
            $(document).on('click', '.delete-category', function(e) {
                e.preventDefault();
                deleteCategoryId = $(this).data('id');
                $('#delete_category_name').text($(this).data('name'));
                $('#deleteCategoryModal').modal('show');
            });

            $('#confirmDelete').on('click', function() {
                if (!deleteCategoryId) return;

                $.ajax({
                    url: "{{ route('loan.categories.destroy', '') }}/" + deleteCategoryId,
                    type: "DELETE",
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#deleteCategoryModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            toastr.error(response.message);
                            $('#deleteCategoryModal').modal('hide');
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong.');
                        $('#deleteCategoryModal').modal('hide');
                    }
                });
            });

            // Clear form when modal is closed
            $('#addCategoryModal, #editCategoryModal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
                $('.error-text').text('');
                $('#addFormError, #editFormError').addClass('d-none').text('');
                $('#requires_approval, #status').prop('checked', true);
            });
        });

        // EMI Calculator
        function openEmiCalculator(categoryId, categoryName) {
            $('#calc_category_id').val(categoryId);
            $('#calc_category_name_display').text(categoryName);
            $('#calc_amount').val('');
            $('#calc_tenure').val('');
            $('#calcResult').hide();
            $('#emiCalculatorModal').modal('show');
        }

        $('#calculateEmiBtn').on('click', function() {
            let categoryId = $('#calc_category_id').val();
            let amount = $('#calc_amount').val();
            let tenure = $('#calc_tenure').val();

            if (!amount || !tenure) {
                toastr.error('Please enter both amount and tenure');
                return;
            }

            $.ajax({
                url: "{{ route('loan.categories.calculate-emi', '') }}/" + categoryId,
                type: "POST",
                data: {
                    amount: amount,
                    tenure_months: tenure,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        $('#emi_result').text('₹ ' + response.data.emi_amount);
                        $('#total_payable_result').text('₹ ' + response.data.total_payable);
                        $('#total_interest_result').text('₹ ' + response.data.total_interest);
                        $('#calcResult').show();
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Calculation failed');
                }
            });
        });

        // Export to CSV
        function exportToCSV() {
            let table = document.getElementById('categoriesTable');
            if (!table) {
                toastr.error('Table not found');
                return;
            }

            let rows = table.querySelectorAll('tr');
            let csv = [];

            // Add headers
            let headers = [];
            let headerCells = rows[0].querySelectorAll('th');
            for (let i = 0; i < headerCells.length - 1; i++) {
                let headerText = headerCells[i].innerText.trim();
                if (headerText && headerText !== 'Actions') {
                    headers.push('"' + headerText.replace(/"/g, '""') + '"');
                }
            }
            csv.push(headers.join(','));

            // Add data rows
            for (let i = 1; i < rows.length; i++) {
                let rowData = [];
                let cols = rows[i].querySelectorAll('td');

                for (let j = 0; j < cols.length - 1; j++) {
                    let cellText = cols[j].innerText.replace(/"/g, '""').trim();
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
            downloadLink.download = 'loan_categories_' + new Date().getTime() + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();

            toastr.success('CSV exported successfully');
        }
    </script>
@endsection
