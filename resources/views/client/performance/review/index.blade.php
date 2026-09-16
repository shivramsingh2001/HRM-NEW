@extends('client.layout.master')

@section('style')
    <style>
        /* .stats-grid/.stats-card/.stats-icon-wrapper/.stats-content/.stats-amount-main/.stats-label
           are centralized in client.layout.head (single blue-only theme) — no local copy. */

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

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .filter-item {
            flex: 0 0 auto;
            min-width: 140px;
        }

        .filter-select {
            width: 100%;
            height: 36px;
            padding: 6px 28px 6px 10px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            cursor: pointer;
        }

        .apply-btn {
            height: 36px;
            padding: 0 16px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .reset-btn {
            height: 36px;
            padding: 0 12px;
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        /* ==================== TABLE STYLES ==================== */
        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            color: #475569;
            padding: 12px 16px;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            font-size: 13px;
            padding: 12px 16px;
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

        .badge.bg-warning {
            background: #fef3c7 !important;
            color: #92400e;
        }

        .badge.bg-info {
            background: #e0f2fe !important;
            color: #0369a1;
        }

        .badge.bg-secondary {
            background: #f1f5f9 !important;
            color: #475569;
        }

        /* ==================== RATING STARS ==================== */
        .rating-stars {
            display: inline-flex;
            gap: 2px;
        }

        .rating-stars i {
            font-size: 14px;
            color: #fbbf24;
        }

        .rating-stars i.empty {
            color: #e2e8f0;
        }

        /* ==================== ACTION BUTTONS ==================== */
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
        }

        .action-btn:hover {
            background: white;
            transform: translateY(-2px);
        }

        .action-btn.view:hover {
            color: #4f46e5;
            border-color: #4f46e5;
        }

        .action-btn.edit:hover {
            color: #10b981;
            border-color: #10b981;
        }

        .action-btn.delete:hover {
            color: #ef4444;
            border-color: #ef4444;
        }

        /* ==================== MODAL STYLES ==================== */
        .rating-input {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .rating-input .stars {
            display: flex;
            gap: 4px;
            cursor: pointer;
        }

        .rating-input .stars i {
            font-size: 24px;
            color: #cbd5e1;
            transition: all 0.2s;
        }

        .rating-input .stars i.active,
        .rating-input .stars i:hover {
            color: #fbbf24;
        }

        .modal-header .close-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fee2e2;
            cursor: pointer;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .kpi-preview {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 16px;
        }

        .kpi-preview-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
        }

        .kpi-preview-item:last-child {
            border-bottom: none;
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Performance Reviews</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('performance.team') }}">Performance</a></li>
                <li class="breadcrumb-item active">Reviews</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="dropdown">
                <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown">
                    <i class="feather-download"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <a href="{{ route('performance.reviews.export', request()->query()) }}" class="dropdown-item">
                        <i class="bi bi-filetype-csv me-3"></i> Export CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stats-card total-card">
                <div class="stats-icon-wrapper"><i class="feather-users"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $stats['total'] ?? 0 }}</div>
                    <div class="stats-label">Total Employees</div>
                </div>
            </div>
            <div class="stats-card completed-card">
                <div class="stats-icon-wrapper"><i class="feather-check-circle"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $stats['completed'] ?? 0 }}</div>
                    <div class="stats-label">Completed</div>
                </div>
            </div>
            <div class="stats-card pending-card">
                <div class="stats-icon-wrapper"><i class="feather-clock"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $stats['pending'] ?? 0 }}</div>
                    <div class="stats-label">Pending</div>
                </div>
            </div>
            <div class="stats-card draft-card">
                <div class="stats-icon-wrapper"><i class="feather-file-text"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $stats['draft'] ?? 0 }}</div>
                    <div class="stats-label">Draft</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title"><i class="feather-filter"></i> Filter Reviews</div>
            </div>
            <form action="{{ route('performance.reviews.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <select class="filter-select" name="month">
                            @php
                                $months = [];
                                for ($i = 0; $i <= 5; $i++) {
                                    $m = now()->subMonths($i);
                                    $months[$m->format('Y-m')] = $m->format('F Y');
                                }
                            @endphp
                            @foreach ($months as $val => $label)
                                <option value="{{ $val }}" {{ ($month ?? '') == $val ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select class="filter-select" name="department_id">
                            <option value="">All Departments</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}"
                                    {{ ($departmentId ?? '') == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">

                        <div class="custom-employee-dropdown">
                            <button class="btn btn-light w-100 d-flex align-items-center justify-content-between"
                                type="button" id="employeeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-flex align-items-center gap-2" id="selectedEmployeeDisplay">
                                    @if (request('employee_id') && ($selectedEmployee = $employees->firstWhere('id', request('employee_id'))))
                                        @php
                                            $selectedInitials = strtoupper(substr($selectedEmployee->name, 0, 2));
                                        @endphp
                                        <span class="employee-initials-sm">{{ $selectedInitials }}</span>
                                        <span class="employee-name">{{ $selectedEmployee->name }}</span>
                                    @else
                                        <span class="text-muted">All Employees</span>
                                    @endif
                                </span>
                                <i class="feather-chevron-down text-muted"></i>
                            </button>

                            <ul class="dropdown-menu w-80 p-2" aria-labelledby="employeeDropdown">
                                <li>
                                    <a class="dropdown-item rounded {{ !request('employee_id') ? 'active' : '' }}"
                                        href="{{ route('performance.reviews.index', array_merge(request()->except(['employee_id', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($employees as $user)
                                    @php
                                        $initials = strtoupper(substr($user->name, 0, 2));
                                    @endphp
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('employee_id') == $user->id ? 'active' : '' }}"
                                            href="{{ route('performance.reviews.index', array_merge(request()->except(['page']), ['employee_id' => $user->id])) }}">
                                            <span class="employee-initials">{{ $initials }}</span>
                                            <div class="d-flex flex-column">
                                                <span>{{ $user->name }} (<small
                                                        class="text-muted">{{ $user->employee_id }})</small></span>
                                                <small class="text-muted">{{ $user->email }}</small>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="filter-item">
                        <select class="filter-select" name="status">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="submitted" {{ request('status') == 'submitted' ? 'selected' : '' }}>Submitted
                            </option>
                            <option value="acknowledged" {{ request('status') == 'acknowledged' ? 'selected' : '' }}>
                                Acknowledged</option>
                        </select>
                    </div>
                    {{-- <div class="filter-item">
                        <button type="submit" class="apply-btn"><i class="feather-search"></i> Apply</button>
                    </div> --}}
                    <div class="filter-item">
                        <a href="{{ route('performance.reviews.index') }}" class="reset-btn"><i
                                class="feather-refresh-cw"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Reviews Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Employee Performance Reviews</h5>
                <span class="badge bg-info">Month: {{ date('F Y', strtotime($month . '-01')) }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Sr No.</th>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th>Department</th>
                                <th>Overall Score</th>
                                <th>Grade</th>
                                <th>Manager Rating</th>
                                <th>Status</th>
                                <th>Reviewed By</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reviews as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="employee-avatar">
                                                {{ strtoupper(substr($item['employee']->name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="fw-semibold">{{ $item['employee']->name }} <small
                                                        class="text-muted">( {{ $item['employee']->employee_id ?? 'N/A' }}
                                                        )</small></div>
                                                <small class="text-muted">{{ $item['employee']->email ?? 'N/A' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $item['employee']->jobDetails?->Designation?->name ?? 'N/A' }}</td>
                                    <td>{{ $item['employee']->jobDetails?->Department?->name ?? 'N/A' }}</td>
                                    <td>
                                        <span class="fw-bold">{{ $item['kpi_score']->overall_score ?? 'N/A' }}%</span>
                                    </td>
                                    <td>
                                        <span
                                            class="badge bg-{{ $item['kpi_score'] && $item['kpi_score']->grade == 'A' ? 'success' : ($item['kpi_score'] && $item['kpi_score']->grade == 'C' ? 'warning' : 'info') }}">
                                            {{ $item['kpi_score']->grade ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($item['review'] && $item['review']->overall_rating)
                                            <div class="rating-stars">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    <i
                                                        class="feather-star{{ $i <= $item['review']->overall_rating ? '' : ' empty' }}"></i>
                                                @endfor
                                            </div>
                                            <div class="small">{{ $item['review']->overall_rating }}/5</div>
                                        @else
                                            <span class="text-muted">Not rated</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusBadge =
                                                [
                                                    'pending' => 'warning',
                                                    'draft' => 'secondary',
                                                    'submitted' => 'info',
                                                    'acknowledged' => 'success',
                                                ][$item['status']] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-{{ $statusBadge }}">
                                            {{ ucfirst($item['status']) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($item['review'] && $item['review']->reviewer)
                                            {{ $item['review']->reviewer->name }}
                                            <br><small
                                                class="text-muted">{{ $item['review']->submitted_at?->format('d M Y') ?? 'N/A' }}</small>
                                        @else
                                            <span class="text-muted">Not reviewed</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            @if ($item['can_review'])
                                                <a href="#" class="action-btn edit review-employee"
                                                    data-id="{{ $item['employee']->id }}"
                                                    data-name="{{ $item['employee']->name }}"
                                                    data-review="{{ json_encode($item['review']) }}"
                                                    data-kpi="{{ json_encode($item['kpi_score']) }}"
                                                    title="Create/Edit Review">
                                                    <i class="feather-edit-3"></i>
                                                </a>
                                            @else
                                                <a href="#" class="action-btn view view-review"
                                                    data-review-id="{{ $item['review']->id ?? 0 }}" title="View Review">
                                                    <i class="feather-eye"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="empty-state">
                                            <i class="feather-users fs-48 text-muted"></i>
                                            <h5 class="mt-3">No employees found</h5>
                                            <p class="text-muted">No team members available for review</p>
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
@endsection

@section('create-modal')
    <!-- Create/Edit Review Modal -->
    <div class="modal fade-scale" id="reviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1" id="modalTitle">Performance Review</span>
                        <small id="employeeName" class="text-muted"></small>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <div id="formError" class="alert alert-danger d-none"></div>

                            <form id="reviewForm" method="POST">
                                @csrf
                                <input type="hidden" name="user_id" id="user_id">
                                <input type="hidden" name="month" value="{{ $month }}">
                                <input type="hidden" name="status" id="formStatus" value="submitted">

                                <!-- KPI Preview -->
                                <div class="kpi-preview" id="kpiPreview" style="display: none;">
                                    <div class="fw-semibold mb-2">Current Performance Metrics</div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="kpi-preview-item">
                                                <span>Attendance:</span>
                                                <strong id="previewAttendance">-</strong>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="kpi-preview-item">
                                                <span>Task Completion:</span>
                                                <strong id="previewTask">-</strong>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="kpi-preview-item">
                                                <span>Deadline Met:</span>
                                                <strong id="previewDeadline">-</strong>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="kpi-preview-item">
                                                <span>Regularization:</span>
                                                <strong id="previewRegularization">-</strong>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="kpi-preview-item">
                                                <span>Overall Score:</span>
                                                <strong id="previewOverall">-</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Rating Section -->
                                <div class="mb-4">
                                    <label class="fw-semibold mb-2">Overall Rating *</label>
                                    <div class="rating-input">
                                        <div class="stars" id="ratingStars">
                                            <i class="feather-star" data-value="1"></i>
                                            <i class="feather-star" data-value="2"></i>
                                            <i class="feather-star" data-value="3"></i>
                                            <i class="feather-star" data-value="4"></i>
                                            <i class="feather-star" data-value="5"></i>
                                        </div>
                                        <input type="hidden" name="overall_rating" id="overall_rating" required>
                                        <span class="text-muted small" id="ratingText">Click to rate</span>
                                    </div>
                                    <small class="text-danger error-text overall_rating_error"></small>
                                </div>

                                <!-- Strengths -->
                                <div class="mb-3">
                                    <label class="fw-semibold">Strengths</label>
                                    <textarea class="form-control" name="strengths" id="strengths" rows="3"
                                        placeholder="What are the employee's key strengths?"></textarea>
                                </div>

                                <!-- Areas for Improvement -->
                                <div class="mb-3">
                                    <label class="fw-semibold">Areas for Improvement</label>
                                    <textarea class="form-control" name="areas_for_improvement" id="areas_for_improvement" rows="3"
                                        placeholder="What areas need improvement?"></textarea>
                                </div>

                                <!-- Achievements -->
                                <div class="mb-3">
                                    <label class="fw-semibold">Key Achievements</label>
                                    <textarea class="form-control" name="achievements" id="achievements" rows="3"
                                        placeholder="What were the key achievements this month?"></textarea>
                                </div>

                                <!-- Goals Next Month -->
                                <div class="mb-3">
                                    <label class="fw-semibold">Goals for Next Month</label>
                                    <textarea class="form-control" name="goals_next_month" id="goals_next_month" rows="3"
                                        placeholder="What should be the focus for next month?"></textarea>
                                </div>

                                <!-- Additional Feedback -->
                                <div class="mb-3">
                                    <label class="fw-semibold">Additional Feedback</label>
                                    <textarea class="form-control" name="additional_feedback" id="additional_feedback" rows="3"
                                        placeholder="Any additional comments or feedback..."></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-6">
                                        <button type="button" class="btn btn-secondary w-100" id="saveDraftBtn">
                                            <i class="feather-save"></i> Save as Draft
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="feather-check-circle"></i> Submit
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Review Modal -->
    <div class="modal fade-scale" id="viewReviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Review Details</span>
                        <small id="viewEmployeeName" class="text-muted"></small>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="kpi-preview-item"><span>Rating:</span> <strong id="viewRating">-</strong>
                                    </div>
                                    <div class="kpi-preview-item"><span>Strengths:</span> <span
                                            id="viewStrengths">-</span></div>
                                    <div class="kpi-preview-item"><span>Areas for Improvement:</span> <span
                                            id="viewImprovements">-</span></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="kpi-preview-item"><span>Achievements:</span> <span
                                            id="viewAchievements">-</span></div>
                                    <div class="kpi-preview-item"><span>Goals Next Month:</span> <span
                                            id="viewGoals">-</span></div>
                                    <div class="kpi-preview-item"><span>Feedback:</span> <span id="viewFeedback">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 text-muted small" id="reviewMeta"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

        $(document).ready(function() {
            // Auto-submit on select change
            $('.filter-select').on('change', function() {
                $('#filterForm').submit();
            });

            // Rating stars functionality
            let currentRating = 0;
            $('#ratingStars i').on('click', function() {
                currentRating = $(this).data('value');
                $('#overall_rating').val(currentRating);
                $('#ratingText').text('Rating: ' + currentRating + '/5');

                $('#ratingStars i').each(function() {
                    let val = $(this).data('value');
                    if (val <= currentRating) {
                        $(this).removeClass('empty').css('color', '#fbbf24');
                    } else {
                        $(this).addClass('empty').css('color', '#cbd5e1');
                    }
                });
            });

            // Open Create/Edit Modal
            $('.review-employee').on('click', function(e) {
                e.preventDefault();
                let userId = $(this).data('id');
                let employeeName = $(this).data('name');
                let reviewData = $(this).data('review');
                let kpiData = $(this).data('kpi');

                console.log('Review Data:', reviewData);
                console.log('KPI Data:', kpiData);

                $('#modalTitle').text('Performance Review');
                $('#employeeName').text(employeeName);
                $('#user_id').val(userId);

                // Reset form
                $('#reviewForm')[0].reset();
                $('#overall_rating').val('');
                currentRating = 0;
                $('#ratingStars i').removeClass('empty').css('color', '#cbd5e1');
                $('#ratingText').text('Click to rate');
                $('#formError').addClass('d-none');

                // Show KPI preview if available
                if (kpiData && kpiData.attendance_score !== undefined && kpiData.attendance_score !== null) {
                    $('#kpiPreview').show();
                    $('#previewAttendance').text(kpiData.attendance_score + '%');
                    $('#previewTask').text(kpiData.task_completion_score + '%');
                    $('#previewDeadline').text(kpiData.deadline_met_score + '%');
                    $('#previewRegularization').text(kpiData.regularization_score + '%');
                    $('#previewOverall').text(kpiData.overall_score + '%');
                } else {
                    $('#kpiPreview').hide();
                }

                // Populate existing review data
                if (reviewData && reviewData.id) {
                    let review = reviewData;
                    if (review.overall_rating) {
                        currentRating = review.overall_rating;
                        $('#overall_rating').val(currentRating);
                        $('#ratingText').text('Rating: ' + currentRating + '/5');
                        $('#ratingStars i').each(function() {
                            let val = $(this).data('value');
                            if (val <= currentRating) {
                                $(this).removeClass('empty').css('color', '#fbbf24');
                            } else {
                                $(this).addClass('empty').css('color', '#cbd5e1');
                            }
                        });
                    }
                    $('#strengths').val(review.strengths || '');
                    $('#areas_for_improvement').val(review.areas_for_improvement || '');
                    $('#achievements').val(review.achievements || '');
                    $('#goals_next_month').val(review.goals_next_month || '');
                    $('#additional_feedback').val(review.additional_feedback || '');
                }

                $('#reviewModal').modal('show');
            });

            // Save as Draft - FIXED
            $('#saveDraftBtn').on('click', function(e) {
                e.preventDefault();
                
                // For draft, rating is optional
                let hasRating = $('#overall_rating').val();
                if (!hasRating) {
                    // Set a default rating for draft (optional)
                    // Or you can allow draft without rating
                    console.log('Saving draft without rating');
                }
                
                // Create form data manually
                let formData = new FormData();
                formData.append('_token', $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}');
                formData.append('user_id', $('#user_id').val());
                formData.append('month', $('#reviewForm input[name="month"]').val());
                formData.append('status', 'draft');
                formData.append('overall_rating', $('#overall_rating').val() || 3); // Default rating 3 if empty
                formData.append('strengths', $('#strengths').val());
                formData.append('areas_for_improvement', $('#areas_for_improvement').val());
                formData.append('achievements', $('#achievements').val());
                formData.append('goals_next_month', $('#goals_next_month').val());
                formData.append('additional_feedback', $('#additional_feedback').val());

                let userId = $('#user_id').val();
                let url = "{{ route('performance.reviews.store', '') }}/" + userId;

                $.ajax({
                    url: url,
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#reviewModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        }
                    },
                    error: function(xhr) {
                        console.log('Error:', xhr);
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                toastr.error(value[0]);
                            });
                        } else {
                            $('#formError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // Submit Review - FIXED
            $('#reviewForm').on('submit', function(e) {
                e.preventDefault();

                if (!$('#overall_rating').val()) {
                    toastr.error('Please provide a rating');
                    return;
                }

                // Create form data manually for submission
                let formData = new FormData();
                formData.append('_token', $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}');
                formData.append('user_id', $('#user_id').val());
                formData.append('month', $('#reviewForm input[name="month"]').val());
                formData.append('status', 'submitted');
                formData.append('overall_rating', $('#overall_rating').val());
                formData.append('strengths', $('#strengths').val());
                formData.append('areas_for_improvement', $('#areas_for_improvement').val());
                formData.append('achievements', $('#achievements').val());
                formData.append('goals_next_month', $('#goals_next_month').val());
                formData.append('additional_feedback', $('#additional_feedback').val());

                let userId = $('#user_id').val();
                let url = "{{ route('performance.reviews.store', '') }}/" + userId;

                $.ajax({
                    url: url,
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#reviewModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        }
                    },
                    error: function(xhr) {
                        console.log('Error:', xhr);
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                toastr.error(value[0]);
                            });
                        } else {
                            $('#formError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // View Review Modal
            $('.view-review').on('click', function(e) {
                e.preventDefault();
                let reviewId = $(this).data('review-id');

                if (!reviewId || reviewId === 0) {
                    toastr.error('Invalid review ID');
                    return;
                }

                let url = "{{ route('performance.reviews.show', '') }}/" + reviewId;

                $.ajax({
                    url: url,
                    type: "GET",
                    success: function(response) {
                        if (response.success) {
                            let review = response.review;
                            $('#viewEmployeeName').text(review.user.name);
                            $('#viewRating').html(
                                '<div class="rating-stars d-inline-block me-2">' +
                                Array(5).fill().map((_, i) =>
                                    `<i class="feather-star${i < review.overall_rating ? '' : ' empty'}"></i>`
                                ).join('') +
                                '</div> ' + review.overall_rating + '/5'
                            );
                            $('#viewStrengths').text(review.strengths || 'Not specified');
                            $('#viewImprovements').text(review.areas_for_improvement || 'Not specified');
                            $('#viewAchievements').text(review.achievements || 'Not specified');
                            $('#viewGoals').text(review.goals_next_month || 'Not specified');
                            $('#viewFeedback').text(review.additional_feedback || 'Not specified');
                            $('#reviewMeta').html(
                                `Reviewed by ${review.reviewer.name} on ${new Date(review.submitted_at).toLocaleDateString()}`
                            );
                            $('#viewReviewModal').modal('show');
                        }
                    },
                    error: function(xhr) {
                        console.log('Error:', xhr);
                        toastr.error('Unable to load review details');
                    }
                });
            });
        });
    </script>
@endsection
