@extends('client.layout.master')

@section('style')
<style>
    /* ==================== EXPENSE TYPE CARDS — all-blue theme, 4-per-row, compact spacing (mirrors client/department/department.blade.php) ==================== */
    .expense-type-card {
        border: 1px solid #eaeef5;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
        transition: all .2s ease;
    }

    .expense-type-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px -6px rgba(30, 50, 110, .14);
        border-color: #dfe5f0;
    }

    .expense-type-card .side-stick {
        background-color: #0D6EFD;
    }

    .et-title {
        font-size: 11.5px !important;
        font-weight: 700;
        color: #1a2236;
        max-width: 72%;
    }

    .et-date {
        font-size: 9px;
        color: #6b7385;
        margin-bottom: 0;
    }

    .et-badge {
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
        background: #0D6EFD;
        color: #fff;
    }

    .et-description {
        color: #475569;
        font-size: 9.5px;
        line-height: 1.4;
    }

    .et-card-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 5px;
        padding-top: 6px;
        margin-top: 4px;
        border-top: 1px solid #eaeef5;
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

@php
 $user = Auth::user();
 $role = $user->role;
 @endphp

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Expense Type Management" current="Expense Type">
        <x-slot:actions>
            <div class="hstack gap-2">
                @if(in_array($role,['admin','hr']))
                <a href="javascript:void(0)" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#addexpensetypeModal">
                        <i class="feather-plus me-2"></i>Add Expense Type
                    </a>
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            <!--! BEGIN: [Single Note Item] !-->
            @forelse ($expenseTypes as $expenseType)
                <div class="col-xxl-3 col-xl-3 col-lg-3 col-md-6 col-sm-6 single-note-item">
                    <div class="card expense-type-card card-body mb-2 stretch stretch-full position-relative border-0">

                        <span class="side-stick"></span>

                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <!-- Title -->
                            <h5 class="et-title note-title text-truncate mb-0">
                                {{ $expenseType->name }}
                            </h5>

                            <!-- Status Badge -->
                            <span class="et-badge {{ $expenseType->status ? 'badge-active' : 'badge-inactive' }}">
                                {{ $expenseType->status == 1 ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <p class="et-date mb-1">
                            <i class="bi bi-calendar-event me-1"></i>
                            {{ date('d M Y', strtotime($expenseType->created_at)) }}
                        </p>

                        <!-- Description -->
                        <div class="note-content flex-grow-1">
                            <p class="et-description text-truncate-3-line mb-0">
                                {{ $expenseType->description ?? 'No description provided.' }}
                            </p>
                            @if ($expenseType->hasPolicy())
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    @if ($expenseType->max_amount !== null)
                                        <span class="badge bg-soft-primary text-primary">Max ₹{{ number_format($expenseType->max_amount, 2) }}</span>
                                    @endif
                                    @if ($expenseType->receipt_required_above !== null)
                                        <span class="badge bg-soft-warning text-warning">Receipt {{ (float) $expenseType->receipt_required_above > 0 ? 'above ₹' . number_format($expenseType->receipt_required_above, 2) : 'always' }}</span>
                                    @endif
                                    @if ($expenseType->max_backdate_days !== null)
                                        <span class="badge bg-soft-info text-info">Within {{ $expenseType->max_backdate_days }} day(s)</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="et-card-footer">
                            <!-- Edit Icon -->
                            <a href="javascript:void(0)" class="action-btn edit-expense-type" title="Edit Expense Type"
                                data-id="{{ $expenseType->id }}"
                                data-name="{{ $expenseType->name }}"
                                data-description="{{ $expenseType->description }}"
                                data-max_amount="{{ $expenseType->max_amount }}"
                                data-receipt_required_above="{{ $expenseType->receipt_required_above }}"
                                data-max_backdate_days="{{ $expenseType->max_backdate_days }}"
                                data-status="{{ $expenseType->status }}">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                        </div>

                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="empty-state">
                        <i class="bi bi-credit-card-2-front"></i>
                        <h4>No Expense Types Found</h4>
                        <p>Get started by adding your first expense type</p>
                    </div>
                </div>
            @endforelse
            <!--! BEGIN: [Single Note Item] !-->
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Expense Type Modal -->
    <div class="modal fade-scale" id="addexpensetypeModal" tabindex="-1" aria-labelledby="addexpensetypeModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add Expense Type</span>
                    </h2>
                    <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="{{ route('expense-type.create') }}" id="addexpensetypeForm">
                                <div id="addFormError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="name">Name *</label>
                                            <input type="text" class="form-control" name="name" required
                                                id="name" placeholder="Enter Expense Type name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="description">Description</label>
                                            <textarea class="form-control" name="description" id="description" rows="3"
                                                placeholder="Enter Expense Type Description..."></textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <div class="fw-semibold small mb-1">Claim rules <span class="text-muted fw-normal">(optional — leave blank for no rule)</span></div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="fw-semibold" for="max_amount">Max per claim (₹)</label>
                                        <input type="number" class="form-control" name="max_amount" id="max_amount" min="0.01" step="0.01" placeholder="No limit">
                                        <small class="text-danger error-text max_amount_error"></small>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="fw-semibold" for="receipt_required_above">Receipt needed above (₹)</label>
                                        <input type="number" class="form-control" name="receipt_required_above" id="receipt_required_above" min="0" step="0.01" placeholder="Never (0 = always)">
                                        <small class="text-danger error-text receipt_required_above_error"></small>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="fw-semibold" for="max_backdate_days">File within (days of the expense)</label>
                                        <input type="number" class="form-control" name="max_backdate_days" id="max_backdate_days" min="0" step="1" placeholder="No limit">
                                        <small class="text-danger error-text max_backdate_days_error"></small>
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Expense Type Modal -->
    <div class="modal fade-scale" id="editexpensetypeModal" tabindex="-1" aria-labelledby="editexpensetypeModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Expense Type</span>
                    </h2>
                    <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="editexpensetypeForm">
                                @csrf
                                <div id="editFormError" class="alert alert-danger d-none"></div>
                                <input type="hidden" name="id" id="edit_id">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_name">Name *</label>
                                            <input type="text" class="form-control" name="name" required
                                                id="edit_name" placeholder="Enter Expense Type name">
                                            <small class="text-danger error-text edit_name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_description">Description</label>
                                            <textarea class="form-control" name="description" id="edit_description" rows="3"
                                                placeholder="Enter Expense Type Description..."></textarea>
                                            <small class="text-danger error-text edit_description_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <div class="fw-semibold small mb-1">Claim rules <span class="text-muted fw-normal">(optional — leave blank for no rule)</span></div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="fw-semibold" for="edit_max_amount">Max per claim (₹)</label>
                                        <input type="number" class="form-control" name="max_amount" id="edit_max_amount" min="0.01" step="0.01" placeholder="No limit">
                                        <small class="text-danger error-text edit_max_amount_error"></small>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="fw-semibold" for="edit_receipt_required_above">Receipt needed above (₹)</label>
                                        <input type="number" class="form-control" name="receipt_required_above" id="edit_receipt_required_above" min="0" step="0.01" placeholder="Never (0 = always)">
                                        <small class="text-danger error-text edit_receipt_required_above_error"></small>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="fw-semibold" for="edit_max_backdate_days">File within (days of the expense)</label>
                                        <input type="number" class="form-control" name="max_backdate_days" id="edit_max_backdate_days" min="0" step="1" placeholder="No limit">
                                        <small class="text-danger error-text edit_max_backdate_days_error"></small>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_status">Status *</label>
                                            <select class="form-control" name="status" id="edit_status" required>
                                                <option value="1">Active</option>
                                                <option value="0">Inactive</option>
                                            </select>
                                            <small class="text-danger error-text edit_status_error"></small>
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
            // Add Expense Type Form Submission
            $('#addexpensetypeForm').on('submit', function(e) {
                e.preventDefault();
                // Reset errors
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#addexpensetypeModal').modal('hide');
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
                            $('#addFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // Edit Expense Type - Open Modal with Data
            $(document).on('click', '.edit-expense-type', function(e) {
                e.preventDefault();

                // Get data from the clicked card
                const id = $(this).data('id');
                const name = $(this).data('name');
                const description = $(this).data('description');
                const status = $(this).data('status');

                // Populate the edit form
                $('#edit_id').val(id);
                $('#edit_name').val(name);
                $('#edit_description').val(description);
                $('#edit_status').val(status);
                $('#edit_max_amount').val($(this).attr('data-max_amount'));
                $('#edit_receipt_required_above').val($(this).attr('data-receipt_required_above'));
                $('#edit_max_backdate_days').val($(this).attr('data-max_backdate_days'));

                // Clear previous errors
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                // Show the modal
                $('#editexpensetypeModal').modal('show');
            });

            // Edit Expense Type Form Submission
            $('#editexpensetypeForm').on('submit', function(e) {
                e.preventDefault();

                // Reset errors
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();

                $.ajax({
                    url: "{{ route('expense-type.update', '') }}/" + id,
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#editexpensetypeModal').modal('hide');
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        // Validation error
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.edit_' + key + '_error').text(value[0]);
                            });
                            toastr.error('Please fix the validation errors');
                        }
                        // Server error
                        else {
                            $('#editFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // Clear form when modal is closed
            $('#addexpensetypeModal').on('hidden.bs.modal', function() {
                $('#addexpensetypeForm')[0].reset();
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');
            });

            $('#editexpensetypeModal').on('hidden.bs.modal', function() {
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
