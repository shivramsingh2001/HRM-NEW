@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ASSET VENDOR CARDS — all-blue theme, 4-per-row, compact spacing (mirrors client/leave/leave-type.blade.php) ==================== */
    .asset-vendor-card {
        border: 1px solid #eaeef5;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
        transition: all .2s ease;
    }

    .asset-vendor-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px -6px rgba(30, 50, 110, .14);
        border-color: #dfe5f0;
    }

    .asset-vendor-card .side-stick {
        background-color: #0D6EFD;
    }

    .avnd-title {
        font-size: 11.5px !important;
        font-weight: 700;
        color: #1a2236;
        max-width: 62%;
    }

    .avnd-date {
        font-size: 9px;
        color: #6b7385;
        margin-bottom: 0;
    }

    .avnd-badges {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .avnd-badge {
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

    .avnd-info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f4f6fb;
        border-radius: 8px;
        padding: 5px 8px;
    }

    .avnd-info-label {
        font-size: 8px;
        color: #6b7385;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .avnd-info-value {
        font-size: 9.5px;
        font-weight: 600;
        color: #1a2236;
    }

    .avnd-info-value i {
        color: var(--icon-color, #0D6EFD);
    }

    .avnd-description {
        color: #475569;
        font-size: 9.5px;
        line-height: 1.4;
    }

    .avnd-description i {
        color: var(--icon-color, #0D6EFD);
    }

    .avnd-card-footer {
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
    <x-ui.page-header class="content-area-header sticky-top" title="Asset Vendor Management" current="Vendors" :crumbs="[['label' => 'Assets', 'url' => route('assets.index')]]">
        <x-slot:actions>
            <div class="hstack gap-2">
                <a href="javascript:void(0)" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#addVendorModal">
                        <i class="feather-plus me-2"></i>Add Vendor
                    </a>
            </div>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            <!--! BEGIN: [Single Note Item] !-->
            @forelse ($vendors as $v)
                <div class="col-xxl-3 col-xl-3 col-lg-3 col-md-6 col-sm-6 single-note-item">
                    <div class="card asset-vendor-card card-body mb-2 stretch stretch-full position-relative border-0">

                        <span class="side-stick"></span>

                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <!-- Title -->
                            <h5 class="avnd-title note-title text-truncate mb-0">
                                {{ $v->name }}
                            </h5>

                            <div class="avnd-badges">
                                <span class="avnd-badge {{ $v->status ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $v->status ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        <p class="avnd-date mb-1">
                            <i class="bi bi-calendar-event me-1"></i>
                            {{ date('d M Y', strtotime($v->created_at)) }}
                        </p>

                        <!-- Info Row -->
                        <div class="avnd-info-row mb-1">
                            <!-- Contact Person -->
                            <div>
                                <small class="avnd-info-label d-block">Contact Person</small>
                                <span class="avnd-info-value">
                                    <i class="bi bi-person-badge me-1 fs-12"></i>
                                    {{ $v->contact_person ?? '-' }}
                                </span>
                            </div>

                            <!-- Assets Count -->
                            <div class="text-end">
                                <small class="avnd-info-label d-block">Assets</small>
                                <span class="avnd-info-value">
                                    <i class="bi bi-box-seam me-1 fs-12"></i>
                                    {{ $v->assets_count }}
                                </span>
                            </div>
                        </div>

                        <!-- Contact Details -->
                        <div class="note-content flex-grow-1">
                            <p class="avnd-description text-truncate mb-1">
                                <i class="bi bi-telephone fs-10"></i> {{ $v->phone ?? 'No phone on file' }}
                            </p>
                            <p class="avnd-description text-truncate mb-0">
                                <i class="bi bi-envelope fs-10"></i> {{ $v->email ?? 'No email on file' }}
                            </p>
                        </div>

                        <div class="avnd-card-footer">
                            <!-- Edit Icon -->
                            <button type="button" class="action-btn edit-vendor" title="Edit Vendor"
                                data-id="{{ encrypt($v->id) }}" data-name="{{ $v->name }}"
                                data-contact_person="{{ $v->contact_person }}" data-phone="{{ $v->phone }}"
                                data-email="{{ $v->email }}" data-address="{{ $v->address }}"
                                data-tax_number="{{ $v->tax_number }}" data-status="{{ $v->status }}">
                                <i class="bi bi-pencil-square"></i>
                            </button>

                            <!-- Delete Icon -->
                            <button type="button" class="action-btn delete-vendor" title="Delete Vendor"
                                data-id="{{ encrypt($v->id) }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>

                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="empty-state">
                        <i class="bi bi-truck"></i>
                        <h4>No Vendors Found</h4>
                        <p>Get started by adding your first asset vendor</p>
                    </div>
                </div>
            @endforelse
            <!--! BEGIN: [Single Note Item] !-->
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Vendor Modal -->
    <x-ui.modal id="addVendorModal" title="Add Vendor">
        <form action="{{ route('asset-vendors.store') }}" id="addVendorForm">
            @csrf
            <div id="addVendorError" class="alert alert-danger d-none"></div>
            <div class="row">
                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="name">Name *</label>
                        <input type="text" class="form-control" name="name" required
                            id="name" placeholder="e.g. Acme Computers Pvt. Ltd.">
                        <small class="text-danger error-text name_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="contact_person">Contact Person</label>
                        <input type="text" class="form-control" name="contact_person"
                            id="contact_person" placeholder="e.g. John Doe">
                        <small class="text-danger error-text contact_person_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="phone">Phone</label>
                        <input type="text" class="form-control" name="phone"
                            id="phone" placeholder="e.g. +91 98765 43210">
                        <small class="text-danger error-text phone_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="email">Email</label>
                        <input type="email" class="form-control" name="email"
                            id="email" placeholder="e.g. sales@vendor.com">
                        <small class="text-danger error-text email_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="tax_number">Tax Number</label>
                        <input type="text" class="form-control" name="tax_number"
                            id="tax_number" placeholder="e.g. GSTIN / Tax ID">
                        <small class="text-danger error-text tax_number_error"></small>
                    </div>
                </div>

                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="address">Address</label>
                        <textarea class="form-control" name="address" id="address" rows="2"
                            placeholder="Vendor's mailing address..."></textarea>
                        <small class="text-danger error-text address_error"></small>
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

    <!-- Edit Vendor Modal -->
    <x-ui.modal id="editVendorModal" title="Edit Vendor">
        <form action="#" id="editVendorForm">
            @csrf
            <div id="editVendorError" class="alert alert-danger d-none"></div>
            <div class="row">
                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_vendor_name">Name *</label>
                        <input type="text" class="form-control" name="name" required
                            id="edit_vendor_name" placeholder="e.g. Acme Computers Pvt. Ltd.">
                        <small class="text-danger error-text name_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_vendor_contact_person">Contact Person</label>
                        <input type="text" class="form-control" name="contact_person"
                            id="edit_vendor_contact_person" placeholder="e.g. John Doe">
                        <small class="text-danger error-text contact_person_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_vendor_phone">Phone</label>
                        <input type="text" class="form-control" name="phone"
                            id="edit_vendor_phone" placeholder="e.g. +91 98765 43210">
                        <small class="text-danger error-text phone_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_vendor_email">Email</label>
                        <input type="email" class="form-control" name="email"
                            id="edit_vendor_email" placeholder="e.g. sales@vendor.com">
                        <small class="text-danger error-text email_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_vendor_tax_number">Tax Number</label>
                        <input type="text" class="form-control" name="tax_number"
                            id="edit_vendor_tax_number" placeholder="e.g. GSTIN / Tax ID">
                        <small class="text-danger error-text tax_number_error"></small>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_vendor_status">Status *</label>
                        <select class="form-control" name="status" id="edit_vendor_status" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        <small class="text-danger error-text status_error"></small>
                    </div>
                </div>

                <div class="col-12 mb-3">
                    <div class="form-group">
                        <label class="fw-semibold" for="edit_vendor_address">Address</label>
                        <textarea class="form-control" name="address" id="edit_vendor_address" rows="2"
                            placeholder="Vendor's mailing address..."></textarea>
                        <small class="text-danger error-text address_error"></small>
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

            $('#addVendorForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#addVendorError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#addVendorModal').modal('hide');
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
                            $('#addVendorError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // Edit Vendor - Open Modal with Data
            $(document).on('click', '.edit-vendor', function(e) {
                e.preventDefault();

                const el = $(this);
                const id = el.data('id');
                $('#editVendorForm').attr('action', "{{ url('asset-vendors') }}/" + id + '/update');
                $('#edit_vendor_name').val(el.data('name'));
                $('#edit_vendor_contact_person').val(el.data('contact_person'));
                $('#edit_vendor_phone').val(el.data('phone'));
                $('#edit_vendor_email').val(el.data('email'));
                $('#edit_vendor_address').val(el.data('address'));
                $('#edit_vendor_tax_number').val(el.data('tax_number'));
                $('#edit_vendor_status').val(el.data('status') ? '1' : '0').trigger('change');

                $('.error-text').text('');
                $('#editVendorError').addClass('d-none').text('');

                $('#editVendorModal').modal('show');
            });

            // Edit Vendor Form Submission
            $('#editVendorForm').on('submit', function(e) {
                e.preventDefault();

                $('.error-text').text('');
                $('#editVendorError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#editVendorModal').modal('hide');
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
                            $('#editVendorError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // Delete Vendor
            $(document).on('click', '.delete-vendor', function() {
                if (!confirm('Delete this vendor?')) return;
                const id = $(this).data('id');
                $.ajax({
                    url: "{{ url('asset-vendors') }}/" + id,
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

            $('#addVendorModal').on('hidden.bs.modal', function() {
                $('#addVendorForm')[0].reset();
                $('.error-text').text('');
                $('#addVendorError').addClass('d-none').text('');
            });

            $('#editVendorModal').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#editVendorError').addClass('d-none').text('');
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
