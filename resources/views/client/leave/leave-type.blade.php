@extends('client.layout.master')

@section('style')
<style>
    :root {
        --lt-primary: #1e3a8a;
        --lt-primary-2: #2563eb;
        --lt-soft: #e3edfe;
        --lt-border: #eaeef5;
        --lt-text: #1a2236;
        --lt-text-soft: #6b7385;
    }

    /* Bootstrap's row/col gutters are margin-based (--bs-gutter-y), not CSS
       grid gap, so the vertical gap between rows is trimmed here directly. */
    .note-has-grid { --bs-gutter-y: 0.35rem; }

    .single-note-item .card {
        border: 1px solid var(--lt-border) !important;
        border-radius: 10px;
        margin-bottom: 0 !important;
    }

    .single-note-item .card-body {
        padding: 10px 12px !important;
    }

    .single-note-item .note-title {
        font-size: 12px;
        font-weight: 700;
        color: var(--lt-text);
        margin-bottom: 2px !important;
    }

    .single-note-item .badge {
        font-size: 9px;
        padding: 2px 7px;
    }
    .single-note-item .badge.bg-success { background-color: var(--lt-primary-2) !important; }
    .single-note-item .badge.bg-danger { background-color: var(--lt-primary) !important; }
    .single-note-item .badge.bg-system {
        background-color: var(--lt-soft);
        color: var(--lt-primary);
        font-weight: 700;
    }
    .single-note-item .badge.bg-primary,
    .single-note-item .badge.bg-info { background-color: var(--lt-primary-2) !important; }
    .single-note-item .badge.bg-secondary { background-color: #93c5fd !important; color: var(--lt-primary) !important; }

    .single-note-item .bg-light { background-color: #f4f6fb !important; padding: 6px 10px !important; }
    .single-note-item .fs-11, .single-note-item .fs-12, .single-note-item .fs-10 { color: var(--lt-text-soft); font-size: 9.5px !important; }
    .single-note-item .fs-6 { font-size: 12px !important; }
    .single-note-item .note-content p { font-size: 10.5px !important; margin-bottom: 0; }

    /* ==================== COMPACT MODAL (Add Leave Type) — core chrome
       (max-width/header/body/card/row/label/btn) is centralized in
       client.layout.head; only this page's own extras stay here. ==================== */
    .compact-modal .modal-header .fs-18 { font-size: 13px !important; }
    .compact-modal .form-group { margin-bottom: 0; }
    .compact-modal .form-control { font-size: 11.5px; }
</style>
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
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-2 note-has-grid" id="note-full-container">
            <!--! BEGIN: [Single Note Item] !-->
            @foreach ($leaveTypes as $leaveType)
                <div class="col single-note-item">
                    <div class="card card-body stretch stretch-full position-relative shadow-sm border-0">

                        <!-- Top Right: Status + View -->
                        <div class="position-absolute top-0 end-0 d-flex align-items-center gap-2 m-3">

                            <!-- System Badge -->
                            @if ($leaveType->isSystemType())
                                <span class="badge bg-system" title="System-managed — cannot be edited or deleted">
                                    <i class="feather-lock fs-10"></i> System
                                </span>
                            @endif

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
    <x-ui.modal id="addDepartments" title="Add Leave Type">
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
                                        <a href="javascript:void(0)" class="btn btn-modal-cancel float-end"
                                            data-bs-dismiss="modal">
                                            Cancel
                                        </a>
                                    </div>

                                </div>
                            </form>
    </x-ui.modal>
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
