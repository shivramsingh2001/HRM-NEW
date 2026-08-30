@extends('client.layout.master')

@section('style')
@endsection

@section('content-area')
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">Leave Type Management</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Leave Type</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                <div class="hstack">

                </div>
                <div class="dropdown d-none d-sm-flex">
                    <a href="javascript:void(0)" class="btn btn-light-brand btn-sm rounded-pill" data-bs-toggle="modal"
                        data-bs-target="#addDepartments">Add Leave Type</a>
                </div>
            </div>
        </div>
    </div>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            <!--! BEGIN: [Single Note Item] !-->
            @foreach ($leaveTypes as $leaveType)
                <div class="col-xxl-4 col-xl-6 col-lg-4 col-sm-6 single-note-item">
                    <div class="card card-body mb-4 stretch stretch-full position-relative shadow-sm border-0">

                        <!-- Top Right: Status + View -->
                        <div class="position-absolute top-0 end-0 d-flex align-items-center gap-2 m-3">

                            <!-- Status Badge -->
                            <span class="badge {{ $leaveType->status ? 'bg-success' : 'bg-danger' }}">
                                {{ $leaveType->status ? 'Active' : 'Inactive' }}
                            </span>

                            <!-- View Icon -->
                            <a href="{{ route('leave-type.detail', ['id' => encrypt($leaveType->id)]) }}"
                                class="text-decoration-none">
                                <i class="bi bi-eye-fill fs-5 text-muted"></i>
                            </a>

                        </div>

                        <span class="side-stick"></span>

                        <!-- Title -->
                        <h5 class="note-title text-truncate w-75 mb-1">
                            {{ $leaveType->name }}
                            <i class="point bi bi-circle-fill ms-1 fs-7 text-success"></i>
                        </h5>

                        <p class="fs-11 text-muted mb-2">
                            <i class="bi bi-calendar-event me-1"></i>
                            {{ date('d M Y', strtotime($leaveType->created_at)) }}
                        </p>

                        <!-- Credit Info Box -->
                        <div class="d-flex align-items-center justify-content-between bg-light rounded-3 px-3 py-1 mb-2">
                            <!-- Credit Type -->
                            <div>
                                <small class="text-muted d-block fs-10">Credit Type</small>
                                @php
                                    $typeColor = match ($leaveType->credit_type) {
                                        'weekly' => 'primary',
                                        'monthly' => 'info',
                                        'yearly' => 'success',
                                        'no' => 'secondary',
                                        default => 'dark',
                                    };
                                @endphp
                                <span class="badge bg-{{ $typeColor }} px-2 py-1 text-capitalize">
                                    <i class="bi bi-arrow-repeat me-1 fs-11"></i>
                                    {{ $leaveType->credit_type }}
                                </span>
                            </div>

                            <!-- Credit Value -->
                            <div class="text-end">
                                <small class="text-muted d-block fs-10">Credit Leaves</small>
                                <span class="fw-bold fs-6 text-dark">
                                    <i class="bi bi-plus-circle text-success me-1"></i>
                                    {{ $leaveType->credit_value }}
                                </span>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="note-content">
                            <p class="text-muted fs-12 text-truncate-3-line">
                                {{ $leaveType->description ?? 'No description provided.' }}
                            </p>
                        </div>

                    </div>
                </div>
            @endforeach
            <!--! BEGIN: [Single Note Item] !-->
        </div>
    </div>
@endsection
@section('create-modal')
    <div class="modal fade-scale" id="addDepartments" tabindex="-1" aria-labelledby="addDepartments" aria-hidden="true"
        data-bs-dismiss="ou">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">

                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add Leave Type</span>
                        {{-- <small class="d-block fs-11 fw-normal text-muted">Leave Type must have a head!</small> --}}
                    </h2>
                    <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon"
                        data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>

                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="#" id="addDepartmentForm">
                                <div id="formError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="name">Name *</label>
                                            <input type="text" class="form-control" name="name" required
                                                id="name" placeholder="Enter Leave Type name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="credit_type">Credit Type*</label>
                                            <select class="form-control" name="credit_type" id="credit_type" required>
                                                <option value="no">No
                                                </option>
                                                <option value="weekly">Weekly
                                                </option>
                                                <option value="monthly">Monthly
                                                </option>
                                                <option value="yearly">Yearly
                                                </option>

                                            </select>
                                            <small class="text-danger error-text credit_type_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="credit_value">Credit Value *</label>
                                            <input type="text" class="form-control" name="credit_value" required
                                                id="credit_value" placeholder="Enter Leave Credit Value" value="0">
                                            <small class="text-danger error-text credit_value_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="description">Description
                                            </label>
                                            <textarea class="form-control" name="description" id="description" rows="3"
                                                placeholder="Enter Department Description..."></textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">Save</button>
                                    </div>

                                    <div class="col-6">
                                        <a href="javascript:void(0)" class="btn btn-danger text-warning float-end"
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
@endsection


@section('script-area')
    <script>
        $(document).ready(function() {

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
