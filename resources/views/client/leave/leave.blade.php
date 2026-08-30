@extends('client.layout.master')

@section('style')
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
                    <a href="#" class="btn btn-icon btn-light-brand" data-bs-toggle="collapse"
                        data-bs-target="#collapseOne">
                        <i class="feather-bar-chart"></i>
                    </a>
                    <div class="dropdown">
                        <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10"
                            data-bs-auto-close="outside">
                            <i class="feather-paperclip"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a href="#" class="dropdown-item">
                                <i class="bi bi-filetype-csv me-3"></i>
                                <span>CSV</span>
                            </a>
                        </div>
                    </div>
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
    <div id="collapseOne" class="accordion-collapse collapse page-header-collapse">
        <div class="accordion-body pb-2">
            <div class="row">
                <div class="col-xxl-3 col-md-6">
                    <div class="card stretch stretch-full">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar-text avatar-xl rounded">
                                        <i class="feather-users"></i>
                                    </div>
                                    <a href="#" class="fw-bold d-block">
                                        <span class="text-truncate-1-line">Balance leave</span>
                                        <span class="fs-24 fw-bolder d-block">{{ $balanceLeave }}</span>
                                    </a>
                                </div>
                                {{-- <div class="badge bg-soft-success text-success">
                                    <i class="feather-arrow-up fs-10 me-1"></i>
                                    <span>36.85%</span>
                                </div> --}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-3 col-md-6">
                    <div class="card stretch stretch-full">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar-text avatar-xl rounded">
                                        <i class="feather-users"></i>
                                    </div>
                                    <a href="#" class="fw-bold d-block">
                                        <span class="text-truncate-1-line">Leave Request</span>
                                        <span class="fs-24 fw-bolder d-block">{{ $totalLeave }}</span>
                                    </a>
                                </div>
                                {{-- <div class="badge bg-soft-success text-success">
                                    <i class="feather-arrow-up fs-10 me-1"></i>
                                    <span>36.85%</span>
                                </div> --}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-3 col-md-6">
                    <div class="card stretch stretch-full">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar-text avatar-xl rounded">
                                        <i class="feather-user-check"></i>
                                    </div>
                                    <a href="#" class="fw-bold d-block">
                                        <span class="text-truncate-1-line">Approved Leave</span>
                                        <span class="fs-24 fw-bolder d-block">{{ $approvedLeave }}</span>
                                    </a>
                                </div>
                                {{-- <div class="badge bg-soft-danger text-danger">
                                    <i class="feather-arrow-down fs-10 me-1"></i>
                                    <span>24.56%</span>
                                </div> --}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-3 col-md-6">
                    <div class="card stretch stretch-full">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar-text avatar-xl rounded">
                                        <i class="feather-user-plus"></i>
                                    </div>
                                    <a href="#" class="fw-bold d-block">
                                        <span class="text-truncate-1-line">Unpaid Leave</span>
                                        <span class="fs-24 fw-bolder d-block">{{ $unpaidLeave }}</span>
                                    </a>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
    <!-- [ Main Content ] start -->
    <div class="main-content" style="padding: 30px !important;">
        <!-- Filter Form -->
        <div class="card stretch stretch-full mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('leave.view') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending
                                </option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved
                                </option>
                                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>
                                    Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="from_date" class="form-control"
                                value="{{ request('from_date') }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                        </div>
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="feather-filter me-2"></i> Filter
                            </button>
                            <button type="button" id="resetFilter" class="btn btn-light">
                                <i class="feather-refresh-cw me-2"></i> Reset
                            </button>
                        </div>
                    </div>
                </form>
            </div>
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
                                        <th>Date</th>
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
                                            <td>{{ date('d-m-Y', strtotime($leave->start_date)) ?? '' }}</td>
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

                                                <div class="dropdown">
                                                    <a href="#" class="avatar-text avatar-md"
                                                        data-bs-toggle="dropdown" data-bs-offset="0,21">
                                                        <i class="feather feather-more-horizontal"></i>
                                                    </a>
                                                    <ul class="dropdown-menu">
                                                        @if ($leave->status == 'pending')
                                                            <li>
                                                                <a class="dropdown-item"
                                                                    href="{{ route('leave.update', ['id' => encrypt($leave->id)]) }}">
                                                                    <i class="feather feather-edit-3 me-3"></i>
                                                                    <span>Edit</span>
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item"
                                                                    href="{{ route('leave.delete', ['id' => encrypt($leave->id)]) }}">
                                                                    <i class="feather feather-trash-2 me-3"></i>
                                                                    <span>Delete</span>
                                                                </a>
                                                            </li>
                                                        @else
                                                            <li>
                                                                <a class="dropdown-item" href="#">
                                                                    <i class="feather feather-trash-2 me-3"></i>
                                                                    <span>No Action Performed</span>
                                                                </a>
                                                            </li>
                                                        @endif
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    @if ($leaves->isEmpty())
                                        <tr>
                                            <td colspan="6" class="text-center">No leave applications found.</td>
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
            // Reset filter button
            $('#resetFilter').click(function() {
                // Clear all filter inputs
                $('select[name="status"]').val('');
                $('input[name="from_date"]').val('');
                $('input[name="to_date"]').val('');

                // Submit the form to reset
                $('#filterForm').submit();
            });

            // Optional: Date range validation
            $('input[name="from_date"], input[name="to_date"]').change(function() {
                let fromDate = $('input[name="from_date"]').val();
                let toDate = $('input[name="to_date"]').val();

                if (fromDate && toDate && fromDate > toDate) {
                    alert('From date cannot be greater than To date');
                    $(this).val('');
                }
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
