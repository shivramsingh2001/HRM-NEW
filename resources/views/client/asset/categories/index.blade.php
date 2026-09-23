@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ASSET CATEGORY CARDS — all-blue theme, 4-per-row, compact spacing (mirrors client/leave/leave-type.blade.php) ==================== */
    .asset-category-card {
        border: 1px solid #eaeef5;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
        transition: all .2s ease;
    }

    .asset-category-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px -6px rgba(30, 50, 110, .14);
        border-color: #dfe5f0;
    }

    .asset-category-card .side-stick {
        background-color: #1e3a8a;
    }

    .acat-title {
        font-size: 11.5px !important;
        font-weight: 700;
        color: #1a2236;
        max-width: 62%;
    }

    .acat-date {
        font-size: 9px;
        color: #6b7385;
        margin-bottom: 0;
    }

    .acat-badges {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .acat-badge {
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

    .acat-info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f4f6fb;
        border-radius: 8px;
        padding: 5px 8px;
    }

    .acat-info-label {
        font-size: 8px;
        color: #6b7385;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .acat-info-value {
        font-size: 9.5px;
        font-weight: 600;
        color: #1a2236;
    }

    .acat-info-value i {
        color: #1e3a8a;
    }

    .acat-description {
        color: #475569;
        font-size: 9.5px;
        line-height: 1.4;
    }

    .acat-card-footer {
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
                <h5 class="m-b-10">Asset Category Management</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('assets.index') }}">Assets</a></li>
                <li class="breadcrumb-item">Categories</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                <div class="dropdown d-none d-sm-flex">
                    <a href="javascript:void(0)" class="btn btn-light-brand btn-sm rounded-pill" data-bs-toggle="modal"
                        data-bs-target="#addCategoryModal">
                        <i class="feather-plus me-2"></i>Add Category
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            <!--! BEGIN: [Single Note Item] !-->
            @forelse ($categories as $c)
                <div class="col-xxl-3 col-xl-3 col-lg-3 col-md-6 col-sm-6 single-note-item">
                    <div class="card asset-category-card card-body mb-2 stretch stretch-full position-relative border-0">

                        <span class="side-stick"></span>

                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <!-- Title -->
                            <h5 class="acat-title note-title text-truncate mb-0">
                                {{ $c->name }}
                            </h5>

                            <div class="acat-badges">
                                <span class="acat-badge {{ $c->status ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $c->status ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        <p class="acat-date mb-1">
                            <i class="bi bi-calendar-event me-1"></i>
                            {{ date('d M Y', strtotime($c->created_at)) }}
                        </p>

                        <!-- Info Row -->
                        <div class="acat-info-row mb-1">
                            <!-- Code -->
                            <div>
                                <small class="acat-info-label d-block">Code</small>
                                <span class="acat-info-value">
                                    <i class="bi bi-hash me-1"></i>
                                    {{ $c->code ?? '-' }}
                                </span>
                            </div>

                            <!-- Assets Count -->
                            <div class="text-end">
                                <small class="acat-info-label d-block">Assets</small>
                                <span class="acat-info-value">
                                    <i class="bi bi-box-seam me-1"></i>
                                    {{ $c->assets_count }}
                                </span>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="note-content flex-grow-1">
                            <p class="acat-description text-truncate-3-line mb-0">
                                {{ $c->description ?? 'No description provided.' }}
                            </p>
                        </div>

                        <div class="acat-card-footer">
                            <!-- Edit Icon -->
                            <button type="button" class="action-btn edit-category" title="Edit Category"
                                data-id="{{ encrypt($c->id) }}" data-name="{{ $c->name }}" data-code="{{ $c->code }}"
                                data-description="{{ $c->description }}" data-status="{{ $c->status }}">
                                <i class="bi bi-pencil-square"></i>
                            </button>

                            <!-- Delete Icon -->
                            <button type="button" class="action-btn delete-category" title="Delete Category"
                                data-id="{{ encrypt($c->id) }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>

                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="empty-state">
                        <i class="bi bi-tags"></i>
                        <h4>No Categories Found</h4>
                        <p>Get started by adding your first asset category</p>
                    </div>
                </div>
            @endforelse
            <!--! BEGIN: [Single Note Item] !-->
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Category Modal -->
    <x-ui.modal id="addCategoryModal" title="Add Category">
        <form action="{{ route('asset-categories.store') }}" id="addCategoryForm">
            @csrf
            <div id="addCategoryError" class="alert alert-danger d-none"></div>
            <div class="row">
                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="name">Name *</label>
                        <input type="text" class="form-control" name="name" required
                            id="name" placeholder="e.g. Laptops">
                        <small class="text-danger error-text name_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="code">Code</label>
                        <input type="text" class="form-control" name="code" maxlength="20"
                            id="code" placeholder="e.g. LAP">
                        <small class="text-danger error-text code_error"></small>
                    </div>
                </div>

                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="description">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="3"
                            placeholder="Short description of this category..."></textarea>
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

    <!-- Edit Category Modal -->
    <x-ui.modal id="editCategoryModal" title="Edit Category">
        <form action="#" id="editCategoryForm">
            @csrf
            <div id="editCategoryError" class="alert alert-danger d-none"></div>
            <div class="row">
                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_cat_name">Name *</label>
                        <input type="text" class="form-control" name="name" required
                            id="edit_cat_name" placeholder="e.g. Laptops">
                        <small class="text-danger error-text name_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_cat_code">Code</label>
                        <input type="text" class="form-control" name="code" maxlength="20"
                            id="edit_cat_code" placeholder="e.g. LAP">
                        <small class="text-danger error-text code_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_cat_status">Status *</label>
                        <select class="form-control" name="status" id="edit_cat_status" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        <small class="text-danger error-text status_error"></small>
                    </div>
                </div>

                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_cat_description">Description</label>
                        <textarea class="form-control" name="description" id="edit_cat_description" rows="3"
                            placeholder="Short description of this category..."></textarea>
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
            const csrfToken = $('meta[name="csrf-token"]').attr('content');

            $('#addCategoryForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#addCategoryError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#addCategoryModal').modal('hide');
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
                            $('#addCategoryError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // Edit Category - Open Modal with Data
            $(document).on('click', '.edit-category', function(e) {
                e.preventDefault();

                const id = $(this).data('id');
                $('#editCategoryForm').attr('action', "{{ url('asset-categories') }}/" + id + '/update');
                $('#edit_cat_name').val($(this).data('name'));
                $('#edit_cat_code').val($(this).data('code'));
                $('#edit_cat_description').val($(this).data('description'));
                $('#edit_cat_status').val($(this).data('status') ? '1' : '0').trigger('change');

                $('.error-text').text('');
                $('#editCategoryError').addClass('d-none').text('');

                $('#editCategoryModal').modal('show');
            });

            // Edit Category Form Submission
            $('#editCategoryForm').on('submit', function(e) {
                e.preventDefault();

                $('.error-text').text('');
                $('#editCategoryError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#editCategoryModal').modal('hide');
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
                            $('#editCategoryError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // Delete Category
            $(document).on('click', '.delete-category', function() {
                if (!confirm('Delete this category?')) return;
                const id = $(this).data('id');
                $.ajax({
                    url: "{{ url('asset-categories') }}/" + id,
                    type: 'DELETE',
                    data: {
                        _token: csrfToken
                    },
                    success: function(res) {
                        toastr.success(res.message);
                        setTimeout(() => location.reload(), 800);
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Delete failed.');
                    }
                });
            });

            $('#addCategoryModal').on('hidden.bs.modal', function() {
                $('#addCategoryForm')[0].reset();
                $('.error-text').text('');
                $('#addCategoryError').addClass('d-none').text('');
            });

            $('#editCategoryModal').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#editCategoryError').addClass('d-none').text('');
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
