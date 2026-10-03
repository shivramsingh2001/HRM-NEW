@extends('client.layout.master')

@section('style')
<style>
    .personal-info .input-group-text { background: #EFF6FF; color: #0D6EFD; border-color: #dfe5f0; }
    .personal-info .form-control[readonly] { background: #f8fafc; border-color: #dfe5f0; color: #1a2236; }
    .customers-nav-tabs .nav-link.active { color: #0D6EFD; border-color: #dfe5f0 #dfe5f0 #fff; }
    .customers-nav-tabs .nav-link { color: #6b7385; font-size: 11.5px; }

    .prj-badge { padding: 3px 10px; border-radius: 30px; font-size: 10px; font-weight: 700; letter-spacing: .2px; }
    .badge-ongoing { background: #3b82f6; color: #fff; }
    .badge-pending { background: #93c5fd; color: #0D6EFD; }
    .badge-hold { background: #0D6EFD; color: #fff; }
    .badge-completed { background: #0D6EFD; color: #fff; }
    .badge-cancelled { background: #6b7385; color: #fff; }
    .badge-priority-low { background: #EFF6FF; color: #0D6EFD; }
    .badge-priority-medium { background: #93c5fd; color: #0D6EFD; }
    .badge-priority-high { background: #0D6EFD; color: #fff; }
    .badge-priority-critical { background: #0D6EFD; color: #fff; }

    .widget-card { background: #f4f6fb; border-radius: 10px; padding: 12px 14px; margin-bottom: 12px; }
    .widget-title { font-size: 9.5px; text-transform: uppercase; color: #6b7385; font-weight: 700; letter-spacing: .04em; margin-bottom: 8px; }
    .widget-value { font-size: 15px; font-weight: 700; color: #1a2236; }
    .widget-label { font-size: 9.5px; color: #6b7385; }

    .progress-track { height: 8px; border-radius: 5px; background: #EFF6FF; overflow: hidden; }
    .progress-fill { height: 100%; background: #0D6EFD; }

    .timeline-strip { position: relative; height: 6px; background: #EFF6FF; border-radius: 4px; margin: 24px 0 8px; }
    .timeline-fill { position: absolute; top: 0; left: 0; height: 100%; background: #0D6EFD; border-radius: 4px; }
    .timeline-dot { position: absolute; top: -4px; width: 14px; height: 14px; border-radius: 50%; background: #0D6EFD; border: 2px solid #fff; box-shadow: 0 0 0 1px #dfe5f0; transform: translateX(-50%); }
    .timeline-dot.milestone { background: #3b82f6; width: 10px; height: 10px; top: -2px; }
    .timeline-labels { display: flex; justify-content: space-between; font-size: 9px; color: #6b7385; }

    .fs-card { border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
    .fs-meta { font-size: 9px; color: #6b7385; }
    .fs-body { font-size: 11px; color: #1a2236; margin-top: 4px; }

    .compact-modal .modal-header .fs-18 { font-size: 13px !important; }
    .compact-modal .form-group { margin-bottom: 0; }
    .compact-modal .form-control,
    .compact-modal .form-check-label { font-size: 11.5px; }
</style>
@endsection

@php
    $user = Auth::user();
    $role = $user->role;
    $canEdit = in_array($role, ['admin','hr']) || $project->project_head == $user->id || $project->assigns->contains(fn($a) => $a->user_id == $user->id && $a->status);
    $todayPct = 0;
    if ($project->start_date && $project->deadline_date) {
        $total = \Carbon\Carbon::parse($project->start_date)->diffInDays(\Carbon\Carbon::parse($project->deadline_date)) ?: 1;
        $elapsed = min(max(\Carbon\Carbon::parse($project->start_date)->diffInDays(\Carbon\Carbon::today(), false), 0), $total);
        $todayPct = round(($elapsed / $total) * 100);
    }
@endphp

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Project Management" :current="$project->name" :crumbs="[['label' => 'Projects', 'url' => route('project.index')]]">
        <x-slot:actions>
            <span class="prj-badge badge-priority-{{ $project->priority }}">{{ ucfirst($project->priority) }}</span>
            <span class="prj-badge badge-{{ $project->status }}">{{ ucfirst($project->status) }}</span>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">
        <div class="row">
            <div class="col-lg-12">
                <div class="card border-top-0">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="projectTab" role="tablist">
                            <li class="nav-item flex-fill border-top"><a class="nav-link active" data-bs-toggle="tab" href="#overviewTab">Overview</a></li>
                            <li class="nav-item flex-fill border-top"><a class="nav-link" data-bs-toggle="tab" href="#tasksTab">Tasks</a></li>
                            <li class="nav-item flex-fill border-top"><a class="nav-link" data-bs-toggle="tab" href="#milestonesTab">Milestones</a></li>
                            <li class="nav-item flex-fill border-top"><a class="nav-link" data-bs-toggle="tab" href="#updatesTab">Updates</a></li>
                            <li class="nav-item flex-fill border-top"><a class="nav-link" data-bs-toggle="tab" href="#risksTab">Risks &amp; Blockers</a></li>
                            <li class="nav-item flex-fill border-top"><a class="nav-link" data-bs-toggle="tab" href="#discussionTab">Discussion</a></li>
                        </ul>
                    </div>
                    <div class="tab-content">

                        {{-- ==================== OVERVIEW ==================== --}}
                        <div class="tab-pane fade show active" id="overviewTab">
                            <div class="card-body personal-info">
                                <div class="row">
                                    <div class="col-lg-8">
                                        <div class="mb-3 d-flex align-items-center justify-content-between">
                                            <h5 class="fw-bold mb-0">
                                                <span class="d-block mb-1">Project Information:</span>
                                                <span class="fs-12 fw-normal text-muted">Project ID: #{{ str_pad($project->id, 5, '0', STR_PAD_LEFT) }} — read-only, use the edit icon on the project list to update.</span>
                                            </h5>
                                        </div>

                                        <div class="row mb-3 align-items-center">
                                            <div class="col-lg-3"><label class="fw-semibold">Project Name:</label></div>
                                            <div class="col-lg-9">
                                                <div class="input-group"><div class="input-group-text"><i class="feather-briefcase"></i></div>
                                                    <input type="text" value="{{ $project->name }}" class="form-control" readonly></div>
                                            </div>
                                        </div>
                                        <div class="row mb-3 align-items-center">
                                            <div class="col-lg-3"><label class="fw-semibold">Project Manager:</label></div>
                                            <div class="col-lg-9">
                                                <div class="input-group"><div class="input-group-text"><i class="feather-user"></i></div>
                                                    <input type="text" value="{{ $project->head->name ?? 'N/A' }}" class="form-control" readonly></div>
                                            </div>
                                        </div>
                                        <div class="row mb-3 align-items-center">
                                            <div class="col-lg-3"><label class="fw-semibold">Start Date:</label></div>
                                            <div class="col-lg-9">
                                                <div class="input-group"><div class="input-group-text"><i class="feather-clock"></i></div>
                                                    <input type="text" value="{{ \Carbon\Carbon::parse($project->start_date)->format('d M, Y') }}" class="form-control" readonly></div>
                                            </div>
                                        </div>
                                        <div class="row mb-3 align-items-center">
                                            <div class="col-lg-3"><label class="fw-semibold">Deadline Date:</label></div>
                                            <div class="col-lg-9">
                                                <div class="input-group"><div class="input-group-text"><i class="feather-calendar"></i></div>
                                                    <input type="text" value="{{ \Carbon\Carbon::parse($project->deadline_date)->format('d M, Y') }}" class="form-control" readonly>
                                                    @if (\Carbon\Carbon::parse($project->deadline_date)->isPast() && !in_array($project->status, ['completed','cancelled']))
                                                        <span class="input-group-text" style="background:#0D6EFD;color:#fff;"><i class="feather-alert-triangle"></i> Overdue</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mb-3 align-items-start">
                                            <div class="col-lg-3"><label class="fw-semibold">Description:</label></div>
                                            <div class="col-lg-9">
                                                <div class="input-group"><div class="input-group-text"><i class="feather-file-text"></i></div>
                                                    <textarea class="form-control" rows="3" readonly>{{ $project->description }}</textarea></div>
                                            </div>
                                        </div>

                                        <div class="timeline-strip">
                                            <div class="timeline-fill" style="width: {{ min($todayPct,100) }}%"></div>
                                            @foreach ($project->milestones as $m)
                                                @php
                                                    $mPct = ($project->start_date && $project->deadline_date && $m->due_date)
                                                        ? min(max(round(\Carbon\Carbon::parse($project->start_date)->diffInDays($m->due_date, false) / max(\Carbon\Carbon::parse($project->start_date)->diffInDays($project->deadline_date),1) * 100), 0), 100)
                                                        : 0;
                                                @endphp
                                                <div class="timeline-dot milestone" style="left: {{ $mPct }}%" title="{{ $m->title }}"></div>
                                            @endforeach
                                        </div>
                                        <div class="timeline-labels">
                                            <span>{{ \Carbon\Carbon::parse($project->start_date)->format('d M Y') }}</span>
                                            <span>{{ \Carbon\Carbon::parse($project->deadline_date)->format('d M Y') }}</span>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="widget-card">
                                            <div class="widget-title">Progress</div>
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="widget-value">{{ $project->progress_percentage }}%</span>
                                                @if ($project->progress_manual_override)
                                                    <span class="prj-badge badge-priority-medium" title="Manually reported">Manual</span>
                                                @endif
                                            </div>
                                            <div class="progress-track mb-2"><div class="progress-fill" style="width: {{ $project->progress_percentage }}%"></div></div>
                                            <div class="widget-label">{{ $taskStats['completed'] }}/{{ $taskStats['total'] }} tasks completed</div>
                                            @if ($canEdit && $project->progress_manual_override)
                                                <button type="button" id="resetProgressBtn" class="btn btn-modal-cancel btn-sm mt-2" data-url="{{ route('project.progress.reset', ['id' => encrypt($project->id)]) }}">
                                                    <i class="feather-refresh-cw me-1"></i>Reset to task-derived
                                                </button>
                                            @endif
                                        </div>

                                        <div class="widget-card">
                                            <div class="widget-title">Budget</div>
                                            @if ($budget['budget'] !== null)
                                                <div class="d-flex justify-content-between"><span class="widget-label">Budget</span><span class="widget-value" style="font-size:11px;">{{ number_format($budget['budget'], 2) }}</span></div>
                                                <div class="d-flex justify-content-between"><span class="widget-label">Spent</span><span class="widget-value" style="font-size:11px;">{{ number_format($budget['spent'], 2) }}</span></div>
                                                <div class="d-flex justify-content-between"><span class="widget-label">Remaining</span><span class="widget-value" style="font-size:11px;">{{ number_format($budget['remaining'], 2) }}</span></div>
                                            @else
                                                <div class="widget-label">No budget set for this project.</div>
                                                <div class="d-flex justify-content-between mt-1"><span class="widget-label">Spent (approved expenses)</span><span class="widget-value" style="font-size:11px;">{{ number_format($budget['spent'], 2) }}</span></div>
                                            @endif
                                        </div>

                                        <div class="widget-card">
                                            <div class="widget-title">Tasks</div>
                                            <div class="d-flex justify-content-between"><span class="widget-label">Total</span><span>{{ $taskStats['total'] }}</span></div>
                                            <div class="d-flex justify-content-between"><span class="widget-label">Completed</span><span>{{ $taskStats['completed'] }}</span></div>
                                            <div class="d-flex justify-content-between"><span class="widget-label">In Progress</span><span>{{ $taskStats['in_progress'] }}</span></div>
                                            <div class="d-flex justify-content-between"><span class="widget-label">Overdue</span><span>{{ $taskStats['overdue'] }}</span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-0">
                            <div class="card-body personal-info">
                                <h5 class="fw-bold mb-3"><span class="d-block mb-1">Members:</span></h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr class="text-center"><th>S.No.</th><th>Name</th><th>Designation</th><th>Department</th><th>Assigned Date</th><th>Status</th></tr></thead>
                                        <tbody>
                                            @foreach ($project->assigns->where('status', '1') as $index => $assign)
                                                <tr class="text-center">
                                                    <td>{{ $index + 1 }}</td>
                                                    <td>
                                                        <h6 class="mb-0">{{ $assign->users->name ?? 'N/A' }}</h6>
                                                        <small class="text-muted">{{ $assign->users->email ?? '' }}</small>
                                                    </td>
                                                    <td>{{ $assign->users->jobDetails->Designation->name ?? 'NA' }}</td>
                                                    <td>{{ $assign->users->jobDetails->Department->name ?? 'NA' }}</td>
                                                    <td>{{ \Carbon\Carbon::parse($assign->created_at)->format('d M, Y') }}</td>
                                                    <td><span class="prj-badge {{ $assign->status ? 'badge-ongoing' : 'badge-cancelled' }}">{{ $assign->status ? 'Active' : 'Inactive' }}</span></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- ==================== TASKS ==================== --}}
                        <div class="tab-pane fade" id="tasksTab">
                            <div class="card-body personal-info">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold mb-0">Linked Tasks ({{ $project->tasks->count() }})</h5>
                                    <a href="{{ route('task.create') }}?project_id={{ $project->id }}" class="btn btn-primary btn-sm"><i class="feather-plus me-1"></i>Add Task</a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr class="text-center"><th>Title</th><th>Priority</th><th>Status</th><th>Deadline</th></tr></thead>
                                        <tbody>
                                            @forelse ($project->tasks as $task)
                                                <tr class="text-center">
                                                    <td class="text-start">{{ $task->title }}</td>
                                                    <td>{{ ucfirst($task->priority) }}</td>
                                                    <td>{{ ucfirst(str_replace('_',' ',$task->status)) }}</td>
                                                    <td>{{ $task->deadline_date ? \Carbon\Carbon::parse($task->deadline_date)->format('d M Y') : '—' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="text-center text-muted py-4">No tasks linked to this project yet.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- ==================== MILESTONES ==================== --}}
                        <div class="tab-pane fade" id="milestonesTab">
                            <div class="card-body personal-info">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold mb-0">Milestones</h5>
                                    @if ($canEdit)
                                        <a href="javascript:void(0)" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMilestoneModal"><i class="feather-plus me-1"></i>Add Milestone</a>
                                    @endif
                                </div>
                                <div id="milestonesList">
                                    @forelse ($project->milestones as $m)
                                        <div class="fs-card d-flex justify-content-between align-items-start" data-milestone-id="{{ $m->id }}">
                                            <div>
                                                <strong>{{ $m->title }}</strong>
                                                <div class="fs-meta">Due {{ $m->due_date ? \Carbon\Carbon::parse($m->due_date)->format('d M Y') : '—' }}</div>
                                                @if ($m->description)<div class="fs-body">{{ $m->description }}</div>@endif
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="prj-badge {{ $m->status === 'completed' ? 'badge-completed' : 'badge-pending' }}">{{ ucfirst($m->status) }}</span>
                                                @if ($canEdit)
                                                    @if ($m->status === 'pending')
                                                        <button type="button" class="btn btn-modal-cancel btn-sm milestone-complete-btn" data-url="{{ route('project.milestones.update', $m->id) }}">Mark Complete</button>
                                                    @endif
                                                    <button type="button" class="btn btn-modal-cancel btn-sm milestone-delete-btn" data-url="{{ route('project.milestones.destroy', $m->id) }}"><i class="feather-trash-2"></i></button>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-muted text-center py-4">No milestones yet.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        {{-- ==================== UPDATES ==================== --}}
                        <div class="tab-pane fade" id="updatesTab">
                            <div class="card-body personal-info">
                                @if ($canEdit)
                                    <h5 class="fw-bold mb-3">Post a Project Update</h5>
                                    <form id="addUpdateForm" action="{{ route('project.updates.store', ['id' => encrypt($project->id)]) }}" method="POST" class="mb-4">
                                        @csrf
                                        <div id="addUpdateFormError" class="alert alert-danger d-none"></div>
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <label class="fw-semibold fs-12">Completed Work</label>
                                                <textarea class="form-control" name="completed_work" rows="2"></textarea>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="fw-semibold fs-12">Pending Work</label>
                                                <textarea class="form-control" name="pending_work" rows="2"></textarea>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="fw-semibold fs-12">Issues</label>
                                                <textarea class="form-control" name="issues" rows="2"></textarea>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="fw-semibold fs-12">Next Actions</label>
                                                <textarea class="form-control" name="next_actions" rows="2"></textarea>
                                            </div>
                                            <div class="col-md-8 mb-2">
                                                <label class="fw-semibold fs-12">Notes / Important Changes</label>
                                                <textarea class="form-control" name="notes" rows="2"></textarea>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="fw-semibold fs-12">Report Progress % (optional — overrides task-derived %)</label>
                                                <input type="number" min="0" max="100" class="form-control" name="reported_progress_percentage">
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" class="btn btn-primary btn-sm"><i class="feather-send me-1"></i>Post Update</button>
                                            </div>
                                        </div>
                                    </form>
                                    <hr>
                                @endif

                                <h5 class="fw-bold mb-3">Update History</h5>
                                @forelse ($project->updates as $u)
                                    <div class="fs-card">
                                        <div class="fs-meta">{{ $u->author->name ?? 'Unknown' }} — {{ $u->created_at->format('d M Y, h:i A') }}
                                            @if ($u->reported_progress_percentage !== null) · reported {{ $u->reported_progress_percentage }}% @endif
                                        </div>
                                        @if ($u->completed_work)<div class="fs-body"><strong>Completed:</strong> {{ $u->completed_work }}</div>@endif
                                        @if ($u->pending_work)<div class="fs-body"><strong>Pending:</strong> {{ $u->pending_work }}</div>@endif
                                        @if ($u->issues)<div class="fs-body"><strong>Issues:</strong> {{ $u->issues }}</div>@endif
                                        @if ($u->next_actions)<div class="fs-body"><strong>Next Actions:</strong> {{ $u->next_actions }}</div>@endif
                                        @if ($u->notes)<div class="fs-body"><strong>Notes:</strong> {{ $u->notes }}</div>@endif
                                    </div>
                                @empty
                                    <p class="text-muted text-center py-4">No updates posted yet.</p>
                                @endforelse
                            </div>
                        </div>

                        {{-- ==================== RISKS & BLOCKERS ==================== --}}
                        <div class="tab-pane fade" id="risksTab">
                            <div class="card-body personal-info">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold mb-0">Risks &amp; Blockers</h5>
                                    @if ($canEdit)
                                        <a href="javascript:void(0)" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRiskModal"><i class="feather-plus me-1"></i>Log Risk / Blocker</a>
                                    @endif
                                </div>
                                @forelse ($project->risks as $r)
                                    <div class="fs-card d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>{{ $r->title }}</strong>
                                            <span class="prj-badge badge-priority-{{ $r->severity }}">{{ ucfirst($r->severity) }}</span>
                                            <div class="fs-meta">{{ ucfirst($r->type) }} · raised by {{ $r->raisedBy->name ?? 'N/A' }} on {{ optional($r->raised_at)->format('d M Y') }}</div>
                                            @if ($r->description)<div class="fs-body">{{ $r->description }}</div>@endif
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="prj-badge {{ in_array($r->status, ['resolved','closed']) ? 'badge-completed' : 'badge-pending' }}">{{ ucfirst($r->status) }}</span>
                                            @if ($canEdit && !in_array($r->status, ['resolved','closed']))
                                                <button type="button" class="btn btn-modal-cancel btn-sm risk-resolve-btn" data-url="{{ route('project.risks.update', $r->id) }}">Resolve</button>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted text-center py-4">No risks or blockers logged.</p>
                                @endforelse
                            </div>
                        </div>

                        {{-- ==================== DISCUSSION (comments + attachments) ==================== --}}
                        <div class="tab-pane fade" id="discussionTab">
                            <div class="card-body personal-info">
                                @if ($canEdit)
                                    <form id="addCommentForm" action="{{ route('project.comments.store', ['id' => encrypt($project->id)]) }}" method="POST" class="mb-3">
                                        @csrf
                                        <div class="d-flex gap-2">
                                            <input type="text" class="form-control" name="comment" placeholder="Write a comment..." required>
                                            <button type="submit" class="btn btn-primary btn-sm"><i class="feather-send"></i></button>
                                        </div>
                                    </form>
                                @endif
                                <div id="commentsList">
                                    @forelse ($project->comments as $c)
                                        <div class="fs-card">
                                            <div class="fs-meta">{{ $c->author->name ?? 'Unknown' }} — {{ $c->created_at->format('d M Y, h:i A') }}</div>
                                            <div class="fs-body">{{ $c->comment }}</div>
                                        </div>
                                    @empty
                                        <p class="text-muted text-center py-3">No comments yet.</p>
                                    @endforelse
                                </div>

                                <hr>
                                @if ($canEdit)
                                    <form id="addAttachmentForm" action="{{ route('project.attachments.store', ['id' => encrypt($project->id)]) }}" method="POST" enctype="multipart/form-data" class="mb-3">
                                        @csrf
                                        <div class="d-flex gap-2">
                                            <input type="file" class="form-control" name="file" required>
                                            <button type="submit" class="btn btn-primary btn-sm"><i class="feather-upload"></i></button>
                                        </div>
                                    </form>
                                @endif
                                <div id="attachmentsList">
                                    @forelse ($project->attachments as $a)
                                        <div class="fs-card d-flex justify-content-between align-items-center">
                                            <div>
                                                <a href="{{ file_url($a->file_path, 'project_attachment') }}" target="_blank"><i class="feather-paperclip me-1"></i>{{ $a->original_filename }}</a>
                                                <div class="fs-meta">{{ $a->uploadedBy->name ?? 'Unknown' }} — {{ $a->created_at->format('d M Y') }}</div>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-muted text-center py-3">No attachments yet.</p>
                                    @endforelse
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
    <!-- Add Milestone Modal -->
    <div class="modal fade-scale" id="addMilestoneModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0"><span class="fs-18 fw-bold mb-1">Add Milestone</span></h2>
                    <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal"><i class="feather-x text-danger"></i></a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0"><div class="card-body">
                        <form id="addMilestoneForm" action="{{ route('project.milestones.store', ['id' => encrypt($project->id)]) }}" method="POST">
                            @csrf
                            <div id="addMilestoneFormError" class="alert alert-danger d-none"></div>
                            <div class="row">
                                <div class="col-12 mb-3"><label class="fw-semibold">Title *</label><input type="text" class="form-control" name="title" required></div>
                                <div class="col-12 mb-3"><label class="fw-semibold">Due Date</label><input type="date" class="form-control" name="due_date"></div>
                                <div class="col-12 mb-3"><label class="fw-semibold">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                                <div class="col-6"><button class="btn btn-primary" type="submit"><i class="feather-save me-2"></i>Save</button></div>
                                <div class="col-6"><a href="javascript:void(0)" class="btn btn-modal-cancel float-end" data-bs-dismiss="modal">Cancel</a></div>
                            </div>
                        </form>
                    </div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Risk/Blocker Modal -->
    <div class="modal fade-scale" id="addRiskModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0"><span class="fs-18 fw-bold mb-1">Log Risk / Blocker</span></h2>
                    <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal"><i class="feather-x text-danger"></i></a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0"><div class="card-body">
                        <form id="addRiskForm" action="{{ route('project.risks.store', ['id' => encrypt($project->id)]) }}" method="POST">
                            @csrf
                            <div id="addRiskFormError" class="alert alert-danger d-none"></div>
                            <div class="row">
                                <div class="col-6 mb-3"><label class="fw-semibold">Type *</label>
                                    <select class="form-control" name="type" required><option value="risk">Risk</option><option value="blocker">Blocker</option></select>
                                </div>
                                <div class="col-6 mb-3"><label class="fw-semibold">Severity *</label>
                                    <select class="form-control" name="severity" required>
                                        <option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="critical">Critical</option>
                                    </select>
                                </div>
                                <div class="col-12 mb-3"><label class="fw-semibold">Title *</label><input type="text" class="form-control" name="title" required></div>
                                <div class="col-12 mb-3"><label class="fw-semibold">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                                <div class="col-6"><button class="btn btn-primary" type="submit"><i class="feather-save me-2"></i>Save</button></div>
                                <div class="col-6"><a href="javascript:void(0)" class="btn btn-modal-cancel float-end" data-bs-dismiss="modal">Cancel</a></div>
                            </div>
                        </form>
                    </div></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
<script>
    $(document).ready(function() {
        function ajaxPost(url, data, isFormData, onSuccess, errorContainer) {
            $.ajax({
                url: url, type: 'POST', data: data,
                processData: !isFormData, contentType: isFormData ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        setTimeout(() => location.reload(), 900);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422 && errorContainer) {
                        let msg = Object.values(xhr.responseJSON.errors || {}).map(e => e[0]).join(' ');
                        $(errorContainer).removeClass('d-none').text(msg);
                    }
                    toastr.error(xhr.responseJSON?.message || 'Something went wrong.');
                    if (onSuccess) onSuccess(false);
                }
            });
        }

        $('#addUpdateForm').on('submit', function(e) {
            e.preventDefault();
            $('#addUpdateFormError').addClass('d-none');
            ajaxPost($(this).attr('action'), $(this).serialize(), false, null, '#addUpdateFormError');
        });

        $('#addMilestoneForm').on('submit', function(e) {
            e.preventDefault();
            $('#addMilestoneFormError').addClass('d-none');
            ajaxPost($(this).attr('action'), $(this).serialize(), false, null, '#addMilestoneFormError');
        });

        $('#addRiskForm').on('submit', function(e) {
            e.preventDefault();
            $('#addRiskFormError').addClass('d-none');
            ajaxPost($(this).attr('action'), $(this).serialize(), false, null, '#addRiskFormError');
        });

        $('#addCommentForm').on('submit', function(e) {
            e.preventDefault();
            ajaxPost($(this).attr('action'), $(this).serialize(), false);
        });

        $('#addAttachmentForm').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            ajaxPost($(this).attr('action'), formData, true);
        });

        $(document).on('click', '.milestone-complete-btn', function() {
            ajaxPost($(this).data('url'), { status: 'completed', _method: 'POST' }, false);
        });
        $(document).on('click', '.milestone-delete-btn', function() {
            $.ajax({
                url: $(this).data('url'), type: 'DELETE',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(r) { toastr.success(r.message); setTimeout(() => location.reload(), 900); },
                error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Something went wrong.'); }
            });
        });
        $(document).on('click', '.risk-resolve-btn', function() {
            ajaxPost($(this).data('url'), { status: 'resolved' }, false);
        });
        $('#resetProgressBtn').on('click', function() {
            ajaxPost($(this).data('url'), {}, false);
        });

        if (typeof toastr !== 'undefined') {
            toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 3000 };
        }
    });
</script>
@endsection
