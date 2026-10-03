@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ASSET TYPE CARDS — all-blue theme, 4-per-row, compact spacing (mirrors client/leave/leave-type.blade.php) ==================== */
    .asset-type-card {
        border: 1px solid #eaeef5;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
        transition: all .2s ease;
    }

    .asset-type-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px -6px rgba(30, 50, 110, .14);
        border-color: #dfe5f0;
    }

    .asset-type-card .side-stick {
        background-color: #0D6EFD;
    }

    .atype-title {
        font-size: 11.5px !important;
        font-weight: 700;
        color: #1a2236;
        max-width: 62%;
    }

    .atype-date {
        font-size: 9px;
        color: #6b7385;
        margin-bottom: 0;
    }

    .atype-badges {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .atype-badge {
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

    .atype-info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f4f6fb;
        border-radius: 8px;
        padding: 5px 8px;
        gap: 6px;
    }

    .atype-info-label {
        font-size: 8px;
        color: #6b7385;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .atype-info-value {
        font-size: 9.5px;
        font-weight: 600;
        color: #1a2236;
    }

    .atype-info-value i {
        color: var(--icon-color, #0D6EFD);
    }

    .atype-description {
        color: #475569;
        font-size: 9.5px;
        line-height: 1.4;
    }

    .atype-card-footer {
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

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Asset Type Management" current="Types" :crumbs="[['label' => 'Assets', 'url' => route('assets.index')]]">
        <x-slot:actions>
            <div class="hstack gap-2">
                <a href="javascript:void(0)" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#addTypeModal">
                        <i class="feather-plus me-2"></i>Add Type
                    </a>
            </div>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            <!--! BEGIN: [Single Note Item] !-->
            @forelse ($types as $t)
                <div class="col-xxl-3 col-xl-3 col-lg-3 col-md-6 col-sm-6 single-note-item">
                    <div class="card asset-type-card card-body mb-2 stretch stretch-full position-relative border-0">

                        <span class="side-stick"></span>

                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <!-- Title -->
                            <h5 class="atype-title note-title text-truncate mb-0">
                                {{ $t->name }}
                            </h5>

                            <div class="atype-badges">
                                <span class="atype-badge {{ $t->status ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $t->status ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        <p class="atype-date mb-1">
                            <i class="bi bi-calendar-event me-1"></i>
                            {{ date('d M Y', strtotime($t->created_at)) }}
                        </p>

                        <!-- Info Row -->
                        <div class="atype-info-row mb-1">
                            <!-- Category -->
                            <div>
                                <small class="atype-info-label d-block">Category</small>
                                <span class="atype-info-value">
                                    <i class="bi bi-tag me-1"></i>
                                    {{ $t->category->name ?? '-' }}
                                </span>
                            </div>

                            <!-- Assets Count -->
                            <div class="text-end">
                                <small class="atype-info-label d-block">Assets</small>
                                <span class="atype-info-value">
                                    <i class="bi bi-box-seam me-1"></i>
                                    {{ $t->assets_count }}
                                </span>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="note-content flex-grow-1">
                            <p class="atype-description text-truncate-3-line mb-0">
                                {{ $t->description ?? 'No description provided.' }}
                            </p>
                        </div>

                        <div class="atype-card-footer">
                            <!-- Edit Icon -->
                            <button type="button" class="action-btn edit-type" title="Edit Type"
                                data-id="{{ encrypt($t->id) }}" data-name="{{ $t->name }}" data-code="{{ $t->code }}"
                                data-category="{{ $t->asset_category_id }}" data-description="{{ $t->description }}"
                                data-status="{{ $t->status }}">
                                <i class="bi bi-pencil-square"></i>
                            </button>

                            <!-- Delete Icon -->
                            <button type="button" class="action-btn delete-type" title="Delete Type"
                                data-id="{{ encrypt($t->id) }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>

                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="empty-state">
                        <i class="bi bi-tags"></i>
                        <h4>No Types Found</h4>
                        <p>Get started by adding your first asset type</p>
                    </div>
                </div>
            @endforelse
            <!--! BEGIN: [Single Note Item] !-->
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Type Modal -->
    <x-ui.modal id="addTypeModal" title="Add Type">
        <form action="{{ route('asset-types.store') }}" id="addTypeForm">
            @csrf
            <div id="addTypeError" class="alert alert-danger d-none"></div>
            <div class="row">
                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="asset_category_id">Category</label>
                        <select class="form-control" name="asset_category_id" id="asset_category_id">
                            <option value="">-- Uncategorized --</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-danger error-text asset_category_id_error"></small>
                    </div>
                </div>
                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="name">Name *</label>
                        <input type="text" class="form-control" name="name" required
                            id="name" placeholder="e.g. Laptop">
                        <small class="text-danger error-text name_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="code">Code</label>
                        <input type="text" class="form-control" name="code" maxlength="20"
                            id="code" placeholder="e.g. LAP-01">
                        <small class="text-danger error-text code_error"></small>
                    </div>
                </div>

                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="description">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="3"
                            placeholder="Short description of this type..."></textarea>
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

    <!-- Edit Type Modal -->
    <x-ui.modal id="editTypeModal" title="Edit Type">
        <form action="#" id="editTypeForm">
            @csrf
            <div id="editTypeError" class="alert alert-danger d-none"></div>
            <div class="row">
                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_type_category">Category</label>
                        <select class="form-control" name="asset_category_id" id="edit_type_category">
                            <option value="">-- Uncategorized --</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-danger error-text asset_category_id_error"></small>
                    </div>
                </div>
                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_type_name">Name *</label>
                        <input type="text" class="form-control" name="name" required
                            id="edit_type_name" placeholder="e.g. Laptop">
                        <small class="text-danger error-text name_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_type_code">Code</label>
                        <input type="text" class="form-control" name="code" maxlength="20"
                            id="edit_type_code" placeholder="e.g. LAP-01">
                        <small class="text-danger error-text code_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_type_status">Status *</label>
                        <select class="form-control" name="status" id="edit_type_status" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        <small class="text-danger error-text status_error"></small>
                    </div>
                </div>

                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_type_description">Description</label>
                        <textarea class="form-control" name="description" id="edit_type_description" rows="3"
                            placeholder="Short description of this type..."></textarea>
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

            $('#addTypeForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#addTypeError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#addTypeModal').modal('hide');
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
                            $('#addTypeError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // Edit Type - Open Modal with Data
            $(document).on('click', '.edit-type', function(e) {
                e.preventDefault();

                const id = $(this).data('id');
                $('#editTypeForm').attr('action', "{{ url('asset-types') }}/" + id + '/update');
                $('#edit_type_name').val($(this).data('name'));
                $('#edit_type_code').val($(this).data('code'));
                $('#edit_type_category').val($(this).data('category')).trigger('change');
                $('#edit_type_description').val($(this).data('description'));
                $('#edit_type_status').val($(this).data('status') ? '1' : '0').trigger('change');

                $('.error-text').text('');
                $('#editTypeError').addClass('d-none').text('');

                $('#editTypeModal').modal('show');
            });

            // Edit Type Form Submission
            $('#editTypeForm').on('submit', function(e) {
                e.preventDefault();

                $('.error-text').text('');
                $('#editTypeError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#editTypeModal').modal('hide');
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
                            $('#editTypeError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // Delete Type
            $(document).on('click', '.delete-type', function() {
                if (!confirm('Delete this type?')) return;
                const id = $(this).data('id');
                $.ajax({
                    url: "{{ url('asset-types') }}/" + id,
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

            $('#addTypeModal').on('hidden.bs.modal', function() {
                $('#addTypeForm')[0].reset();
                $('.error-text').text('');
                $('#addTypeError').addClass('d-none').text('');
            });

            $('#editTypeModal').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#editTypeError').addClass('d-none').text('');
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
