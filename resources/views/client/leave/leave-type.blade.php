@extends('client.layout.master')

@section('style')
<style>
    /* ==================== LEAVE TYPE CARDS — all-blue theme, 4-per-row, compact spacing (mirrors client/department/department.blade.php) ==================== */
    .leave-type-card {
        border: 1px solid #eaeef5;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
        transition: all .2s ease;
    }

    .leave-type-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px -6px rgba(30, 50, 110, .14);
        border-color: #dfe5f0;
    }

    .leave-type-card .side-stick {
        background-color: #1e3a8a;
    }

    .lt-title {
        font-size: 11.5px !important;
        font-weight: 700;
        color: #1a2236;
        max-width: 62%;
    }

    .lt-date {
        font-size: 9px;
        color: #6b7385;
        margin-bottom: 0;
    }

    .lt-badges {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .lt-badge {
        padding: 2px 7px;
        border-radius: 30px;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: .2px;
        white-space: nowrap;
    }

    .badge-active {
        background: #3b82f6;
        color: #fff;
    }

    .badge-inactive {
        background: #1e3a8a;
        color: #fff;
    }

    .badge-system {
        background: #e3edfe;
        color: #1e3a8a;
    }

    .badge-unpaid {
        background: #93c5fd;
        color: #1e3a8a;
    }

    .lt-info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f4f6fb;
        border-radius: 8px;
        padding: 5px 8px;
    }

    .lt-info-label {
        font-size: 8px;
        color: #6b7385;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .lt-info-value {
        font-size: 9.5px;
        font-weight: 600;
        color: #1a2236;
        text-transform: capitalize;
    }

    .lt-info-value i {
        color: #1e3a8a;
    }

    .lt-description {
        color: #475569;
        font-size: 9.5px;
        line-height: 1.4;
    }

    .lt-card-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 5px;
        padding-top: 6px;
        margin-top: 4px;
        border-top: 1px solid #eaeef5;
    }

    .action-btn {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #f4f6fb;
        border: 1px solid #eaeef5;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6b7385;
        font-size: 10px;
        transition: all .2s;
        cursor: pointer;
        text-decoration: none;
        flex-shrink: 0;
    }

    .action-btn:hover {
        background: #1e3a8a;
        color: #fff;
        border-color: #1e3a8a;
    }

    /* ==================== EMPTY STATE ==================== */
    .empty-state {
        padding: 36px 20px;
        text-align: center;
        background: linear-gradient(145deg, #ffffff 0%, #f4f6fb 100%);
        border-radius: 14px;
    }

    .empty-state i {
        font-size: 48px;
        color: #93c5fd;
        margin-bottom: 12px;
    }

    .empty-state h4 {
        color: #1a2236;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 6px;
    }

    .empty-state p {
        color: #6b7385;
        font-size: 11.5px;
        margin-bottom: 0;
    }

    /* ==================== COMPACT MODAL (Add / Edit) — core chrome
       (max-width/header/body/card/row/label/btn) is centralized in
       public/assets/css/theme-custom.css; only this page's own extras stay here. ==================== */
    .compact-modal .modal-header .fs-18 { font-size: 13px !important; }
    .compact-modal .form-group { margin-bottom: 0; }
    .compact-modal .form-control,
    .compact-modal .form-check-label { font-size: 11.5px; }
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
                <div class="dropdown d-none d-sm-flex">
                    <a href="javascript:void(0)" class="btn btn-light-brand btn-sm rounded-pill" data-bs-toggle="modal"
                        data-bs-target="#addLeaveType">
                        <i class="feather-plus me-2"></i>Add Leave Type
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            <!--! BEGIN: [Single Note Item] !-->
            @forelse ($leaveTypes as $leaveType)
                <div class="col-xxl-3 col-xl-3 col-lg-3 col-md-6 col-sm-6 single-note-item">
                    <div class="card leave-type-card card-body mb-2 stretch stretch-full position-relative border-0">

                        <span class="side-stick"></span>

                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <!-- Title -->
                            <h5 class="lt-title note-title text-truncate mb-0">
                                {{ $leaveType->name }}
                            </h5>

                            <div class="lt-badges">
                                @if ($leaveType->isSystemType())
                                    <span class="lt-badge badge-system" title="System-managed — cannot be edited or deleted">
                                        <i class="feather-lock"></i> System
                                    </span>
                                @endif
                                @if ($leaveType->is_unpaid)
                                    <span class="lt-badge badge-unpaid">Unpaid</span>
                                @endif
                                <span class="lt-badge {{ $leaveType->status ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $leaveType->status ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        <p class="lt-date mb-1">
                            <i class="bi bi-calendar-event me-1"></i>
                            {{ date('d M Y', strtotime($leaveType->created_at)) }}
                        </p>

                        <!-- Info Row -->
                        <div class="lt-info-row mb-1">
                            <!-- Credit Type -->
                            <div>
                                <small class="lt-info-label d-block">Credit Type</small>
                                <span class="lt-info-value">
                                    <i class="bi bi-arrow-repeat me-1"></i>
                                    {{ $leaveType->credit_type }}
                                </span>
                            </div>

                            <!-- Credit Value -->
                            <div class="text-end">
                                <small class="lt-info-label d-block">Credit Leaves</small>
                                <span class="lt-info-value">
                                    <i class="bi bi-plus-circle me-1"></i>
                                    {{ $leaveType->credit_value }}
                                </span>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="note-content flex-grow-1">
                            <p class="lt-description text-truncate-3-line mb-0">
                                {{ $leaveType->description ?? 'No description provided.' }}
                            </p>
                        </div>

                        <div class="lt-card-footer">
                            <!-- View Icon -->
                            <a href="{{ route('leave-type.detail', ['id' => encrypt($leaveType->id)]) }}"
                                class="action-btn" title="View Details">
                                <i class="bi bi-eye-fill"></i>
                            </a>

                            @if (!$leaveType->isSystemType())
                                <!-- Edit Icon -->
                                <a href="javascript:void(0)" class="action-btn edit-leave-type" title="Edit Leave Type"
                                    data-update-url="{{ route('leave-type.update', ['id' => encrypt($leaveType->id)]) }}"
                                    data-name="{{ $leaveType->name }}"
                                    data-credit-type="{{ $leaveType->credit_type }}"
                                    data-credit-value="{{ $leaveType->credit_value }}"
                                    data-description="{{ $leaveType->description }}"
                                    data-status="{{ $leaveType->status }}"
                                    data-is-unpaid="{{ $leaveType->is_unpaid ? 1 : 0 }}">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                            @endif
                        </div>

                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="empty-state">
                        <i class="bi bi-calendar-week"></i>
                        <h4>No Leave Types Found</h4>
                        <p>Get started by adding your first leave type</p>
                    </div>
                </div>
            @endforelse
            <!--! BEGIN: [Single Note Item] !-->
        </div>
    </div>
@endsection
@section('create-modal')
    <!-- Add Leave Type Modal -->
    <x-ui.modal id="addLeaveType" title="Add Leave Type">
        <form action="#" id="addLeaveTypeForm">
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
                        <label class="fw-semibold" for="credit_type">Credit Type *</label>
                        <select class="form-control" name="credit_type" id="credit_type" required>
                            <option value="no">No</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
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
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_unpaid" id="is_unpaid" value="1">
                        <label class="form-check-label" for="is_unpaid">
                            Unpaid leave type
                        </label>
                    </div>
                </div>

                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="description">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="3"
                            placeholder="Enter Leave Type Description..."></textarea>
                        <small class="text-danger error-text description_error"></small>
                    </div>
                </div>

                <div class="col-6">
                    <button class="btn btn-primary" type="submit">
                        <i class="feather-save me-2"></i>Save
                    </button>
                </div>

                <div class="col-6">
                    <a href="javascript:void(0)" class="btn btn-modal-cancel float-end"
                        data-bs-dismiss="modal">
                        <i class="feather-x me-2"></i>Cancel
                    </a>
                </div>
            </div>
        </form>
    </x-ui.modal>

    <!-- Edit Leave Type Modal -->
    <x-ui.modal id="editLeaveTypeModal" title="Edit Leave Type">
        <form action="#" id="editLeaveTypeForm">
            <div id="editFormError" class="alert alert-danger d-none"></div>
            <div class="row">
                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_name">Leave Type Name *</label>
                        <input type="text" class="form-control" name="name" required
                            id="edit_name" placeholder="Enter Leave Type name">
                        <small class="text-danger error-text name_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_credit_type">Credit Type *</label>
                        <select class="form-control" name="credit_type" id="edit_credit_type" required>
                            <option value="no">No Credit</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                        <small class="text-danger error-text credit_type_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_credit_value">Credit Value *</label>
                        <input type="number" step="any" class="form-control" name="credit_value" required
                            id="edit_credit_value" placeholder="Enter credit value">
                        <small class="text-danger error-text credit_value_error"></small>
                    </div>
                </div>
                <div class="col-md-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_status">Status *</label>
                        <select class="form-control" name="status" id="edit_status" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        <small class="text-danger error-text status_error"></small>
                    </div>
                </div>

                <div class="col-12 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_unpaid" id="edit_is_unpaid" value="1">
                        <label class="form-check-label" for="edit_is_unpaid">
                            Unpaid leave type
                        </label>
                    </div>
                </div>

                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_description">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"
                            placeholder="Enter leave type description..."></textarea>
                        <small class="text-danger error-text description_error"></small>
                    </div>
                </div>

                <div class="col-6">
                    <button class="btn btn-primary" type="submit">
                        <i class="feather-save me-2"></i>Update
                    </button>
                </div>

                <div class="col-6">
                    <a href="javascript:void(0)" class="btn btn-modal-cancel float-end"
                        data-bs-dismiss="modal">
                        <i class="feather-x me-2"></i>Cancel
                    </a>
                </div>
            </div>
        </form>
    </x-ui.modal>
@endsection


@section('script-area')
    <script>
        $(document).ready(function() {

            $('#addLeaveTypeForm').on('submit', function(e) {

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
                            $('#addLeaveType').modal('hide');
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {

                        // Validation error
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.' + key + '_error').text(value[0]);
                            });
                            toastr.error('Please fix the validation errors');
                        }

                        // Server error
                        else {
                            $('#formError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });

            });

            // Edit Leave Type - Open Modal with Data
            $(document).on('click', '.edit-leave-type', function(e) {
                e.preventDefault();

                const updateUrl = $(this).data('update-url');
                const name = $(this).data('name');
                const creditType = $(this).data('credit-type');
                const creditValue = $(this).data('credit-value');
                const description = $(this).data('description');
                const status = $(this).data('status');
                const isUnpaid = $(this).data('is-unpaid');

                $('#editLeaveTypeForm').attr('action', updateUrl);
                $('#edit_name').val(name);
                $('#edit_credit_type').val(creditType).trigger('change');
                $('#edit_credit_value').val(creditValue);
                $('#edit_description').val(description);
                $('#edit_status').val(status).trigger('change');
                $('#edit_is_unpaid').prop('checked', String(isUnpaid) === '1');

                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                $('#editLeaveTypeModal').modal('show');
            });

            // Edit Leave Type Form Submission
            $('#editLeaveTypeForm').on('submit', function(e) {
                e.preventDefault();

                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#editLeaveTypeModal').modal('hide');
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
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
                            $('#editFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            $('#addLeaveType').on('hidden.bs.modal', function() {
                $('#addLeaveTypeForm')[0].reset();
                $('.error-text').text('');
                $('#formError').addClass('d-none').text('');
            });

            $('#editLeaveTypeModal').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
            });

            // Initialize toastr if not already loaded
            if (typeof toastr !== 'undefined') {
                toastr.options = {
                    "closeButton": true,
                    "progressBar": true,
                    "positionClass": "toast-top-right",
                    "timeOut": "3000"
                };
            }
        });
    </script>
@endsection
