@extends('client.layout.master')

@section('style')
<style>
    .project-head-badge {
        background-color: #1e3a8a;
        color: white;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 3px;
        margin-left: 5px;
    }

    /* ==================== PROJECT MODALS - small font, blue theme ==================== */
    #addProjectModal .modal-header,
    #editProjectModal .modal-header {
        background: #fff !important;
        border-bottom: 1px solid #edf2f7 !important;
        padding: 10px 16px !important;
    }
    #addProjectModal .modal-header .fs-18,
    #editProjectModal .modal-header h5 {
        font-size: 13px !important;
        color: #1e293b !important;
        font-weight: 700;
    }
    #addProjectModal .form-group label,
    #editProjectModal .form-group label {
        font-size: 11px !important;
    }
    #addProjectModal .form-control,
    #editProjectModal .form-control {
        font-size: 11.5px !important;
        padding: 6px 10px !important;
    }
    #addProjectModal .btn,
    #editProjectModal .btn {
        font-size: 11.5px !important;
        padding: 6px 14px !important;
    }
    #addProjectModal .btn-primary,
    #editProjectModal .btn-primary {
        background: #1e3a8a !important;
        border-color: #1e3a8a !important;
    }
    #addProjectModal .btn-primary:hover,
    #editProjectModal .btn-primary:hover {
        background: #16295e !important;
        border-color: #16295e !important;
    }
    #addProjectModal .btn-modal-cancel,
    #editProjectModal .btn-modal-cancel {
        background: #eef3fd !important;
        border: 1px solid #bfd3f7 !important;
        color: #1e3a8a !important;
    }
    #addProjectModal .btn-modal-cancel:hover,
    #editProjectModal .btn-modal-cancel:hover {
        background: #dbeafe !important;
        color: #1e3a8a !important;
    }

    /* Toast Container Styles */
    #toast-container {
        z-index: 1090;
    }
    
    .toast {
        font-size: 0.875rem;
        backdrop-filter: blur(10px);
        margin-bottom: 0.5rem;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
</style>
@endsection

@php
 $user = Auth::user();
 $role = $user->role;
 @endphp
 
 
@section('content-area')
    <!-- Toast Container -->
    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;"></div>
    
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <!--<h5 class="m-b-10">Projects</h5>-->
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Projects</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                <div class="dropdown d-none d-sm-flex">
                     @if(in_array($role,['admin','hr']))
                    <a href="#" class="btn btn-light-brand btn-sm rounded-pill" data-bs-toggle="modal"
                        data-bs-target="#addProjectModal">Add Project</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            @php
                $projectData = [];
            @endphp
            
            @foreach ($projects as $project)
                @php
                    // Get project head user
                    $projectHead = $project->headUser;
                    $projectHeadName = $projectHead->name ?? 'N/A';
                    
                    // Count team members (excluding head or including head based on controller logic)
                    // Backend handles whether head is included in count
                    $totalMembers = $project->assigns()->where('status', 1)->count();
                    
                    // Get project deadline status
                    $today = date('Y-m-d');
                    $deadline = $project->deadline_date;
                    $isOverdue = $deadline < $today;
                    
                    $currentStatus = $project->status ?? 'pending';
                    
                    // Store project data for JavaScript
                    $projectData[] = [
                        'id' => $project->id,
                        'name' => $project->name,
                        'deadline' => $deadline,
                        'isOverdue' => $isOverdue,
                        'status' => $currentStatus,
                        'formatted_date' => date('d M Y', strtotime($deadline))
                    ];
                @endphp
                
                <div class="col-xxl-4 col-xl-6 col-lg-4 col-sm-6 single-note-item">
                    <div class="card card-body mb-4 stretch stretch-full position-relative shadow-sm border-0">

                        <!-- Top Right Actions -->
                        <div class="position-absolute top-0 end-0 d-flex align-items-center gap-2 m-4">
                            @php
                                $statusColors = [
                                    'ongoing' => 'info',
                                    'pending' => 'warning',
                                    'hold' => 'secondary',
                                    'completed' => 'success',
                                    'cancelled' => 'danger',
                                ];
                                $statusLabels = [
                                    'ongoing' => 'Ongoing',
                                    'pending' => 'Pending',
                                    'hold' => 'On Hold',
                                    'completed' => 'Completed',
                                    'cancelled' => 'Cancelled',
                                ];
                            @endphp
                            <!-- Status Badge -->
                            <span class="badge bg-{{ $statusColors[$currentStatus] ?? 'secondary' }}">
                                {{ $statusLabels[$currentStatus] ?? ucfirst($currentStatus) }}
                            </span>

                            <!-- View Icon -->
                            <a href="{{ route('project.view-details', ['id' => encrypt($project->id)]) }}"
                                class="text-decoration-none">
                                <i class="bi bi-eye-fill fs-5 text-muted"></i>
                            </a>
                        </div>

                        <span class="side-stick"></span>

                        <!-- Project Name -->
                        <h5 class="note-title text-truncate w-75 mb-1">
                            {{ $project->name }}
                        </h5>

                        <!-- Project Code -->
                       
                        <small class="text-muted fs-10 mb-1">
                            <i class="bi bi-tag me-1 fs-10"></i>
                            {{ $project->project_code ?? 'N/A' }}
                        </small>

                        <!-- Deadline Warning -->
                        @if($isOverdue && $currentStatus != 'completed' && $currentStatus != 'cancelled')
                        <small class="text-danger fs-11">
                            <i class="bi bi-exclamation-triangle fs-11 me-1"></i>
                            Deadline passed on {{ date('d M Y', strtotime($deadline)) }}
                        </small>
                        @endif

                        <!-- Date Info -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted fs-10">
                                <i class="bi bi-calendar-plus me-1"></i>
                                {{ date('d M Y', strtotime($project->start_date)) }}
                            </small>
                            <small class="text-muted fs-10">
                                <i class="bi bi-calendar-check me-1"></i>
                                {{ date('d M Y', strtotime($project->deadline_date)) }}
                            </small>
                        </div>

                        <!-- Project Head & Team -->
                        <div class="d-flex align-items-center bg-light rounded-3 px-3 py-2 mb-2">
                            <div class="flex-grow-1">
                                <small class="text-muted d-block fs-10">Project Head</small>
                                <span class="fw-semibold fs-11">
                                    <i class="bi bi-person-badge fs-14 me-1 text-primary"></i>
                                    {{ $projectHeadName }}
                                </span>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block fs-10">Team Size</small>
                                <span class="fw-bold text-dark">
                                    <i class="bi bi-people-fill text-success me-1"></i>
                                    {{ $totalMembers }}
                                </span>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="note-content flex-grow-1">
                            <p class="text-muted fs-12 text-truncate-3-line">
                                {{ $project->description ?? 'No description available.' }}
                            </p>
                        </div>

                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Project Modal -->
    <div class="modal fade-scale" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add New Project</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="{{ route('project.create') }}" id="addProjectForm">
                                @csrf
                                <div id="addFormError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="name">Project Name *</label>
                                            <input type="text" class="form-control" name="name" required
                                                id="name" placeholder="Enter Project name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="project_head">Project Head *</label>
                                            <select class="form-control select2" name="project_head" id="project_head"
                                                required>
                                                <option value="">-- Select Project Head --</option>
                                                @foreach ($users as $user)
                                                    <option value="{{ $user->id }}">{{ ucfirst($user->name) }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text project_head_error"></small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="start_date">Start Date *</label>
                                            <input type="date" class="form-control" name="start_date"
                                                id="start_date" required>
                                            <small class="text-danger error-text start_date_error"></small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="deadline_date">Deadline Date *</label>
                                            <input type="date" class="form-control" name="deadline_date"
                                                id="deadline_date" required>
                                            <small class="text-danger error-text deadline_date_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="member">Team Members *</label>
                                            <select class="form-control select2" multiple name="member[]" id="member"
                                                required>
                                                <option value="">-- Select Team Members --</option>
                                                @foreach ($users as $user)
                                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">Select at least one team member</div>
                                            <small class="text-danger error-text member_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="description">Description</label>
                                            <textarea class="form-control" name="description" id="description" rows="3"
                                                placeholder="Enter project description..."></textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">
                                                Cancel
                                            </button>
                                            <button class="btn btn-primary" type="submit">
                                                <i class="feather feather-save me-2"></i>Save Project
                                            </button>
                                        </div>
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
        // Toast notification function that auto-removes after 20 seconds
        function showToast(message, type = 'info', duration = 20000) {
            const toastId = 'toast-' + Date.now();
            const iconMap = {
                'danger': 'bi-exclamation-triangle-fill',
                'warning': 'bi-exclamation-circle-fill',
                'success': 'bi-check-circle-fill',
                'info': 'bi-info-circle-fill'
            };
            
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body text-white">
                            <i class="bi ${iconMap[type] || 'bi-info-circle-fill'} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            $('#toast-container').append(toastHtml);
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, { delay: duration });
            toast.show();
            
            // Auto remove after 20 seconds
            setTimeout(() => {
                if (toastElement) {
                    $(toastElement).fadeOut(300, function() {
                        $(this).remove();
                    });
                }
            }, duration);
        }
        
       
        // Set minimum dates
        const today = new Date().toISOString().split('T')[0];
        $('#start_date').attr('max', today);
        $('#deadline_date').attr('min', today);

        // Update deadline min date when start date changes
        $('#start_date').on('change', function() {
            $('#deadline_date').attr('min', $(this).val());
        });

        // Add Project Form Submission
        $('#addProjectForm').on('submit', function(e) {
            e.preventDefault();
            // Reset errors
            $('.error-text').text('');
            $('#addFormError').addClass('d-none').text('');

            // Get form data
            const formData = new FormData(this);

            $.ajax({
                url: $(this).attr('action'),
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                beforeSend: function() {
                    $('#addProjectForm button[type="submit"]').prop('disabled', true)
                        .html('<i class="feather feather-loader me-2"></i>Saving...');
                },
                success: function(response) {
                    $('#addProjectForm button[type="submit"]').prop('disabled', false)
                        .html('<i class="feather feather-save me-2"></i>Save Project');

                    if (response.success) {
                        // Show success toast
                        showToast('Project created successfully! ✅', 'success');
                        
                        $('#addProjectModal').modal('hide');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    }
                },
                error: function(xhr) {
                    $('#addProjectForm button[type="submit"]').prop('disabled', false)
                        .html('<i class="feather feather-save me-2"></i>Save Project');

                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            $('.' + key + '_error').text(value[0]);
                        });
                        // Show error toast
                        showToast('Please fix the validation errors! ❌', 'danger');
                    } else {
                        $('#addFormError')
                            .removeClass('d-none')
                            .text(xhr.responseJSON?.message || 'Something went wrong.');
                        // Show error toast
                        showToast('Something went wrong! Please try again. ❌', 'danger');
                    }
                }
            });
        });

        // Clear form when modal is closed
        $('#addProjectModal').on('hidden.bs.modal', function() {
            $('#addProjectForm')[0].reset();
            $('.error-text').text('');
            $('#addFormError').addClass('d-none').text('');
            // Reset Select2
            $('.select2').val(null).trigger('change');
            // Reset min dates
            $('#start_date').attr('min', today);
            $('#deadline_date').attr('min', today);
        });
    });
</script>
@endsection