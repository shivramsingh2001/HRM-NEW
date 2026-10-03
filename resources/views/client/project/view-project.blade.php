@extends('client.layout.master')

@section('style')
<style>
    /* ==================== PROJECT CARDS — all-blue theme, 4-per-row, compact spacing (mirrors client/department/department.blade.php) ==================== */
    .project-card {
        border: 1px solid #eaeef5;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
        transition: all .2s ease;
    }

    .project-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px -6px rgba(30, 50, 110, .14);
        border-color: #dfe5f0;
    }

    .project-card .side-stick { background-color: #0D6EFD; }

    .prj-title {
        font-size: 11.5px !important;
        font-weight: 700;
        color: #1a2236;
        max-width: 62%;
    }

    .prj-code { font-size: 9px; color: #6b7385; margin-bottom: 0; }

    .prj-badges { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; justify-content: flex-end; }

    .prj-badge {
        padding: 2px 7px;
        border-radius: 30px;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: .2px;
        white-space: nowrap;
    }

    .badge-ongoing { background: #3b82f6; color: #fff; }
    .badge-pending { background: #93c5fd; color: #0D6EFD; }
    .badge-hold { background: #0D6EFD; color: #fff; }
    .badge-completed { background: #0D6EFD; color: #fff; }
    .badge-cancelled { background: #6b7385; color: #fff; }
    .badge-priority-low { background: #EFF6FF; color: #0D6EFD; }
    .badge-priority-medium { background: #93c5fd; color: #0D6EFD; }
    .badge-priority-high { background: #0D6EFD; color: #fff; }
    .badge-priority-critical { background: #0D6EFD; color: #fff; }

    .prj-overdue { font-size: 9px; color: #0D6EFD; font-weight: 600; margin-bottom: 4px; }

    .prj-dates { display: flex; justify-content: space-between; font-size: 9px; color: #6b7385; margin-bottom: 6px; }

    .prj-info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f4f6fb;
        border-radius: 8px;
        padding: 5px 8px;
        margin-bottom: 6px;
    }

    .prj-info-label { font-size: 8px; color: #6b7385; text-transform: uppercase; letter-spacing: .03em; }
    .prj-info-value { font-size: 9.5px; font-weight: 600; color: #1a2236; }
    .prj-info-value i { color: var(--icon-color, #0D6EFD); }

    .prj-progress-track { height: 6px; border-radius: 4px; background: #EFF6FF; overflow: hidden; flex: 1; }
    .prj-progress-fill { height: 100%; background: #0D6EFD; }
    .prj-progress-row { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
    .prj-progress-row small { font-size: 9px; color: #6b7385; font-weight: 700; }

    .prj-description { color: #475569; font-size: 9.5px; line-height: 1.4; }

    .prj-card-footer {
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
    <x-ui.page-header class="content-area-header sticky-top" title="Project Management" current="Projects">
        <x-slot:actions>
            <div class="hstack gap-2">
                @if(in_array($role,['admin','hr']))
                <a href="javascript:void(0)" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#addProjectModal">
                        <i class="feather-plus me-2"></i>Add Project
                    </a>
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            @forelse ($projects as $project)
                @php
                    $totalMembers = $project->assigns->where('status', 1)->count();
                    $today = date('Y-m-d');
                    $deadline = $project->deadline_date;
                    $isOverdue = $deadline < $today && !in_array($project->status, ['completed', 'cancelled']);
                @endphp
                <div class="col-xxl-3 col-xl-3 col-lg-3 col-md-6 col-sm-6 single-note-item">
                    <div class="card project-card card-body mb-2 stretch stretch-full position-relative border-0">

                        <span class="side-stick"></span>

                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h5 class="prj-title note-title text-truncate mb-0">{{ $project->name }}</h5>
                            <div class="prj-badges">
                                <span class="prj-badge badge-priority-{{ $project->priority }}">{{ ucfirst($project->priority) }}</span>
                                <span class="prj-badge badge-{{ $project->status }}">{{ ucfirst($project->status) }}</span>
                            </div>
                        </div>

                        <p class="prj-code mb-1"><i class="bi bi-tag me-1"></i>{{ $project->project_code ?? 'N/A' }}</p>

                        @if ($isOverdue)
                            <p class="prj-overdue"><i class="bi bi-exclamation-triangle me-1"></i>Deadline passed {{ date('d M Y', strtotime($deadline)) }}</p>
                        @endif

                        <div class="prj-dates">
                            <span><i class="bi bi-calendar-plus me-1"></i>{{ date('d M Y', strtotime($project->start_date)) }}</span>
                            <span><i class="bi bi-calendar-check me-1"></i>{{ date('d M Y', strtotime($project->deadline_date)) }}</span>
                        </div>

                        <div class="prj-progress-row">
                            <div class="prj-progress-track"><div class="prj-progress-fill" style="width: {{ $project->progress_percentage }}%"></div></div>
                            <small>{{ $project->progress_percentage }}%</small>
                        </div>

                        <div class="prj-info-row">
                            <div>
                                <small class="prj-info-label d-block">Project Manager</small>
                                <span class="prj-info-value"><i class="bi bi-person-badge me-1"></i>{{ $project->head->name ?? 'N/A' }}</span>
                            </div>
                            <div class="text-end">
                                <small class="prj-info-label d-block">Team</small>
                                <span class="prj-info-value"><i class="bi bi-people-fill me-1"></i>{{ $totalMembers }}</span>
                            </div>
                        </div>

                        <div class="note-content flex-grow-1">
                            <p class="prj-description text-truncate-3-line mb-0">{{ $project->description ?? 'No description available.' }}</p>
                        </div>

                        <div class="prj-card-footer">
                            <a href="{{ route('project.view-details', ['id' => encrypt($project->id)]) }}" class="action-btn" title="View Details">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                            @if(in_array($role,['admin','hr']) || $project->project_head == $user->id || $project->assigns->contains(fn($a) => $a->user_id == $user->id && $a->status))
                                <a href="javascript:void(0)" class="action-btn edit-project" title="Edit Project"
                                    data-update-url="{{ route('project.update', ['id' => encrypt($project->id)]) }}"
                                    data-name="{{ $project->name }}"
                                    data-description="{{ $project->description }}"
                                    data-start-date="{{ \Carbon\Carbon::parse($project->start_date)->toDateString() }}"
                                    data-deadline-date="{{ \Carbon\Carbon::parse($project->deadline_date)->toDateString() }}"
                                    data-project-head="{{ $project->project_head }}"
                                    data-status="{{ $project->status }}"
                                    data-priority="{{ $project->priority }}"
                                    data-budget="{{ $project->budget }}"
                                    data-members='@json($project->assigns->where("status",1)->pluck("user_id")->values())'>
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                            @endif
                        </div>

                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="empty-state">
                        <i class="bi bi-kanban"></i>
                        <h4>No Projects Found</h4>
                        <p>Get started by adding your first project</p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Project Modal -->
    <div class="modal fade-scale" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0"><span class="fs-18 fw-bold mb-1">Add New Project</span></h2>
                    <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
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
                                            <input type="text" class="form-control" name="name" required id="name" placeholder="Enter Project name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="project_head">Project Manager *</label>
                                            <select class="form-control select2" name="project_head" id="project_head" required>
                                                <option value="">-- Select Project Manager --</option>
                                                @foreach ($users as $u)
                                                    <option value="{{ $u->id }}">{{ ucfirst($u->name) }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text project_head_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="start_date">Start Date *</label>
                                            <input type="date" class="form-control" name="start_date" id="start_date" required>
                                            <small class="text-danger error-text start_date_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="deadline_date">Deadline Date *</label>
                                            <input type="date" class="form-control" name="deadline_date" id="deadline_date" required>
                                            <small class="text-danger error-text deadline_date_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="priority">Priority</label>
                                            <select class="form-control" name="priority" id="priority">
                                                <option value="low">Low</option>
                                                <option value="medium" selected>Medium</option>
                                                <option value="high">High</option>
                                                <option value="critical">Critical</option>
                                            </select>
                                            <small class="text-danger error-text priority_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="budget">Budget</label>
                                            <input type="number" step="0.01" min="0" class="form-control" name="budget" id="budget" placeholder="Optional">
                                            <small class="text-danger error-text budget_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="member">Team Members *</label>
                                            <select class="form-control select2" multiple name="member[]" id="member" required>
                                                @foreach ($users as $u)
                                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">Select at least one team member</div>
                                            <small class="text-danger error-text member_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="description">Description</label>
                                            <textarea class="form-control" name="description" id="description" rows="3" placeholder="Enter project description..."></textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit"><i class="feather-save me-2"></i>Save Project</button>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0)" class="btn btn-modal-cancel float-end" data-bs-dismiss="modal"><i class="feather-x me-2"></i>Cancel</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div class="modal fade-scale" id="editProjectModal" tabindex="-1" aria-labelledby="editProjectModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0"><span class="fs-18 fw-bold mb-1">Edit Project</span></h2>
                    <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="#" id="editProjectForm">
                                <div id="editFormError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_name">Project Name *</label>
                                            <input type="text" class="form-control" name="name" required id="edit_name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_project_head">Project Manager *</label>
                                            <select class="form-control select2" name="project_head" id="edit_project_head" required>
                                                @foreach ($users as $u)
                                                    <option value="{{ $u->id }}">{{ ucfirst($u->name) }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text project_head_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_start_date">Start Date *</label>
                                            <input type="date" class="form-control" name="start_date" id="edit_start_date" required>
                                            <small class="text-danger error-text start_date_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_deadline_date">Deadline Date *</label>
                                            <input type="date" class="form-control" name="deadline_date" id="edit_deadline_date" required>
                                            <small class="text-danger error-text deadline_date_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_status">Status *</label>
                                            <select class="form-control" name="status" id="edit_status" required>
                                                <option value="ongoing">Ongoing</option>
                                                <option value="pending">Pending</option>
                                                <option value="hold">On Hold</option>
                                                <option value="completed">Completed</option>
                                                <option value="cancelled">Cancelled</option>
                                            </select>
                                            <small class="text-danger error-text status_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_priority">Priority</label>
                                            <select class="form-control" name="priority" id="edit_priority">
                                                <option value="low">Low</option>
                                                <option value="medium">Medium</option>
                                                <option value="high">High</option>
                                                <option value="critical">Critical</option>
                                            </select>
                                            <small class="text-danger error-text priority_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_budget">Budget</label>
                                            <input type="number" step="0.01" min="0" class="form-control" name="budget" id="edit_budget">
                                            <small class="text-danger error-text budget_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_member">Team Members *</label>
                                            <select class="form-control select2" multiple name="member[]" id="edit_member" required>
                                                @foreach ($users as $u)
                                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text member_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_description">Description</label>
                                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit"><i class="feather-save me-2"></i>Update</button>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0)" class="btn btn-modal-cancel float-end" data-bs-dismiss="modal"><i class="feather-x me-2"></i>Cancel</a>
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
        const today = new Date().toISOString().split('T')[0];

        function initSelect2(scope) {
            if ($.fn.select2) {
                $(scope).find('.select2').select2({ dropdownParent: $(scope) });
            }
        }
        initSelect2('#addProjectModal');
        initSelect2('#editProjectModal');

        // ==================== ADD ====================
        $('#start_date').attr('max', today);
        $('#deadline_date').attr('min', today);
        $('#start_date').on('change', function() { $('#deadline_date').attr('min', $(this).val()); });

        $('#addProjectForm').on('submit', function(e) {
            e.preventDefault();
            $('.error-text').text('');
            $('#addFormError').addClass('d-none').text('');

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    if (response.success) {
                        $('#addProjectModal').modal('hide');
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
                        $('#addFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                    }
                }
            });
        });

        $('#addProjectModal').on('hidden.bs.modal', function() {
            $('#addProjectForm')[0].reset();
            $('.error-text').text('');
            $('#addFormError').addClass('d-none').text('');
            $('#addProjectModal .select2').val(null).trigger('change');
            $('#start_date').attr('min', today);
            $('#deadline_date').attr('min', today);
        });

        // ==================== EDIT ====================
        $(document).on('click', '.edit-project', function(e) {
            e.preventDefault();
            const el = $(this);
            $('#editProjectForm').attr('action', el.data('update-url'));
            $('#edit_name').val(el.data('name'));
            $('#edit_description').val(el.data('description'));
            $('#edit_start_date').val(el.data('start-date'));
            $('#edit_deadline_date').val(el.data('deadline-date'));
            $('#edit_status').val(el.data('status'));
            $('#edit_priority').val(el.data('priority'));
            $('#edit_budget').val(el.data('budget'));
            $('#edit_project_head').val(el.data('project-head')).trigger('change');
            $('#edit_member').val(el.data('members')).trigger('change');

            $('.error-text').text('');
            $('#editFormError').addClass('d-none').text('');
            $('#editProjectModal').modal('show');
        });

        $('#editProjectForm').on('submit', function(e) {
            e.preventDefault();
            $('.error-text').text('');
            $('#editFormError').addClass('d-none').text('');

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    if (response.success) {
                        $('#editProjectModal').modal('hide');
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
                        toastr.error(xhr.responseJSON?.message || (xhr.status === 403 ? 'You do not have permission to edit this project.' : 'Something went wrong'));
                    }
                }
            });
        });

        $('#editProjectModal').on('hidden.bs.modal', function() {
            $('.error-text').text('');
            $('#editFormError').addClass('d-none').text('');
        });

        if (typeof toastr !== 'undefined') {
            toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 3000 };
        }
    });
</script>
@endsection
