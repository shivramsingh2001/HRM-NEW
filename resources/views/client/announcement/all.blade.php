@extends('client.layout.master')

@section('style')
<style>
    /* ==================== CARD STYLES — all-blue theme, compact spacing ==================== */
    .announcement-card {
        background: white;
        border-radius: 12px;
        border: 1px solid #eaeef5;
        transition: all 0.2s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
    }

    .announcement-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 18px -6px rgba(30, 50, 110, .14);
        border-color: #dfe5f0;
    }

    .announcement-image-wrapper {
        position: relative;
        width: 100%;
        height: 140px;
        overflow: hidden;
        background: linear-gradient(135deg, #0D6EFD, #0D6EFD);
    }

    .announcement-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .announcement-card:hover .announcement-image {
        transform: scale(1.05);
    }

    .announcement-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        padding: 3px 9px;
        border-radius: 30px;
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: 0.2px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        z-index: 2;
    }

    .badge-active {
        background: #3b82f6;
        color: white;
    }

    .badge-inactive {
        background: #0D6EFD;
        color: white;
    }

    .expire-badge {
        position: absolute;
        top: 8px;
        left: 8px;
        padding: 3px 9px;
        border-radius: 30px;
        font-size: 9.5px;
        font-weight: 700;
        background: rgba(255, 255, 255, .92);
        color: #0D6EFD;
        z-index: 2;
    }

    .expire-badge.expired { background: #0D6EFD; color: #fff; }

    .announcement-content {
        padding: 12px 14px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .announcement-title {
        font-size: 13px;
        font-weight: 700;
        color: #1a2236;
        margin-bottom: 6px;
        line-height: 1.35;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .announcement-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        padding-bottom: 8px;
        border-bottom: 1px solid #eaeef5;
    }

    .user-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0D6EFD, #0D6EFD);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 10.5px;
        flex-shrink: 0;
    }

    .user-info {
        flex: 1;
        min-width: 0;
    }

    .user-name {
        font-weight: 600;
        color: #1a2236;
        font-size: 11px;
        margin-bottom: 1px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-email {
        font-size: 9.5px;
        color: #6b7385;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .announcement-description {
        color: #475569;
        font-size: 11px;
        line-height: 1.5;
        margin-bottom: 10px;
        flex: 1;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .announcement-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 8px;
        border-top: 1px solid #eaeef5;
        margin-top: auto;
    }

    .file-attachment {
        display: flex;
        align-items: center;
        gap: 4px;
        background: #f4f6fb;
        padding: 3px 9px;
        border-radius: 30px;
        font-size: 9.5px;
        color: #475569;
        transition: all 0.2s;
        text-decoration: none;
    }

    .file-attachment:hover {
        background: #EFF6FF;
        color: #0D6EFD;
    }

    .file-attachment i {
        font-size: 12px;
    }

    .acknowledge-badge {
        display: flex;
        align-items: center;
        gap: 3px;
        font-size: 9.5px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 30px;
    }

    .acknowledge-badge.required {
        background: #dbeafe;
        color: #0D6EFD;
    }

    .acknowledge-badge.not-required {
        background: #eef1f7;
        color: #6b7385;
    }

    .action-dropdown {
        position: relative;
    }



    .dropdown-menu {
        border: 1px solid #eaeef5;
        border-radius: 10px;
        box-shadow: 0 8px 20px -5px rgba(20, 30, 60, .12);
        padding: 6px;
        min-width: 160px;
    }

    .dropdown-item {
        padding: 6px 12px;
        border-radius: 7px;
        font-size: 11.5px;
        color: #1a2236;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
    }

    .dropdown-item:hover {
        background: #f4f6fb;
    }

    .dropdown-item i {
        font-size: 14px;
        color: #6b7385;
    }

    .dropdown-item:hover i {
        color: var(--icon-color, #0D6EFD);
    }

    .dropdown-divider {
        margin: 6px 0;
        border-color: #eaeef5;
    }





    .filter-select {
        width: 180px;
        height: 32px;
        padding: 4px 12px;
        border: 1px solid #dfe5f0;
        border-radius: 8px;
        font-size: 11.5px;
        color: #1a2236;
        background: #f4f6fb;
        cursor: pointer;
        transition: all 0.2s;
    }

    .filter-select:hover {
        background: white;
        border-color: #0D6EFD;
    }

    .filter-select:focus {
        outline: none;
        border-color: #0D6EFD;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
    }



    /* ==================== STATS CARDS ==================== */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: .75rem;
        margin-bottom: 1rem;
    }

    .stats-card {
        background: white;
        border: 1px solid #eaeef5;
        border-radius: 10px;
        padding: 12px 14px;
        display: flex;
        align-items: center;
        transition: all 0.2s;
        box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
    }

    .stats-card:hover {
        box-shadow: 0 4px 12px -4px rgba(30, 50, 110, .12);
        border-color: #dfe5f0;
        transform: translateY(-1px);
    }

    .stats-icon {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
        background: #EFF6FF;
    }

    .stats-icon i {
        font-size: 15px;
        color: var(--icon-color, #0D6EFD);
    }

    .stats-info h3 {
        font-size: 17px;
        font-weight: 700;
        margin: 0 0 2px 0;
        color: #1a2236;
        line-height: 1.2;
    }

    .stats-info p {
        font-size: 11px;
        color: #6b7385;
        margin: 0;
    }

    /* ==================== CARD GRID ==================== */
    .cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: .85rem;
        margin-bottom: 1rem;
    }







    /* ==================== EMPTY STATE ==================== */
    .empty-state {
        padding: 36px 20px;
        text-align: center;
        background: linear-gradient(145deg, #ffffff 0%, #f4f6fb 100%);
        border-radius: 14px;
        /* margin: 24px; */
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
        margin-bottom: 14px;
    }

    .empty-state .btn {
        padding: 6px 16px;
        border-radius: 8px;
        font-size: 11.5px;
        font-weight: 500;
    }

    /* ==================== RESPONSIVE ==================== */
    @media (max-width: 768px) {
        .cards-grid {
            grid-template-columns: 1fr;
        }

        .filter-select {
            width: 100%;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }
    }

    /* ==================== COMPACT MODAL (Edit) — core chrome
       (max-width/header/body/card/row/label/btn) is centralized in
       client.layout.head; only this page's own extras stay here. ==================== */
    .compact-modal .modal-header .fs-14 { font-size: 13px !important; }
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
    <x-ui.page-header class="content-area-header sticky-top" title="Announcement Management" current="All Announcements">
        <x-slot:actions>
            <div class="hstack gap-2">
                <!--@if(in_array($role,['admin','hr']))-->
                <!--<div class="dropdown d-none d-sm-flex">-->
                <!--    <a href="#" class="btn btn-primary" data-bs-toggle="modal"-->
                <!--        data-bs-target="#addAnnouncementModal">-->
                <!--        <i class="feather-plus me-2"></i>Add Announcement-->
                <!--    </a>-->
                <!--</div>-->
                <!--@endif-->
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body pb-0 h-100">
        <!-- Statistics Cards -->
        <!--<div class="stats-grid">-->
        <!--    <div class="stats-card">-->
        <!--        <div class="stats-icon" style="background: #eef2ff;">-->
        <!--            <i class="feather-check-circle" style="color: var(--icon-color, #0D6EFD);"></i>-->
        <!--        </div>-->
        <!--        <div class="stats-info">-->
        <!--            <h3>{{ $totalAnnouncements ?? 0 }}</h3>-->
        <!--            <p>Total Announcements</p>-->
        <!--        </div>-->
        <!--    </div>-->
            
        <!--    <div class="stats-card">-->
        <!--        <div class="stats-icon" style="background: #d1fae5;">-->
        <!--            <i class="feather-check-circle" style="color: #10b981;"></i>-->
        <!--        </div>-->
        <!--        <div class="stats-info">-->
        <!--            <h3>{{ $activeAnnouncements ?? 0 }}</h3>-->
        <!--            <p>Active</p>-->
        <!--        </div>-->
        <!--    </div>-->
            
        <!--    <div class="stats-card">-->
        <!--        <div class="stats-icon" style="background: #fee2e2;">-->
        <!--            <i class="feather-x-circle" style="color: #ef4444;"></i>-->
        <!--        </div>-->
        <!--        <div class="stats-info">-->
        <!--            <h3>{{ $inactiveAnnouncements ?? 0 }}</h3>-->
        <!--            <p>Inactive</p>-->
        <!--        </div>-->
        <!--    </div>-->
        <!--</div>-->

      
        
        <!-- Announcements Grid -->
        <div class="cards-grid">
            @forelse($announcements ?? [] as $announcement)
                <div class="announcement-card">
                    <div class="announcement-image-wrapper">
                        @if($announcement->image)
                            <img src="{{ file_url($announcement->image, 'announcement_image') }}" 
                                 alt="{{ $announcement->title }}" 
                                 class="announcement-image">
                        @else
                            <div class="announcement-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                                <i class="feather-megaphone" style="font-size: 60px; color: rgba(255,255,255,0.3);"></i>
                            </div>
                        @endif
                        
                        <span class="announcement-badge {{ $announcement->status == 1 ? 'badge-active' : 'badge-inactive' }}">
                            <i class="feather-{{ $announcement->status == 1 ? 'check-circle' : 'x-circle' }} me-1"></i>
                            {{ $announcement->status == 1 ? 'Active' : 'Inactive' }}
                        </span>

                        @if($announcement->expire_date)
                            @php $isExpired = \Carbon\Carbon::parse($announcement->expire_date)->lt(now()->startOfDay()); @endphp
                            <span class="expire-badge {{ $isExpired ? 'expired' : '' }}">
                                {{ $isExpired ? 'Expired' : 'Expires' }} {{ \Carbon\Carbon::parse($announcement->expire_date)->format('d M Y') }}
                            </span>
                        @endif
                    </div>
                    
                    <div class="announcement-content">
                        <h5 class="announcement-title">{{ $announcement->title }}</h5>
                        
                        <div class="announcement-meta">
                            <div class="user-avatar">
                                {{ strtoupper(substr($announcement->user_name, 0, 2)) }}
                            </div>
                            <div class="user-info">
                                <div class="user-name">{{ $announcement->user_name }} ({{ $announcement->user_employee_id }})</div>
                                <div class="user-email">{{ $announcement->user_email }}</div>
                            </div>
                        </div>
                        
                        @if($announcement->description)
                            <p class="announcement-description">{{ $announcement->description }}</p>
                        @endif
                        
                        <div class="announcement-footer">
                            <div class="d-flex align-items-center gap-2">
                                @if($announcement->file)
                                    <a href="{{ file_url($announcement->file, 'announcement_file') }}" 
                                       download 
                                       class="file-attachment">
                                        <i class="feather-paperclip"></i>
                                        Attachment
                                    </a>
                                @endif
                                
                                <span class="acknowledge-badge {{ $announcement->acknowledge == 1 ? 'required' : 'not-required' }}">
                                    <i class="feather-{{ $announcement->acknowledge == 1 ? 'check-square' : 'square' }}"></i>
                                    {{ $announcement->acknowledge == 1 ? 'Required' : 'Not Required' }}
                                </span>
                            </div>
                            
                            @if(in_array($role, ['admin', 'hr']))
                            <div class="dropdown">
                                <div class="action-btn" data-bs-toggle="dropdown">
                                    <i class="feather-more-vertical"></i>
                                </div>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item edit-announcement" href="#" 
                                           data-id="{{ $announcement->id }}"
                                           data-title="{{ $announcement->title }}"
                                           data-description="{{ $announcement->description }}"
                                           data-image="{{ file_url($announcement->image, 'announcement_image') }}"
                                           data-file="{{ file_url($announcement->file, 'announcement_file') }}"
                                           data-acknowledge="{{ $announcement->acknowledge }}"
                                           data-status="{{ $announcement->status }}"
                                           data-expire-date="{{ $announcement->expire_date ? \Carbon\Carbon::parse($announcement->expire_date)->format('Y-m-d') : '' }}">
                                            <i class="feather-edit-3"></i>
                                            <span>Edit</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="empty-state">
                        <i class="feather-megaphone"></i>
                        <h4>No Announcements Found</h4>
                        <p class="text-muted">Get started by adding your first announcement</p>
                       
                    </div>
                </div>
            @endforelse
        </div>
        
        <!-- Pagination -->
        @if (method_exists($announcements, 'links') && $announcements->hasPages())
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="pagination-info">
                    Showing {{ $announcements->firstItem() }} to {{ $announcements->lastItem() }} of {{ $announcements->total() }} entries
                </div>
                <div class="pagination">
                    {{ $announcements->appends(request()->query())->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection

@section('create-modal')
    <!-- Edit Announcement Modal -->
    <div class="modal fade-scale" id="editAnnouncementModal" tabindex="-1" aria-labelledby="editAnnouncementModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-14 fw-bold mb-1">Edit Announcement</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="editAnnouncementForm" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div id="editFormError" class="alert alert-danger d-none"></div>

                                <input type="hidden" name="id" id="edit_id">

                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_title">Title *</label>
                                            <input type="text" class="form-control" name="title" required
                                                id="edit_title" placeholder="Enter announcement title">
                                            <small class="text-danger error-text edit_title_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_image">Image (Max: 2MB)</label>
                                            <input type="file" class="form-control" name="image"
                                                id="edit_image" accept="image/*">
                                            <small class="text-danger error-text edit_image_error"></small>
                                            <div id="editImagePreviewContainer" class="mt-2">
                                                <img id="editImagePreview" class="image-preview" src="#" alt="Preview" style="display: none; width: 100px; height: 100px; object-fit: cover; border-radius: 8px;">
                                            </div>
                                            <div id="currentImage" class="small text-muted mt-1"></div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_file">Attachment (Max: 5MB)</label>
                                            <input type="file" class="form-control" name="file"
                                                id="edit_file" accept=".pdf,.doc,.docx,.txt">
                                            <small class="text-danger error-text edit_file_error"></small>
                                            <div id="currentFile" class="small text-muted mt-1"></div>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_description">Description</label>
                                            <textarea class="form-control" name="description" id="edit_description" rows="2"
                                                placeholder="Enter announcement description..."></textarea>
                                            <small class="text-danger error-text edit_description_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="acknowledge"
                                                id="edit_acknowledge" value="1">
                                            <label class="form-check-label" for="edit_acknowledge">
                                                Require acknowledgment from employees
                                            </label>
                                            <small class="text-danger error-text edit_acknowledge_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_expire_date">Expires On (optional)</label>
                                            <input type="date" class="form-control" name="expire_date" id="edit_expire_date">
                                            <small class="text-danger error-text edit_expire_date_error"></small>
                                        </div>
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
                                        <a href="#" class="btn btn-modal-cancel float-end"
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
            // Image preview for add form
            $('#image').change(function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#imagePreview').attr('src', e.target.result);
                        $('#imagePreviewContainer').show();
                    }
                    reader.readAsDataURL(file);
                }
            });

            // File info for add form
            $('#file').change(function() {
                const file = this.files[0];
                if (file) {
                    $('#fileInfo').text(`Selected: ${file.name} (${(file.size/1024).toFixed(2)} KB)`).show();
                }
            });

            // Edit Announcement - Open Modal with Data
            $(document).on('click', '.edit-announcement', function(e) {
                e.preventDefault();
                
                // Get data from the clicked row
                const id = $(this).data('id');
                const title = $(this).data('title');
                const description = $(this).data('description');
                const image = $(this).data('image');
                const file = $(this).data('file');
                const acknowledge = $(this).data('acknowledge');
                const status = $(this).data('status');
                const expireDate = $(this).data('expire-date');

                // Populate the edit form
                $('#edit_id').val(id);
                $('#edit_title').val(title);
                $('#edit_description').val(description);
                $('#edit_status').val(status);
                $('#edit_expire_date').val(expireDate || '');

                // Set acknowledge checkbox
                if (acknowledge == 1) {
                    $('#edit_acknowledge').prop('checked', true);
                } else {
                    $('#edit_acknowledge').prop('checked', false);
                }

                // Show current image if exists
                if (image) {
                    $('#currentImage').html(`Current image: <a href="${image}" target="_blank">View</a>`);
                } else {
                    $('#currentImage').html('');
                }

                // Show current file if exists
                if (file) {
                    $('#currentFile').html(`Current file: <a href="${file}" target="_blank">Download</a>`);
                } else {
                    $('#currentFile').html('');
                }

                // Clear previous errors and preview
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
                $('#editImagePreview').hide();

                // Show the modal
                $('#editAnnouncementModal').modal('show');
            });

            // Image preview for edit form
            $('#edit_image').change(function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#editImagePreview').attr('src', e.target.result).show();
                        $('#currentImage').html('');
                    }
                    reader.readAsDataURL(file);
                }
            });

            // Edit Announcement Form Submission
            $('#editAnnouncementForm').on('submit', function(e) {
                e.preventDefault();
                
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();
                let formData = new FormData(this);
                formData.append('_method', 'PUT');

                $.ajax({
                    url: "{{ route('announcement.update', '') }}/" + id,
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#editAnnouncementModal').modal('hide');
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.edit_' + key + '_error').text(value[0]);
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

            // Clear form when modal is closed
            $('#addAnnouncementModal').on('hidden.bs.modal', function() {
                $('#addAnnouncementForm')[0].reset();
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');
                $('#imagePreviewContainer').hide();
                $('#fileInfo').hide();
            });

            $('#editAnnouncementModal').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
                $('#editImagePreview').hide();
                $('#currentImage').html('');
                $('#currentFile').html('');
            });

            // Initialize toastr
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