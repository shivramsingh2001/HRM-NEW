@extends('client.layout.master')

@section('style')
<style>
    /* Toast Container Styles */
    #toast-container {
        z-index: 1090;
    }
    
    .toast {
        font-size: 0.875rem;
        backdrop-filter: blur(10px);
        margin-bottom: 0.5rem;
        animation: slideInRight 0.3s ease-out;
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
    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3"></div>
    
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <!--<h5 class="m-b-10">Project Details</h5>-->
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('project.index') }}">Projects</a></li>
                <li class="breadcrumb-item">Project Details</li>
            </ul>
        </div>

        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                @if ($project->status == 'ongoing')
                    <span class="badge bg-info">Ongoing</span>
                @elseif($project->status == 'completed')
                    <span class="badge bg-success">Completed</span>
                @elseif($project->status == 'pending')
                    <span class="badge bg-warning">Pending</span>
                @elseif($project->status == 'hold')
                    <span class="badge bg-secondary">On Hold</span>
                @elseif($project->status == 'cancelled')
                    <span class="badge bg-danger">Cancelled</span>
                @endif
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">
        <div class="row">
            <div class="col-lg-12">
                <div class="card border-top-0">
                    <div class="card-header p-0">
                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="myTab"
                            role="tablist">
                            <li class="nav-item flex-fill border-top" role="presentation">
                                <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab"
                                    data-bs-target="#profileTab" role="tab">Project Information</a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <!-- Project Information Tab -->
                        <div class="tab-pane fade show active" id="profileTab" role="tabpanel">
                            <div class="card-body personal-info">
                                <div class="mb-4 d-flex align-items-center justify-content-between">
                                    <h5 class="fw-bold mb-0 me-4">
                                        <span class="d-block mb-2">Project Information:</span>
                                        <span class="fs-12 fw-normal text-muted text-truncate-1-line">
                                            Project ID: #{{ str_pad($project->id, 5, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </h5>
                                    <div>
                                         @if(in_array($role,['admin','hr','manager']))
                                        <a href="javascript:void(0);" class="btn btn-warning me-2" data-bs-toggle="modal"
                                            data-bs-target="#editProjectModal">
                                            <i class="feather-edit me-1"></i>Edit Project
                                        </a>
                                        @endif
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">Project Name: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-briefcase"></i></div>
                                            <input type="text" value="{{ $project->name }}" class="form-control" readonly
                                                placeholder="Project Name">
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">Project Head: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-user"></i></div>
                                            <input type="text" value="{{ $project->headUser->name ?? 'N/A' }}"
                                                class="form-control" readonly placeholder="Project Head">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">Start Date: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-clock"></i></div>
                                            <input type="text"
                                                value="{{ \Carbon\Carbon::parse($project->start_date)->format('d M, Y') }}"
                                                class="form-control" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">Deadline Date: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-calendar"></i></div>
                                            <input type="text"
                                                value="{{ \Carbon\Carbon::parse($project->deadline_date)->format('d M, Y') }}"
                                                class="form-control" readonly>
                                            @if (\Carbon\Carbon::parse($project->deadline_date)->isPast() && $project->status != 'completed' && $project->status != 'cancelled')
                                                <span class="input-group-text bg-danger text-white">
                                                    <i class="feather-alert-triangle"></i> Overdue
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">Project Status: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-activity"></i></div>
                                            <select class="form-control" id="projectStatus" disabled>
                                                <option value="ongoing"
                                                    {{ $project->status == 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                                                <option value="pending"
                                                    {{ $project->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                                <option value="hold" {{ $project->status == 'hold' ? 'selected' : '' }}>On
                                                    Hold</option>
                                                <option value="completed"
                                                    {{ $project->status == 'completed' ? 'selected' : '' }}>Completed
                                                </option>
                                                <option value="cancelled"
                                                    {{ $project->status == 'cancelled' ? 'selected' : '' }}>Cancelled
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-start">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">Description: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-file-text"></i></div>
                                            <textarea class="form-control" rows="4" readonly placeholder="Project Description">{{ $project->description }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-0">
                            <div class="card-body personal-info">
                                <div
                                    class="mb-4 d-flex flex-column flex-md-row align-items-center justify-content-between">
                                    <h5 class="fw-bold mb-0 me-4">
                                        <span class="d-block mb-2">Members :</span>
                                    </h5>
                                </div>
                                <hr class="my-0">
                                <div class="row mb-4 align-items-center">
                                    <div class="col-12">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr class="text-center">
                                                        <th>S. No.</th>
                                                        <th>Name</th>
                                                        <th>Designation</th>
                                                        <th>Department</th>
                                                        <th>Assigned Date</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    @foreach ($project->assigns->where('status', '1') as $index => $assign)
                                                        <tr class="text-center">
                                                            <td>{{ $index + 1 }}</td>
                                                            <td>
                                                                <div
                                                                    class="d-flex align-items-center justify-content-center">
                                                                    <div>
                                                                        <h6 class="mb-0">{{ $assign->users->name }}
                                                                        </h6>
                                                                        <small
                                                                            class="text-muted">{{ $assign->users->email ?? '' }}</small>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <td>
                                                                {{ $assign->users->jobDetails->Designation->name ?? 'NA' }}
                                                            </td>
                                                            <td>
                                                                {{ $assign->users->jobDetails->Department->name ?? 'NA' }}
                                                            </td>
                                                            <td class="text-center">
                                                                {{ \Carbon\Carbon::parse($assign->created_at)->format('d M, Y') }}
                                                            </td>
                                                            <td class="text-center">
                                                                @if ($assign->status == 1)
                                                                    <span class="badge bg-success">Active</span>
                                                                @else
                                                                    <span class="badge bg-danger">Inactive</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <hr class="my-0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Edit Project Modal -->
    <div class="modal fade" id="editProjectModal" tabindex="-1" aria-labelledby="editProjectModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Project Details</span>
                        <small class="d-block fs-11 fw-normal text-muted">Update project information</small>
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="editProjectForm"
                                action="{{ route('project.update', ['id' => encrypt($project->id)]) }}" method="POST">
                                @csrf
                                <div id="editFormError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_name">Project Name *</label>
                                            <input type="text" class="form-control" name="name" id="edit_name"
                                                value="{{ $project->name }}" placeholder="Enter Project Name" required>
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_project_head">Project Head *</label>
                                            <select class="form-control select2" name="project_head"
                                                id="edit_project_head" required>
                                                <option value="">-- Select Project Head --</option>
                                                @foreach ($users as $user)
                                                    <option value="{{ $user->id }}"
                                                        {{ $project->project_head == $user->id ? 'selected' : '' }}>
                                                        {{ ucfirst($user->name) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text project_head_error"></small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="start_date">Start Date *</label>
                                            <input type="date" class="form-control" name="start_date"
                                                id="start_date" value="{{ \Carbon\Carbon::parse($project->start_date)->format('Y-m-d') }}" required>
                                            <small class="text-danger error-text start_date_error"></small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_deadline_date">Deadline Date *</label>
                                            <input type="date" class="form-control" name="deadline_date"
                                                id="edit_deadline_date"
                                                value="{{ \Carbon\Carbon::parse($project->deadline_date)->format('Y-m-d') }}"
                                                required>
                                            <small class="text-danger error-text deadline_date_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_status">Project Status *</label>
                                            <select class="form-control" name="status" id="edit_status" required>
                                                <option value="ongoing"
                                                    {{ $project->status == 'ongoing' ? 'selected' : '' }}>Ongoing
                                                </option>
                                                <option value="pending"
                                                    {{ $project->status == 'pending' ? 'selected' : '' }}>Pending
                                                </option>
                                                <option value="hold" {{ $project->status == 'hold' ? 'selected' : '' }}>
                                                    On Hold</option>
                                                <option value="completed"
                                                    {{ $project->status == 'completed' ? 'selected' : '' }}>Completed
                                                </option>
                                                <option value="cancelled"
                                                    {{ $project->status == 'cancelled' ? 'selected' : '' }}>Cancelled
                                                </option>
                                            </select>
                                            <small class="text-danger error-text status_error"></small>
                                        </div>
                                    </div>

                                    <!-- Team Members Section -->
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_member">Team Members *</label>
                                            @php
                                                $projectMemberIds = $project->assigns->where('status', '1')->pluck('user_id')->toArray();
                                            @endphp
                                            <select class="form-control select2" multiple name="member[]"
                                                id="edit_member" required>
                                                <option value="" disabled>-- Select Team Members --</option>
                                                @foreach ($users as $user)
                                                    <option value="{{ $user->id }}"
                                                        {{ in_array($user->id, $projectMemberIds) ? 'selected' : '' }}>
                                                        {{ $user->name }}
                                                        @if($user->id == $project->project_head)
                                                            (Project Head)
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">Select team members. Current project members are pre-selected.</div>
                                            <small class="text-danger error-text member_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_description">Project Description</label>
                                            <textarea class="form-control" name="description" id="edit_description" rows="4"
                                                placeholder="Enter project description...">{{ $project->description }}</textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                                Cancel
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="feather-save me-1"></i>Update Project
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
            // Toast notification function
            function showToast(message, type = 'info', duration = 5000) {
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
                
                // Auto remove after duration
                setTimeout(() => {
                    if (toastElement) {
                        $(toastElement).fadeOut(300, function() {
                            $(this).remove();
                        });
                    }
                }, duration);
            }


            // Edit Project Form Submission
            $('#editProjectForm').on('submit', function(e) {
                e.preventDefault();

                // Reset errors
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                // Validate at least one member
                var members = $('#edit_member').val();
                if (!members || members.length === 0) {
                    $('.member_error').text('Please select at least one team member.');
                    return;
                }

                // Validate start date is not greater than deadline
                var startDate = new Date($('#start_date').val());
                var deadlineDate = new Date($('#edit_deadline_date').val());
                if (startDate > deadlineDate) {
                    $('.deadline_date_error').text('Deadline date must be after start date.');
                    return;
                }

                // Show loading
                var submitBtn = $(this).find('button[type="submit"]');
                var originalText = submitBtn.html();
                submitBtn.prop('disabled', true).html('<i class="feather-loader me-1"></i>Updating...');

                // Get form data
                var formData = new FormData(this);

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
                        submitBtn.prop('disabled', false).html(originalText);

                        if (response.success) {
                            // Show success toast
                            showToast('Project updated successfully! ✅', 'success');
                            
                            $('#editProjectModal').modal('hide');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        }
                    },
                    error: function(xhr) {
                        submitBtn.prop('disabled', false).html(originalText);

                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.' + key + '_error').text(value[0]);
                            });
                            // Show error toast
                            // showToast('Please fix the validation errors! ❌', 'danger');
                        } else {
                            let errorMsg = xhr.responseJSON?.message || 'Something went wrong.';
                            $('#editFormError').removeClass('d-none').text(errorMsg);
                            // Show error toast
                            // showToast('Something went wrong! Please try again. ❌', 'danger');
                        }
                    }
                });
            });

            // Set minimum date for deadline based on start date
            $('#start_date').on('change', function() {
                $('#edit_deadline_date').attr('min', $(this).val());
            });

            // Initialize min date for deadline
            $('#edit_deadline_date').attr('min', $('#start_date').val());

            // Clear form when modal is closed
            $('#editProjectModal').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
            });
        });
    </script>
@endsection