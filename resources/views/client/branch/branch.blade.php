{{-- resources/views/client/branch/branch.blade.php
     Company Branch — organizational profile (name/address/contact/manager).
     Separate from client/attendance-location/attendance-location.blade.php
     (attendance geofencing). Mirrors client/department/department.blade.php. --}}
@extends('client.layout.master')

@section('style')
<style>
    /* ==================== BRANCH CARDS — all-blue theme, 4-per-row, compact spacing (mirrors client/department/department.blade.php) ==================== */
    .branch-card {
        border: 1px solid #eaeef5;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
        transition: all .2s ease;
    }

    .branch-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px -6px rgba(30, 50, 110, .14);
        border-color: #dfe5f0;
    }

    .branch-card .side-stick { background-color: #1e3a8a; }

    .br-title {
        font-size: 11.5px !important;
        font-weight: 700;
        color: #1a2236;
        max-width: 72%;
    }

    .br-address {
        font-size: 9px;
        color: #6b7385;
        margin-bottom: 0;
    }

    .br-badge {
        padding: 2px 7px;
        border-radius: 30px;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: .2px;
        white-space: nowrap;
    }

    .badge-active { background: #3b82f6; color: #fff; }
    .badge-inactive { background: #1e3a8a; color: #fff; }

    .br-info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f4f6fb;
        border-radius: 8px;
        padding: 5px 8px;
    }

    .br-info-label {
        font-size: 8px;
        color: #6b7385;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .br-info-value {
        font-size: 9.5px;
        font-weight: 600;
        color: #1a2236;
    }

    .br-info-value i { color: #1e3a8a; }

    .br-description {
        color: #475569;
        font-size: 9.5px;
        line-height: 1.4;
    }

    .br-card-footer {
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

    .action-btn:hover { background: #1e3a8a; color: #fff; border-color: #1e3a8a; }
    .action-btn.danger:hover { background: #dc2626; border-color: #dc2626; }

    /* ==================== EMPTY STATE ==================== */
    .empty-state {
        padding: 36px 20px;
        text-align: center;
        background: linear-gradient(145deg, #ffffff 0%, #f4f6fb 100%);
        border-radius: 14px;
    }

    .empty-state i { font-size: 48px; color: #93c5fd; margin-bottom: 12px; }
    .empty-state h4 { color: #1a2236; font-size: 14px; font-weight: 600; margin-bottom: 6px; }
    .empty-state p { color: #6b7385; font-size: 11.5px; margin-bottom: 0; }

    /* ==================== COMPACT MODAL — core chrome centralized in
       public/assets/css/theme-custom.css; only this page's own extras here. ==================== */
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
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">Branch Management</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Branch</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                @if(in_array($role,['admin','hr']))
                <div class="dropdown d-none d-sm-flex">
                    <a href="javascript:void(0)" class="btn btn-light-brand btn-sm rounded-pill" data-bs-toggle="modal"
                        data-bs-target="#addBranchModal">
                        <i class="feather-plus me-2"></i>Add Branch
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            @forelse ($branches as $branch)
                <div class="col-xxl-3 col-xl-3 col-lg-3 col-md-6 col-sm-6 single-note-item">
                    <div class="card branch-card card-body mb-2 stretch stretch-full position-relative border-0">

                        <span class="side-stick"></span>

                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h5 class="br-title note-title text-truncate mb-0">
                                {{ $branch->name }}
                            </h5>
                            <span class="br-badge {{ $branch->status ? 'badge-active' : 'badge-inactive' }}">
                                {{ $branch->status ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <p class="br-address mb-1">
                            <i class="bi bi-geo-alt me-1"></i>
                            {{ collect([$branch->city, $branch->state, $branch->country])->filter()->implode(', ') ?: 'No address on file' }}
                        </p>

                        <div class="br-info-row mb-1">
                            <div>
                                <small class="br-info-label d-block">Branch Manager</small>
                                <span class="br-info-value">
                                    <i class="bi bi-person-badge me-1"></i>
                                    {{ $branch->branchHead->name ?? 'NA' }}
                                </span>
                            </div>
                            <div class="text-end">
                                <small class="br-info-label d-block">Employees</small>
                                <span class="br-info-value">
                                    <i class="bi bi-people-fill me-1"></i>
                                    {{ $branch->employees_count ?? 0 }}
                                </span>
                            </div>
                        </div>

                        <div class="note-content flex-grow-1">
                            <p class="br-description text-truncate-3-line mb-0">
                                {{ $branch->description ?? 'No description available.' }}
                            </p>
                        </div>

                        <div class="br-card-footer">
                            <a href="{{ route('branch.detail', ['id' => encrypt($branch->id)]) }}"
                                class="action-btn" title="View Details">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                            @if(in_array($role,['admin','hr']))
                            <a href="javascript:void(0)" class="action-btn edit-branch" title="Edit Branch"
                                data-update-url="{{ route('branch.update', ['id' => encrypt($branch->id)]) }}"
                                data-name="{{ $branch->name }}"
                                data-description="{{ $branch->description }}"
                                data-address="{{ $branch->address }}"
                                data-city="{{ $branch->city }}"
                                data-state="{{ $branch->state }}"
                                data-country="{{ $branch->country }}"
                                data-postal-code="{{ $branch->postal_code }}"
                                data-phone="{{ $branch->phone }}"
                                data-email="{{ $branch->email }}"
                                data-branch-head="{{ $branch->branch_head }}"
                                data-status="{{ $branch->status }}">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <a href="javascript:void(0)" class="action-btn danger delete-branch" title="Delete Branch"
                                data-delete-url="{{ route('branch.delete', ['id' => encrypt($branch->id)]) }}"
                                data-name="{{ $branch->name }}">
                                <i class="bi bi-trash"></i>
                            </a>
                            @endif
                        </div>

                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="empty-state">
                        <i class="bi bi-building"></i>
                        <h4>No Branches Found</h4>
                        <p>Get started by adding your first branch</p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Branch Modal -->
    <div class="modal fade-scale" id="addBranchModal" tabindex="-1" aria-labelledby="addBranchModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add Branch</span>
                    </h2>
                    <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="#" id="addBranchForm">
                                <div id="formError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="name">Branch Name *</label>
                                            <input type="text" class="form-control" name="name" required
                                                id="name" placeholder="Enter branch name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="status">Status *</label>
                                            <select class="form-control" name="status" id="status" required>
                                                <option value="1" selected>Active</option>
                                                <option value="0">Inactive</option>
                                            </select>
                                            <small class="text-danger error-text status_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="branch_head">Branch Manager</label>
                                            <select class="form-control" name="branch_head" id="branch_head">
                                                <option value="">-- None --</option>
                                                @foreach ($users as $u)
                                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text branch_head_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="address">Address</label>
                                            <textarea class="form-control" name="address" id="address" rows="2"
                                                placeholder="Street address..."></textarea>
                                            <small class="text-danger error-text address_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="city">City</label>
                                            <input type="text" class="form-control" name="city" id="city">
                                            <small class="text-danger error-text city_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="state">State</label>
                                            <input type="text" class="form-control" name="state" id="state">
                                            <small class="text-danger error-text state_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="country">Country</label>
                                            <input type="text" class="form-control" name="country" id="country">
                                            <small class="text-danger error-text country_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="postal_code">Postal Code</label>
                                            <input type="text" class="form-control" name="postal_code" id="postal_code">
                                            <small class="text-danger error-text postal_code_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="phone">Phone</label>
                                            <input type="text" class="form-control" name="phone" id="phone">
                                            <small class="text-danger error-text phone_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="email">Email</label>
                                            <input type="email" class="form-control" name="email" id="email">
                                            <small class="text-danger error-text email_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="description">Description</label>
                                            <textarea class="form-control" name="description" id="description" rows="2"
                                                placeholder="Enter branch description..."></textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="feather-save me-2"></i>Save
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0)" class="btn btn-modal-cancel float-end" data-bs-dismiss="modal">
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

    <!-- Edit Branch Modal -->
    <div class="modal fade-scale" id="editBranchModal" tabindex="-1" aria-labelledby="editBranchModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Branch</span>
                    </h2>
                    <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="#" id="editBranchForm">
                                <div id="editFormError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_name">Branch Name *</label>
                                            <input type="text" class="form-control" name="name" required id="edit_name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
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
                                            <label class="fw-semibold" for="edit_branch_head">Branch Manager</label>
                                            <select class="form-control" name="branch_head" id="edit_branch_head">
                                                <option value="">-- None --</option>
                                                @foreach ($users as $u)
                                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text branch_head_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_address">Address</label>
                                            <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                                            <small class="text-danger error-text address_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_city">City</label>
                                            <input type="text" class="form-control" name="city" id="edit_city">
                                            <small class="text-danger error-text city_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_state">State</label>
                                            <input type="text" class="form-control" name="state" id="edit_state">
                                            <small class="text-danger error-text state_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_country">Country</label>
                                            <input type="text" class="form-control" name="country" id="edit_country">
                                            <small class="text-danger error-text country_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_postal_code">Postal Code</label>
                                            <input type="text" class="form-control" name="postal_code" id="edit_postal_code">
                                            <small class="text-danger error-text postal_code_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_phone">Phone</label>
                                            <input type="text" class="form-control" name="phone" id="edit_phone">
                                            <small class="text-danger error-text phone_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_email">Email</label>
                                            <input type="email" class="form-control" name="email" id="edit_email">
                                            <small class="text-danger error-text email_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_description">Description</label>
                                            <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="feather-save me-2"></i>Update
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0)" class="btn btn-modal-cancel float-end" data-bs-dismiss="modal">
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

    <!-- Delete Confirmation Modal -->
    <x-ui.modal id="deleteBranchModal" title="Delete Branch">
        <div id="deleteFormError" class="alert alert-danger d-none"></div>
        <p class="fs-12 mb-3">Are you sure you want to delete <strong id="deleteBranchName"></strong>? This cannot be undone.</p>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBranch">
                <i class="feather-trash-2 me-2"></i>Delete
            </button>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="modal">Cancel</button>
        </div>
    </x-ui.modal>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {

            $('#addBranchForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#formError').addClass('d-none').text('');

                $.ajax({
                    url: "{{ route('branch.store') }}",
                    type: "POST",
                    data: $(this).serialize(),
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(response) {
                        if (response.success) {
                            $('#addBranchModal').modal('hide');
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) { $('.' + key + '_error').text(value[0]); });
                            toastr.error('Please fix the validation errors');
                        } else {
                            $('#formError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            $(document).on('click', '.edit-branch', function(e) {
                e.preventDefault();
                const el = $(this);
                $('#editBranchForm').attr('action', el.data('update-url'));
                $('#edit_name').val(el.data('name'));
                $('#edit_description').val(el.data('description'));
                $('#edit_address').val(el.data('address'));
                $('#edit_city').val(el.data('city'));
                $('#edit_state').val(el.data('state'));
                $('#edit_country').val(el.data('country'));
                $('#edit_postal_code').val(el.data('postal-code'));
                $('#edit_phone').val(el.data('phone'));
                $('#edit_email').val(el.data('email'));
                $('#edit_branch_head').val(el.data('branch-head') || '');
                $('#edit_status').val(el.data('status'));

                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
                $('#editBranchModal').modal('show');
            });

            $('#editBranchForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: $(this).serialize(),
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(response) {
                        if (response.success) {
                            $('#editBranchModal').modal('hide');
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) { $('.' + key + '_error').text(value[0]); });
                            toastr.error('Please fix the validation errors');
                        } else {
                            $('#editFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            let deleteBranchUrl = null;
            $(document).on('click', '.delete-branch', function(e) {
                e.preventDefault();
                deleteBranchUrl = $(this).data('delete-url');
                $('#deleteBranchName').text($(this).data('name'));
                $('#deleteFormError').addClass('d-none').text('');
                $('#deleteBranchModal').modal('show');
            });

            $('#confirmDeleteBranch').on('click', function() {
                if (!deleteBranchUrl) return;
                $.ajax({
                    url: deleteBranchUrl,
                    type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(response) {
                        if (response.success) {
                            $('#deleteBranchModal').modal('hide');
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        $('#deleteFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                    }
                });
            });

            $('#addBranchModal').on('hidden.bs.modal', function() {
                $('#addBranchForm')[0].reset();
                $('.error-text').text('');
                $('#formError').addClass('d-none').text('');
            });

            $('#editBranchModal').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
            });

            if (typeof toastr !== 'undefined') {
                toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 3000 };
            }
        });
    </script>
@endsection
