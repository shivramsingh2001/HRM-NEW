@extends('client.layout.master')

@section('style')
<style>
    .image-preview {
        width: 100px;
        height: 100px;
        border-radius: 8px;
        object-fit: cover;
        border: 1px solid #e2e8f0;
        margin-top: 10px;
    }
    .file-info {
        font-size: 12px;
        color: #64748b;
        margin-top: 5px;
    }
     /* ==================== ALL-BLUE THEME, COMPACT SPACING ==================== */
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
        background: #e3edfe;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
        flex: none;
    }

    .stats-icon i {
        font-size: 15px;
        color: #1e3a8a;
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

    /* every stat tile shares the same soft-blue icon chip — single-color theme */
    .stats-card.total .stats-icon,
    .stats-card.active .stats-icon,
    .stats-card.inactive .stats-icon,
    .stats-card.pending .stats-icon {
        background: #e3edfe;
    }
    .stats-card.total .stats-icon i { color: #1e3a8a; }
    .stats-card.active .stats-icon i { color: #2563eb; }
    .stats-card.inactive .stats-icon i { color: #1d4ed8; }
    .stats-card.pending .stats-icon i { color: #0ea5e9; }

    /* ==================== PAGINATION ==================== */
    .pagination {
        margin: 0;
        gap: 4px;
    }

    .page-link {
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 11px;
        padding: 4px 10px;
        border-radius: 6px !important;
        transition: all 0.2s;
    }

    .page-link:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #1e293b;
    }

    .page-item.active .page-link {
        background: #1e3a8a;
        border-color: #1e3a8a;
    }

    /* ==================== EMPTY STATE ==================== */
    .empty-state {
        padding: 32px 20px;
        text-align: center;
        background: linear-gradient(145deg, #ffffff 0%, #f4f6fb 100%);
        border-radius: 14px;
        margin: 16px;
    }

    .empty-state i {
        font-size: 44px;
        color: #93c5fd;
        margin-bottom: 10px;
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

    /* ==================== TABLE / BADGES — small font, tight spacing, blue-only ==================== */
    #announcementList { font-size: 11.5px; }
    #announcementList th { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; color: #6b7385; padding: 6px 10px; }
    #announcementList td { padding: 6px 10px; vertical-align: middle; }
    #announcementList .badge { font-size: 10px; padding: 2px 8px; font-weight: 700; }
    #announcementList .badge.bg-success { background-color: #3b82f6 !important; }
    #announcementList .badge.bg-danger { background-color: #1e3a8a !important; }
    #announcementList .badge.bg-secondary { background-color: #93c5fd !important; color: #1e3a8a !important; }
    #announcementList .badge.bg-warning { background-color: #60a5fa !important; color: #fff !important; }
    .card-header .badge.bg-info { background-color: #0ea5e9 !important; }
    .card-header .badge.bg-success { background-color: #3b82f6 !important; }
    .card-title { font-size: 13px; }

    /* ==================== COMPACT MODAL (Add / Edit) — core chrome
       (max-width/header/body/card/row/label/btn) is centralized in
       client.layout.head; only this page's own extras stay here. ==================== */
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
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Announcement Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">My Announcements</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                @if(in_array($role,['admin','hr']))
                <div class="dropdown d-none d-sm-flex">
                    <a href="#" class="btn btn-light-brand btn-sm rounded-pill" data-bs-toggle="modal"
                        data-bs-target="#addAnnouncementModal">
                        <i class="feather-plus me-2"></i>Add Announcement
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="content-area-body pb-0 h-100">
        <div class="stats-grid">
            <div class="stats-card total">
                <div class="stats-icon">
                    <i class="feather-megaphone"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $totalAnnouncements ?? 0 }}</h3>
                    <p>Total Announcements</p>
                </div>
            </div>
            
            <div class="stats-card active">
                <div class="stats-icon">
                    <i class="feather-check-circle"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $activeAnnouncements ?? 0 }}</h3>
                    <p>Active</p>
                </div>
            </div>
            
            <div class="stats-card inactive">
                <div class="stats-icon">
                    <i class="feather-x-circle"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $inactiveAnnouncements ?? 0 }}</h3>
                    <p>Inactive</p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Announcements List</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info">
                                <i class="feather-list me-1"></i>Total: {{ $totalAnnouncements ?? 0 }}
                            </span>
                            <span class="badge bg-success">
                                <i class="feather-check me-1"></i>Active: {{ $activeAnnouncements ?? 0 }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover" id="announcementList">
                                <thead>
                                    <tr class="text-center">
                                        <th>S. No.</th>
                                        <th>Title</th>
                                        <th>Image</th>
                                        <th>File</th>
                                        <th>Description</th>
                                        <th>Acknowledge</th>
                                        <th>Status</th>
                                        <th>Expires</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($announcements ?? [] as $announcement)
                                        <tr class="text-center">
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $announcement->title ?? '' }}</td>
                                            <td>
                                                @if($announcement->image)
                                                    <img src="{{ asset($announcement->image) }}" 
                                                         alt="Announcement" 
                                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                                @else
                                                    <span class="text-muted">No Image</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($announcement->file)
                                                    @php
                                                        $fileExtension = pathinfo($announcement->file, PATHINFO_EXTENSION);
                                                        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];
                                                        $documentExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
                                                    @endphp
                                                    
                                                    @if(in_array(strtolower($fileExtension), $imageExtensions))
                                                        <div class="d-flex align-items-center">
                                                            <a href="{{ asset($announcement->file) }}" 
                                                               target="_blank" 
                                                               class="me-2"
                                                               data-lightbox="announcement-image"
                                                               data-title="Announcement Image">
                                                                <img src="{{ asset($announcement->file) }}" 
                                                                     alt="Announcement Image" 
                                                                     style="max-width: 50px; max-height: 50px; object-fit: cover;"
                                                                     class="img-thumbnail">
                                                            </a>
                                                        </div>
                                                    @elseif(in_array(strtolower($fileExtension), $documentExtensions) || $fileExtension == 'pdf')
                                                        <a href="{{ asset($announcement->file) }}" 
                                                           download 
                                                           class="text-primary">
                                                            <i class="feather-download"></i> 
                                                            <span class="text-uppercase small">({{ $fileExtension }})</span>
                                                        </a>
                                                    @else
                                                        <a href="{{ asset($announcement->file) }}" 
                                                           download 
                                                           class="text-primary">
                                                            <i class="feather-download"></i> File
                                                        </a>
                                                    @endif
                                                @else
                                                    <span class="text-muted">No File</span>
                                                @endif
                                            </td>
                                            <td>{{ Str::limit($announcement->description ?? '', 50) }}</td>
                                            <td>
                                                @if($announcement->acknowledge == 1)
                                                    <span class="badge bg-success">Required</span>
                                                @else
                                                    <span class="badge bg-secondary">Not Required</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($announcement->status == 1)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($announcement->expire_date)
                                                    @php $isExpired = \Carbon\Carbon::parse($announcement->expire_date)->lt(now()->startOfDay()); @endphp
                                                    <span class="badge {{ $isExpired ? 'bg-danger' : 'bg-warning' }}">
                                                        {{ $isExpired ? 'Expired ' : '' }}{{ \Carbon\Carbon::parse($announcement->expire_date)->format('d M Y') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <a href="#" class="avatar-text avatar-md"
                                                        data-bs-toggle="dropdown" data-bs-offset="0,21">
                                                        <i class="feather feather-more-horizontal"></i>
                                                    </a>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item edit-announcement" href="#"
                                                               data-id="{{ $announcement->id }}"
                                                               data-title="{{ $announcement->title }}"
                                                               data-description="{{ $announcement->description }}"
                                                               data-image="{{ $announcement->image }}"
                                                               data-file="{{ $announcement->file }}"
                                                               data-acknowledge="{{ $announcement->acknowledge }}"
                                                               data-status="{{ $announcement->status }}"
                                                               data-expire-date="{{ $announcement->expire_date ? \Carbon\Carbon::parse($announcement->expire_date)->format('Y-m-d') : '' }}">
                                                                <i class="feather feather-edit-3 me-3"></i>
                                                                <span>Edit</span>
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                <div class="empty-state">
                                                    <i class="feather-megaphone"></i>
                                                    <h4>No Announcements Found</h4>
                                                    <p class="text-muted">Get started by adding your first announcement</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Pagination Section -->
                    @if (method_exists($announcements, 'links') && $announcements->hasPages())
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    Showing {{ $announcements->firstItem() }} to {{ $announcements->lastItem() }} of
                                    {{ $announcements->total() }} entries
                                </div>
                                <div class="remove-internal-para">
                                    {{ $announcements->appends(request()->query())->links() }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Announcement Modal -->
    <div class="modal fade-scale" id="addAnnouncementModal" tabindex="-1" aria-labelledby="addAnnouncementModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add New Announcement</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="{{ route('announcement.store') }}" method="POST" id="addAnnouncementForm" enctype="multipart/form-data">
                                @csrf
                                <div id="addFormError" class="alert alert-danger d-none"></div>
                                
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="title">Title *</label>
                                            <input type="text" class="form-control" name="title" required
                                                id="title" placeholder="Enter announcement title">
                                            <small class="text-danger error-text title_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="image">Image (Max: 2MB)</label>
                                            <input type="file" class="form-control" name="image" 
                                                id="image" accept="image/*">
                                            <small class="text-danger error-text image_error"></small>
                                            <div id="imagePreviewContainer" class="mt-2" style="display: none;">
                                                <img id="imagePreview" class="image-preview" src="#" alt="Preview">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="file">Attachment (Max: 5MB)</label>
                                            <input type="file" class="form-control" name="file" 
                                                id="file" accept=".pdf,.doc,.docx,.txt">
                                            <small class="text-danger error-text file_error"></small>
                                            <div id="fileInfo" class="file-info" style="display: none;"></div>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="description">Description</label>
                                            <textarea class="form-control" name="description" id="description" rows="2"
                                                placeholder="Enter announcement description..."></textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="acknowledge"
                                                id="acknowledge" value="1">
                                            <label class="form-check-label" for="acknowledge">
                                                Require acknowledgment from employees
                                            </label>
                                            <small class="text-danger error-text acknowledge_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="expire_date">Expires On (optional)</label>
                                            <input type="date" class="form-control" name="expire_date" id="expire_date">
                                            <small class="text-danger error-text expire_date_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="feather-save me-2"></i>Save
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

    <!-- Edit Announcement Modal -->
    <div class="modal fade-scale" id="editAnnouncementModal" tabindex="-1" aria-labelledby="editAnnouncementModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Announcement</span>
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
                                                <img id="editImagePreview" class="image-preview" src="#" alt="Preview" style="display: none;">
                                            </div>
                                            <div id="currentImage" class="file-info"></div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_file">Attachment (Max: 5MB)</label>
                                            <input type="file" class="form-control" name="file" 
                                                id="edit_file" accept=".pdf,.doc,.docx,.txt">
                                            <small class="text-danger error-text edit_file_error"></small>
                                            <div id="currentFile" class="file-info"></div>
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

            // Add Announcement Form Submission
            $('#addAnnouncementForm').on('submit', function(e) {
                e.preventDefault();
                
                // Reset errors
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');

                // Create FormData for file upload
                let formData = new FormData(this);

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#addAnnouncementModal').modal('hide');
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
                            $('#addFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
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
                        $('#currentImage').html(''); // Hide current image text when new file selected
                    }
                    reader.readAsDataURL(file);
                }
            });

            // Edit Announcement Form Submission
            $('#editAnnouncementForm').on('submit', function(e) {
                e.preventDefault();
                
                // Reset errors
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();
                
                // Create FormData for file upload
                let formData = new FormData(this);
                formData.append('_method', 'PUT'); // For Laravel PUT method

                $.ajax({
                    url: "{{ route('announcement.update', '') }}/" + id,
                    type: "POST", // Using POST with _method=PUT
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