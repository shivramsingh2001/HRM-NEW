@extends('client.layout.master')

@section('style')
<style>
    /* ==================== DESIGNATION CARDS — all-blue theme, 4-per-row, compact spacing (mirrors client/department/department.blade.php) ==================== */
    .designation-card {
        border: 1px solid #eaeef5;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
        transition: all .2s ease;
    }

    .designation-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px -6px rgba(30, 50, 110, .14);
        border-color: #dfe5f0;
    }

    .designation-card .side-stick {
        background-color: #0D6EFD;
    }

    .desig-title {
        font-size: 11.5px !important;
        font-weight: 700;
        color: #1a2236;
        max-width: 72%;
    }

    .desig-date {
        font-size: 9px;
        color: #6b7385;
        margin-bottom: 0;
    }

    .desig-badge {
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

    .desig-info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f4f6fb;
        border-radius: 8px;
        padding: 5px 8px;
    }

    .desig-info-label {
        font-size: 8px;
        color: #6b7385;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .desig-info-value {
        font-size: 9.5px;
        font-weight: 600;
        color: #1a2236;
    }

    .desig-info-value i {
        color: var(--icon-color, #0D6EFD);
    }

    .desig-description {
        color: #475569;
        font-size: 9.5px;
        line-height: 1.4;
    }

    .desig-card-footer {
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
    <x-ui.page-header class="content-area-header sticky-top" title="Designation Management" current="Designation">
        <x-slot:actions>
            <div class="hstack gap-2">
                @if(in_array($role,['admin','hr']))
                <a href="javascript:void(0)" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#addDesignations">
                        <i class="feather-plus me-2"></i>Add Designation
                    </a>
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            <!--! BEGIN: [Single Note Item] !-->
            @forelse ($designations as $designation)
                <div class="col-xxl-3 col-xl-3 col-lg-3 col-md-6 col-sm-6 single-note-item">
                    <div class="card designation-card card-body mb-2 stretch stretch-full position-relative border-0">

                        <span class="side-stick"></span>

                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <!-- Title -->
                            <h5 class="desig-title note-title text-truncate mb-0">
                                {{ $designation->name }}
                            </h5>

                            <!-- Status Badge -->
                            <span class="desig-badge {{ $designation->status ? 'badge-active' : 'badge-inactive' }}">
                                {{ $designation->status ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <p class="desig-date mb-1">
                            <i class="bi bi-calendar-event me-1"></i>
                            {{ date('d M Y', strtotime($designation->created_at)) }}
                        </p>

                        <!-- Info Row -->
                        <div class="desig-info-row mb-1">
                            <div>
                                <small class="desig-info-label d-block">Employees</small>
                                <span class="desig-info-value">
                                    <i class="bi bi-person-badge me-1"></i>
                                    {{ $designation->employees_count ?? 0 }}
                                </span>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="note-content flex-grow-1">
                            <p class="desig-description text-truncate-3-line mb-0">
                                {{ $designation->description ?? 'No description provided.' }}
                            </p>
                        </div>

                        @if(in_array($role,['admin','hr']) || in_array($role,['admin','manager']))
                            <div class="desig-card-footer">
                                @if(in_array($role,['admin','hr']))
                                    <!-- View Icon -->
                                    <a href="{{ route('designation.detail', ['id' => encrypt($designation->id)]) }}"
                                        class="action-btn" title="View Details">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>
                                @endif

                                @if(in_array($role,['admin','manager']))
                                    <!-- Edit Icon -->
                                    <a href="javascript:void(0)" class="action-btn edit-designation" title="Edit Designation"
                                        data-update-url="{{ route('designation.update', ['id' => encrypt($designation->id)]) }}"
                                        data-name="{{ $designation->name }}"
                                        data-description="{{ $designation->description }}"
                                        data-status="{{ $designation->status }}">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                @endif
                            </div>
                        @endif

                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="empty-state">
                        <i class="bi bi-person-badge"></i>
                        <h4>No Designations Found</h4>
                        <p>Get started by adding your first designation</p>
                    </div>
                </div>
            @endforelse
            <!--! BEGIN: [Single Note Item] !-->
        </div>
    </div>
@endsection
@section('create-modal')
    <!-- Add Designation Modal -->
    <div class="modal fade-scale" id="addDesignations" tabindex="-1" aria-labelledby="addDesignations" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">

                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add Designation</span>
                    </h2>
                    <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon"
                        data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>

                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="#" id="addDesignationForm">
                                <div id="formError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="name">Name *</label>
                                            <input type="text" class="form-control" name="name" required
                                                id="name" placeholder="Enter Designation name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="description">Description
                                            </label>
                                            <textarea class="form-control" name="description" id="description" rows="3"
                                                placeholder="Enter Designation Description..."></textarea>
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Designation Modal -->
    <div class="modal fade-scale" id="editDesignationModal" tabindex="-1" aria-labelledby="editDesignationModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">

                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Designation</span>
                        <small class="d-block fs-11 fw-normal text-muted">Update designation information</small>
                    </h2>
                    <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon"
                        data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>

                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="#" id="editDesignationForm">
                                <div id="editFormError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_name">Designation Name *</label>
                                            <input type="text" class="form-control" name="name" required
                                                id="edit_name" placeholder="Enter Designation name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
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
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_description">Description</label>
                                            <textarea class="form-control" name="description" id="edit_description" rows="3"
                                                placeholder="Enter Designation description..."></textarea>
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

            $('#addDesignationForm').on('submit', function(e) {

                e.preventDefault();
                // Reset errors
                $('.error-text').text('');
                $('#formError').addClass('d-none').text('');

                $.ajax({
                    url: "{{ route('designation.create') }}",
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#addDesignations').modal('hide');
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

            // Edit Designation - Open Modal with Data
            $(document).on('click', '.edit-designation', function(e) {
                e.preventDefault();

                const updateUrl = $(this).data('update-url');
                const name = $(this).data('name');
                const description = $(this).data('description');
                const status = $(this).data('status');

                $('#editDesignationForm').attr('action', updateUrl);
                $('#edit_name').val(name);
                $('#edit_description').val(description);
                $('#edit_status').val(status).trigger('change');

                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                $('#editDesignationModal').modal('show');
            });

            // Edit Designation Form Submission
            $('#editDesignationForm').on('submit', function(e) {
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
                            $('#editDesignationModal').modal('hide');
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

            $('#addDesignations').on('hidden.bs.modal', function() {
                $('#addDesignationForm')[0].reset();
                $('.error-text').text('');
                $('#formError').addClass('d-none').text('');
            });

            $('#editDesignationModal').on('hidden.bs.modal', function() {
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
