@extends('client.layout.master')

@section('style')
<style>
    /* ==================== CARD STYLES ==================== */
    .announcement-card {
        background: white;
        border-radius: 16px;
        border: 1px solid #edf2f7;
        transition: all 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    }

    .announcement-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.15);
        border-color: #cbd5e1;
    }

    .announcement-image-wrapper {
        position: relative;
        width: 100%;
        height: 200px;
        overflow: hidden;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        top: 15px;
        right: 15px;
        padding: 6px 12px;
        border-radius: 30px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.3px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        z-index: 2;
    }

    .badge-active {
        background: #10b981;
        color: white;
    }

    .badge-inactive {
        background: #ef4444;
        color: white;
    }

    .announcement-content {
        padding: 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .announcement-title {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 12px;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .announcement-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f1f5f9;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 14px;
        flex-shrink: 0;
    }

    .user-info {
        flex: 1;
    }

    .user-name {
        font-weight: 600;
        color: #1e293b;
        font-size: 14px;
        margin-bottom: 2px;
    }

    .user-email {
        font-size: 11px;
        color: #64748b;
    }

    .announcement-description {
        color: #475569;
        font-size: 13px;
        line-height: 1.6;
        margin-bottom: 20px;
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
        padding-top: 15px;
        border-top: 1px solid #f1f5f9;
        margin-top: auto;
    }

    .file-attachment {
        display: flex;
        align-items: center;
        gap: 6px;
        background: #f8fafc;
        padding: 6px 12px;
        border-radius: 30px;
        font-size: 11px;
        color: #475569;
        transition: all 0.2s;
        text-decoration: none;
    }

    .file-attachment:hover {
        background: #eef2ff;
        color: #4f46e5;
    }

    .file-attachment i {
        font-size: 14px;
    }

    .acknowledge-badge {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 30px;
    }

    .acknowledge-badge.required {
        background: #dbeafe;
        color: #1e40af;
    }

    .acknowledge-badge.not-required {
        background: #f1f5f9;
        color: #475569;
    }

    .action-dropdown {
        position: relative;
    }

    .action-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        transition: all 0.2s;
        cursor: pointer;
    }

    .action-btn:hover {
        background: #4f46e5;
        color: white;
        border-color: #4f46e5;
    }

    .dropdown-menu {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        padding: 8px;
        min-width: 180px;
    }

    .dropdown-item {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }

    .dropdown-item:hover {
        background: #f1f5f9;
    }

    .dropdown-item i {
        font-size: 16px;
        color: #64748b;
    }

    .dropdown-item:hover i {
        color: #4f46e5;
    }

    .dropdown-divider {
        margin: 8px 0;
        border-color: #e2e8f0;
    }

    /* ==================== FILTER SECTION ==================== */
    .filter-wrapper {
        background: white;
        border-radius: 16px;
        border: 1px solid #edf2f7;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    }

    .filter-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 15px;
    }

    .filter-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
    }

    .filter-title i {
        color: #4f46e5;
        font-size: 18px;
    }

    .filter-select {
        width: 200px;
        height: 40px;
        padding: 8px 16px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        color: #1e293b;
        background: #f8fafc;
        cursor: pointer;
        transition: all 0.2s;
    }

    .filter-select:hover {
        background: white;
        border-color: #4f46e5;
    }

    .filter-select:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    .reset-btn {
        height: 40px;
        padding: 0 20px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        color: #64748b;
        font-size: 13px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: all 0.2s;
    }

    .reset-btn:hover {
        background: white;
        border-color: #4f46e5;
        color: #4f46e5;
    }

    /* ==================== STATS CARDS ==================== */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stats-card {
        background: white;
        border: 1px solid #edf2f7;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        transition: all 0.2s;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    }

    .stats-card:hover {
        box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.15);
        border-color: #cbd5e1;
        transform: translateY(-2px);
    }

    .stats-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 16px;
    }

    .stats-icon i {
        font-size: 26px;
    }

    .stats-info h3 {
        font-size: 26px;
        font-weight: 700;
        margin: 0 0 4px 0;
        color: #1e293b;
        line-height: 1.2;
    }

    .stats-info p {
        font-size: 13px;
        color: #64748b;
        margin: 0;
    }

    /* ==================== CARD GRID ==================== */
    .cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    /* ==================== PAGINATION ==================== */
    .pagination {
        margin: 0;
        gap: 6px;
        justify-content: center;
    }

    .page-link {
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 13px;
        padding: 8px 14px;
        border-radius: 10px !important;
        transition: all 0.2s;
        background: white;
    }

    .page-link:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #1e293b;
        transform: translateY(-1px);
    }

    .page-item.active .page-link {
        background: #4f46e5;
        border-color: #4f46e5;
    }

    .page-item.disabled .page-link {
        background: #f8fafc;
        color: #94a3b8;
    }

    .pagination-info {
        color: #64748b;
        font-size: 13px;
    }

    /* ==================== EMPTY STATE ==================== */
    .empty-state {
        padding: 60px 24px;
        text-align: center;
        background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 24px;
        /* margin: 24px; */
    }

    .empty-state i {
        font-size: 80px;
        color: #cbd5e1;
        margin-bottom: 20px;
    }

    .empty-state h4 {
        color: #334155;
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .empty-state p {
        color: #64748b;
        font-size: 14px;
        margin-bottom: 24px;
    }

    .empty-state .btn {
        padding: 10px 24px;
        border-radius: 12px;
        font-size: 14px;
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
                <h5 class="m-b-10">Announcement Management</h5>
            </div>
            <ul class="breadcrumb" >
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">All Announcements</li>
            </ul>
        </div>
     
        <div class="page-header-right ms-auto">
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
        </div>
    </div>

    <div class="content-area-body pb-0 h-100">
        <!-- Statistics Cards -->
        <!--<div class="stats-grid">-->
        <!--    <div class="stats-card">-->
        <!--        <div class="stats-icon" style="background: #eef2ff;">-->
        <!--            <i class="feather-check-circle" style="color: #4f46e5;"></i>-->
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
                            <img src="{{ asset($announcement->image) }}" 
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
                                    <a href="{{ asset($announcement->file) }}" 
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
                                           data-image="{{ $announcement->image }}"
                                           data-file="{{ $announcement->file }}"
                                           data-acknowledge="{{ $announcement->acknowledge }}"
                                           data-status="{{ $announcement->status }}">
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

                // Populate the edit form
                $('#edit_id').val(id);
                $('#edit_title').val(title);
                $('#edit_description').val(description);
                $('#edit_status').val(status);
                
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