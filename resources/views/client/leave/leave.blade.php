@extends('client.layout.master')

@section('style')
<style>
    :root {
        /* --lv-primary/-2/-soft used to redeclare the exact same blue as the
           shared theme tokens (theme-custom.css) under a page-local name —
           now aliased to those instead of duplicating the values. The other
           three genuinely differ from their shared-token counterparts
           (border/text colors), so they stay page-local as-is. */
        --lv-primary: var(--primary);
        --lv-primary-2: var(--primary-mid);
        --lv-soft: var(--primary-light);
        --lv-border: #eaeef5;
        --lv-text: #1a2236;
        --lv-text-soft: #6b7385;
    }

    .avatar-text.avatar-xl { background: var(--lv-soft) !important; color: var(--lv-primary) !important; }
    .fs-24 { font-size: 17px !important; }
    .card { border-color: var(--lv-border); }
    .card-title { font-size: 13px; }

    #customerList1 { font-size: 11.5px; }
    #customerList1 th { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; color: var(--lv-text-soft); padding: 8px 10px; }
    #customerList1 td { padding: 8px 10px; vertical-align: middle; }
    #customerList1 .badge { font-size: 10px; padding: 3px 9px; font-weight: 700; }
    #customerList1 .bg-warning { background-color: #60a5fa !important; }
    #customerList1 .bg-success { background-color: #3b82f6 !important; }
    #customerList1 .bg-danger { background-color: var(--lv-primary) !important; }
    #customerList1 .bg-secondary { background-color: #93c5fd !important; color: var(--lv-primary) !important; }

    .btn-primary { background: var(--lv-primary-2); border-color: var(--lv-primary-2); }
    .btn-primary:hover { background: var(--lv-primary); border-color: var(--lv-primary); }
    .form-label { font-size: 11.5px; font-weight: 600; color: var(--lv-text-soft); }

    /* ==================== COMPACT FILTER SECTION (matches Team Leave Applications) ==================== */
    .filter-wrapper {
        background: white;
        border-radius: 12px;
        border: 1px solid var(--lv-border);
        padding: 16px 20px;
        margin-bottom: 20px;
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
        font-size: 12px;
        font-weight: 600;
        color: var(--lv-text);
    }

    .filter-title i { color: var(--lv-primary); font-size: 13px; }

    .filter-title span {
        background: var(--lv-soft);
        color: var(--lv-primary);
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 20px;
        margin-left: 6px;
    }

    .clear-all-link {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--lv-text-soft);
        font-size: 12px;
        text-decoration: none;
        padding: 4px 10px;
        border-radius: 20px;
        transition: all 0.2s;
    }

    .clear-all-link:hover { background: var(--lv-soft); color: var(--lv-primary); }
    .clear-all-link i { font-size: 12px; }

    .filter-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }

    .filter-item { flex: 0 0 auto; min-width: 160px; }
    .filter-item.narrow { min-width: auto; }

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
        border-color: var(--lv-primary);
        box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        outline: none;
    }

    .filter-select:hover,
    .filter-item .form-control:hover { background-color: white; border-color: #94a3b8; }

    .reset-btn {
        height: 36px;
        padding: 0 12px;
        background: white;
        color: var(--lv-text-soft);
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .reset-btn:hover { background: #f8fafc; border-color: #94a3b8; color: var(--lv-text); }

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
        color: var(--lv-text-soft);
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

    .filter-tag i { color: var(--lv-primary); font-size: 11px; }

    .filter-tag .remove-tag {
        color: #94a3b8;
        margin-left: 2px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
    }

    .filter-tag .remove-tag:hover { color: var(--lv-primary); }

    .filter-tag.clear-all {
        background: var(--lv-soft);
        border-color: var(--lv-primary);
        color: var(--lv-primary);
        font-weight: 600;
        text-decoration: none;
        padding: 3px 10px;
    }

    .filter-tag.clear-all:hover { background: var(--lv-primary); color: white; }
    .filter-tag.clear-all i { color: currentColor; }

    /* ==================== DIRECT ACTION ICONS (no 3-dot dropdown) ==================== */
    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        color: var(--lv-text-soft);
        border: 1px solid #e2e8f0;
        transition: all 0.2s;
    }

    .action-btn:hover {
        background: white;
        color: var(--lv-primary);
        border-color: var(--lv-primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(30, 58, 138, 0.1);
    }

    .action-btn i { font-size: 12px; }

    /* ==================== STATS CARDS (matches Team Leave Applications) ==================== */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: .75rem;
        margin-bottom: 1rem;
    }

    .stats-card {
        background: white;
        border: 1px solid var(--lv-border);
        border-radius: 10px;
        padding: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.2s;
        box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
    }

    .stats-card:hover {
        box-shadow: 0 4px 12px -4px rgba(30, 50, 110, .12);
        border-color: #dfe5f0;
        transform: translateY(-1px);
    }

    .stats-icon {
        width: 32px;
        height: 32px;
        background: var(--lv-soft);
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: none;
    }

    .stats-icon i { font-size: 12px; color: var(--lv-primary); }

    .stats-info h3 {
        font-size: 15px;
        font-weight: 800;
        margin: 0 0 1px 0;
        color: var(--lv-text);
        line-height: 1.2;
    }

    .stats-info p {
        font-size: 9.5px;
        font-weight: 600;
        color: var(--lv-text-soft);
        margin: 0;
    }

    @media (max-width: 992px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .filter-wrapper { padding: 12px; }
        .filter-row { flex-direction: column; align-items: stretch; }
        .filter-item { width: 100%; }
        .filter-header { flex-direction: column; align-items: flex-start; gap: 8px; }
        .stats-grid { grid-template-columns: 1fr; }
    }
</style>
@endsection

@section('content-area')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Leave Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                <li class="breadcrumb-item">View Leaves</li>
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
                    <a href="{{ route('leave-credit.my-transactions') }}" class="btn btn-primary">
                        <i class="feather-file-text me-2"></i>
                        <span>My Leave Report</span>
                    </a>
                    <a href="{{ route('leave.apply') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Apply Leave</span>
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
    <!-- [ page-header ] end -->
    <!-- [ Main Content ] start -->
    <div class="main-content" style="padding: 20px !important;">
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="feather-users"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $balanceLeave }}</h3>
                    <p>Balance Leave</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon" style="background: rgba(96, 165, 250, 0.15);">
                    <i class="feather-calendar" style="color: #60a5fa;"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $totalLeave }}</h3>
                    <p>Leave Request</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon" style="background: rgba(59, 130, 246, 0.15);">
                    <i class="feather-user-check" style="color: #3b82f6;"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $approvedLeave }}</h3>
                    <p>Approved Leave</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon" style="background: rgba(30, 58, 138, 0.15);">
                    <i class="feather-user-plus" style="color: #1e3a8a;"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $unpaidLeave }}</h3>
                    <p>Unpaid Leave</p>
                </div>
            </div>
        </div>

        <!-- Compact Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Leave Applications
                    @php
                        $activeFilterCount = collect(request()->only(['status', 'leave_type', 'from_date', 'to_date']))
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['status', 'leave_type', 'from_date', 'to_date']))
                    <a href="{{ route('leave.view') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ route('leave.view') }}" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending
                            </option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved
                            </option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>
                                Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="leave_type" class="filter-select">
                            <option value="">All Leave Types</option>
                            @foreach ($leaveTypes ?? [] as $type)
                                <option value="{{ $type->id }}" {{ request('leave_type') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <input type="date" class="form-control" name="from_date" value="{{ request('from_date') }}">
                    </div>
                    <div class="filter-item">
                        <input type="date" class="form-control" name="to_date" value="{{ request('to_date') }}">
                    </div>
                    <div class="filter-item narrow">
                        <a href="{{ route('leave.view') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            @if (request()->hasAny(['status', 'leave_type', 'from_date', 'to_date']))
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>

                    @if (request('status'))
                        <span class="filter-tag">
                            <i class="feather-activity"></i>
                            Status: {{ ucfirst(request('status')) }}
                            <a href="{{ route('leave.view', request()->except(['status', 'page'])) }}" class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('leave_type') && ($selectedType = ($leaveTypes ?? collect())->firstWhere('id', request('leave_type'))))
                        <span class="filter-tag">
                            <i class="feather-tag"></i>
                            Type: {{ $selectedType->name }}
                            <a href="{{ route('leave.view', request()->except(['leave_type', 'page'])) }}" class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('from_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            From: {{ request('from_date') }}
                            <a href="{{ route('leave.view', request()->except(['from_date', 'page'])) }}" class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('to_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            To: {{ request('to_date') }}
                            <a href="{{ route('leave.view', request()->except(['to_date', 'page'])) }}" class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('leave.view') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Leave Applications</h5>
                    </div>
                    <div class="card-body p-0">
                        <!-- Remove the old status filter div here -->
                        <!-- <div class="options px-4 pt-4"> ... </div> -->

                        <div class="table-responsive">
                            <table class="table table-hover" id="customerList1">
                                <thead>
                                    <tr class="text-center">
                                        <th>S. No.</th>
                                        <th>Leave Type</th>
                                        <th>Date Range</th>
                                        <th>Days</th>
                                        <th>Session</th>
                                        <th style="width:100px !important;">Reason</th>
                                        <th>File</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($leaves as $leave)
                                        <tr class="text-center">
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $leave->leaveType->name ?? '' }}</td>
                                            <td>
                                                {{ date('d M Y', strtotime($leave->start_date)) }}
                                                @if ($leave->end_date && $leave->end_date != $leave->start_date)
                                                    &rarr; {{ date('d M Y', strtotime($leave->end_date)) }}
                                                @endif
                                            </td>
                                            <td>{{ $leave->total_days ?? $leave->leave_count }}</td>
                                            <td>{{ ucfirst($leave->start_session) ?? '' }}</td>
                                            <td style="width:100px !important;">{{ ucfirst($leave->reason) ?? '' }}</td>
                                            <td>
                                                @if ($leave->file)
                                                    @php
                                                        $fileUrl = $leave->file;
                                                        $extension = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));

                                                        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                                                    @endphp

                                                    @if (in_array($extension, $imageExtensions))
                                                        <!-- Image Preview -->
                                                        <a href="{{ $fileUrl }}" target="_blank">
                                                            <img src="{{ $fileUrl }}" alt="Leave File"
                                                                height="35" width="35"
                                                                style="border-radius: 50%; object-fit: cover;">
                                                        </a>
                                                    @else
                                                        <!-- Download File -->
                                                        <a href="{{ $fileUrl }}" download
                                                            class="text-decoration-none">
                                                            <i class="bi bi-download fs-4 text-primary"></i>
                                                        </a>
                                                    @endif
                                                @else
                                                    <span class="text-muted">No File</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $statusClasses = [
                                                        'pending' => 'bg-warning',
                                                        'approved' => 'bg-success',
                                                        'cancelled' => 'bg-danger',
                                                    ];
                                                @endphp
                                                <span
                                                    class="badge {{ $statusClasses[$leave->status] ?? 'bg-secondary' }}">
                                                    {{ ucfirst($leave->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($leave->status == 'pending')
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <a href="{{ route('leave.update', ['id' => encrypt($leave->id)]) }}"
                                                            class="action-btn" title="Edit">
                                                            <i class="feather-edit-3"></i>
                                                        </a>
                                                        <a href="{{ route('leave.delete', ['id' => encrypt($leave->id)]) }}"
                                                            class="action-btn" title="Delete"
                                                            onclick="return confirm('Are you sure you want to delete this leave application?')">
                                                            <i class="feather-trash-2"></i>
                                                        </a>
                                                    </div>
                                                @else
                                                    <span class="text-muted">&mdash;</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    @if ($leaves->isEmpty())
                                        <tr>
                                            <td colspan="9" class="text-center">No leave applications found.</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->
@endsection
@section('create-modal')
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Auto-submit on filter select change
            $('.filter-select').on('change', function() {
                $('#filterForm').submit();
            });

            // Date range validation, then auto-submit (no Apply button in this filter bar)
            $('input[name="from_date"], input[name="to_date"]').on('change', function() {
                let fromDate = $('input[name="from_date"]').val();
                let toDate = $('input[name="to_date"]').val();

                if (fromDate && toDate && fromDate > toDate) {
                    alert('From date cannot be greater than To date');
                    $(this).val('');
                    return;
                }

                $('#filterForm').submit();
            });

            // Add Department Form submission (existing code)
            $('#addDepartmentForm').on('submit', function(e) {
                e.preventDefault();
                // Reset errors
                $('.error-text').text('');
                $('#formError').addClass('d-none').text('');

                $.ajax({
                    url: "{{ route('leave-type.create') }}",
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#addDepartments').modal('hide');
                            location.reload();
                        }
                    },
                    error: function(xhr) {
                        // ✅ Validation error
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.' + key + '_error').text(value[0]);
                            });
                        }
                        // ✅ Server error
                        else {
                            $('#formError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });
            });
        });
    </script>
@endsection
