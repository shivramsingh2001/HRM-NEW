{{--
    Meeting detail content — shared by the full-page fallback
    (meeting/show.blade.php) and the AJAX-loaded "Meeting Details" drawer on
    meeting/index.blade.php (MeetingController::show() returns this partial
    alone when $request->ajax()). Modal ids are prefixed "show" to avoid
    colliding with index.blade.php's own #cancelModal when this fragment is
    injected into that page.

    Expects: $meeting (with creator, participants.user, momWriters, tasks.*,
    histories.actionBy eager-loaded).
--}}
@php
    $authUser = Auth::user();
    $canEdit = $meeting->isEditableBy($authUser);
    $canAuthorMom = $meeting->isMomAuthorableBy($authUser);
    $hasMom = !empty($meeting->mom_content) || ($meeting->tasks && $meeting->tasks->count() > 0);
@endphp

<div class="d-flex align-items-center flex-wrap gap-2 mb-3">
    <x-ui.status-badge :status="$meeting->status" />
    @if ($meeting->status != 'cancelled')
        <button type="button" class="btn btn-light btn-sm border" onclick="openMomDrawer({{ $meeting->id }})">
            <i class="feather-file-plus me-1"></i> {{ $hasMom ? 'View / Edit MOM' : 'Create MOM' }}
        </button>
    @endif
    @if ($canEdit && in_array($meeting->status, ['scheduled', 'postponed']))
        <button type="button" class="btn btn-light btn-sm border" data-bs-toggle="modal" data-bs-target="#showRescheduleModal">
            <i class="feather-clock me-1"></i> Reschedule
        </button>
    @endif
    @if ($canEdit && $meeting->status == 'scheduled')
        <button type="button" class="btn btn-light btn-sm border" data-meeting='@json($meeting->toEditPayload())' onclick="openEditMeetingDrawer(this)">
            <i class="feather-edit-2 me-1"></i> Edit
        </button>
        <button type="button" class="btn btn-light btn-sm border" data-bs-toggle="modal" data-bs-target="#showCancelModal">
            <i class="feather-x-circle me-1"></i> Cancel
        </button>
    @endif
    @permission('meetings', 'delete')
        <form action="{{ route('meetings.destroy', $meeting->id) }}" method="POST" onsubmit="return confirm('Delete this meeting? This cannot be undone.');" class="d-inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-light btn-sm border text-danger"><i class="feather-trash-2 me-1"></i> Delete</button>
        </form>
    @endpermission
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="feather-check-circle me-1"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="feather-alert-triangle me-1"></i>{{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="feather-alert-circle me-1"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ── Meeting Information ── --}}
<x-ui.card title="Meeting Information">
    <div class="meeting-info-grid">
        <div class="meeting-info-item">
            <div class="meeting-info-icon"><i class="feather-calendar"></i></div>
            <div><div class="meeting-info-label">Date</div><div class="meeting-info-value">{{ \Carbon\Carbon::parse($meeting->meeting_date)->format('l, d F Y') }}</div></div>
        </div>
        <div class="meeting-info-item">
            <div class="meeting-info-icon"><i class="feather-clock"></i></div>
            <div><div class="meeting-info-label">Time</div><div class="meeting-info-value">{{ \Carbon\Carbon::parse($meeting->start_time)->format('h:i A') }} &ndash; {{ \Carbon\Carbon::parse($meeting->end_time)->format('h:i A') }}</div></div>
        </div>
        <div class="meeting-info-item">
            <div class="meeting-info-icon"><i class="feather-video"></i></div>
            <div><div class="meeting-info-label">Type</div><div class="meeting-info-value">{{ ucfirst($meeting->meeting_type) }}</div></div>
        </div>
        <div class="meeting-info-item">
            <div class="meeting-info-icon"><i class="feather-map-pin"></i></div>
            <div><div class="meeting-info-label">Location</div><div class="meeting-info-value">{{ $meeting->location ?: '—' }}</div></div>
        </div>
        @if ($meeting->virtual_meeting_link)
            <div class="meeting-info-item">
                <div class="meeting-info-icon"><i class="feather-link"></i></div>
                <div><div class="meeting-info-label">Meeting Link</div><div class="meeting-info-value"><a href="{{ $meeting->virtual_meeting_link }}" target="_blank">{{ $meeting->virtual_meeting_link }}</a></div></div>
            </div>
        @endif
        <div class="meeting-info-item">
            <div class="meeting-info-icon"><i class="feather-user"></i></div>
            <div><div class="meeting-info-label">Organizer</div><div class="meeting-info-value">{{ $meeting->creator->name ?? '—' }}</div></div>
        </div>
    </div>
    @if ($meeting->description)
        <div class="meeting-info-label mt-3">Description</div>
        <div class="meeting-description-box">{{ $meeting->description }}</div>
    @endif
    @if ($meeting->status == 'cancelled' && $meeting->cancellation_reason)
        <div class="alert alert-danger mt-3 mb-0"><i class="feather-x-circle me-1"></i> <strong>Cancelled:</strong> {{ $meeting->cancellation_reason }}</div>
    @endif
</x-ui.card>

{{-- ── Participants & RSVP ── --}}
<x-ui.card title="Participants & RSVP">
    <div class="mb-3">
        @forelse ($meeting->participants as $participant)
            <span class="participant-badge">
                <i class="feather-user"></i>
                {{ $participant->user->name ?? 'N/A' }}
                <x-ui.status-badge :status="$participant->attendance_status" />
            </span>
        @empty
            <span class="text-muted fs-12">No participants listed</span>
        @endforelse
    </div>
    @if ($meeting->momWriters->count())
        <div class="meeting-info-label mb-1">MOM Writers</div>
        <div>
            @foreach ($meeting->momWriters as $writer)
                <span class="writer-badge"><i class="feather-edit-3"></i> {{ $writer->user->name ?? 'N/A' }}</span>
            @endforeach
        </div>
    @endif
</x-ui.card>

{{-- ── Attendance marking (organizer / MOM writer only) ── --}}
@if ($canAuthorMom && $meeting->participants->count())
    <x-ui.card title="Mark Attendance">
        <form action="{{ route('meetings.attendance', $meeting->id) }}" method="POST">
            @csrf
            @foreach ($meeting->participants as $participant)
                <div class="attendance-row">
                    <span>{{ $participant->user->name ?? 'N/A' }}</span>
                    <select name="attendance[{{ $participant->id }}]" class="form-control form-control-sm" style="width:140px;">
                        <option value="present" {{ $participant->attendance_status == 'present' ? 'selected' : '' }}>Present</option>
                        <option value="absent" {{ $participant->attendance_status == 'absent' ? 'selected' : '' }}>Absent</option>
                        <option value="late" {{ $participant->attendance_status == 'late' ? 'selected' : '' }}>Late</option>
                    </select>
                </div>
            @endforeach
            <button type="submit" class="btn btn-primary btn-sm mt-3"><i class="feather-check-square me-1"></i> Save Attendance</button>
        </form>
    </x-ui.card>
@endif

{{-- ── Agenda ── --}}
@if (!empty($meeting->agenda_items))
    <x-ui.card title="Agenda">
        <ol class="mb-0 ps-3" style="font-size: 12.5px;">
            @foreach ($meeting->agenda_items as $item)
                <li class="mb-1">{{ $item['title'] ?? $item }}</li>
            @endforeach
        </ol>
    </x-ui.card>
@endif

{{-- ── Decisions ── --}}
@if (!empty($meeting->decisions))
    <x-ui.card title="Decisions">
        <ul class="mb-0 ps-3" style="font-size: 12.5px;">
            @foreach ($meeting->decisions as $decision)
                <li class="mb-1">{{ $decision['decision_text'] ?? $decision }}</li>
            @endforeach
        </ul>
    </x-ui.card>
@endif

{{-- ── Minutes of Meeting ── --}}
@if ($meeting->mom_content)
    <x-ui.card>
        <x-slot:title>
            Minutes of Meeting
            <x-ui.status-badge :status="$meeting->mom_status" />
        </x-slot:title>
        <div class="mom-content-box">{!! $meeting->mom_content !!}</div>
        @if ($meeting->mom_status === 'finalized' && $canEdit)
            <form action="{{ route('meetings.mom.reopen', $meeting->id) }}" method="POST" class="mt-3">
                @csrf
                <button type="submit" class="btn btn-light btn-sm border" onclick="return confirm('Reopen these minutes for editing?');">
                    <i class="feather-unlock me-1"></i> Reopen for Editing
                </button>
            </form>
        @endif
    </x-ui.card>
@endif

{{-- ── Action Items & Tasks ── --}}
<x-ui.card title="Action Items & Tasks">
    @if ($meeting->tasks && $meeting->tasks->count() > 0)
        @foreach ($meeting->tasks as $task)
            <div class="task-item-card">
                <div class="task-item-header">
                    <div>
                        <div class="fw-semibold fs-13">{{ $task->title }}</div>
                        @if ($task->task_code)
                            <div class="fs-11 text-muted">{{ $task->task_code }}</div>
                        @endif
                    </div>
                    <x-ui.status-badge :status="$task->status" />
                </div>
                <div class="task-item-body">
                    <div class="task-meta-grid">
                        <div><div class="task-meta-label">Assigned To</div><div>{{ $task->assignments->first()->assignedTo->name ?? 'Not Assigned' }}</div></div>
                        <div>
                            <div class="task-meta-label">Deadline</div>
                            <div>
                                {{ \Carbon\Carbon::parse($task->deadline_date)->format('d M, Y') }}
                                @if (\Carbon\Carbon::parse($task->deadline_date)->isPast() && $task->status != 'completed')
                                    <span class="text-danger fw-semibold ms-1">(Overdue)</span>
                                @endif
                            </div>
                        </div>
                        <div><div class="task-meta-label">Priority</div><div><span class="priority-badge priority-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span></div></div>
                        <div><div class="task-meta-label">Project</div><div>{{ $task->project->name ?? 'No Project' }}</div></div>
                    </div>
                    @if ($task->description)
                        <div class="meeting-description-box mb-2">
                            {!! nl2br(e(\Illuminate\Support\Str::limit($task->description, 300))) !!}
                        </div>
                    @endif
                    @if ($task->file || $task->voice_file)
                        <div class="d-flex gap-2 flex-wrap mb-2">
                            @if ($task->file)
                                <a href="{{ asset($task->file) }}" class="btn btn-light btn-sm border" target="_blank"><i class="feather-paperclip me-1"></i> Document</a>
                            @endif
                            @if ($task->voice_file)
                                <a href="{{ asset($task->voice_file) }}" class="btn btn-light btn-sm border" target="_blank"><i class="feather-headphones me-1"></i> Voice Note</a>
                            @endif
                        </div>
                    @endif
                    <a href="{{ route('task.view-detail', $task->id) }}" class="btn btn-primary btn-sm"><i class="feather-eye me-1"></i> View Task Details</a>
                </div>
            </div>
        @endforeach
    @else
        <x-ui.empty-state icon="check-square" title="No action items yet" subtitle="Tasks created from this meeting's MOM will appear here." />
    @endif
</x-ui.card>

{{-- ── Activity / History ── --}}
@if ($meeting->histories->count())
    <x-ui.card title="Activity">
        @foreach ($meeting->histories as $entry)
            <div class="history-item">
                <div class="history-dot"></div>
                <div>
                    <div>{{ $entry->description ?: ucfirst(str_replace('_', ' ', $entry->action_type)) }}</div>
                    <div class="text-muted">{{ $entry->actionBy->name ?? 'System' }} &middot; {{ $entry->created_at?->diffForHumans() }}</div>
                </div>
            </div>
        @endforeach
    </x-ui.card>
@endif

@if ($canEdit)
    <x-ui.modal id="showRescheduleModal" title="Reschedule Meeting" bodyOnly>
        <form action="{{ route('meetings.reschedule', $meeting->id) }}" method="POST" id="showRescheduleForm">
            @csrf
            <div class="row g-2">
                <div class="col-12">
                    <label class="form-label fs-12 fw-semibold">New Date *</label>
                    <input type="date" name="meeting_date" class="form-control form-control-sm" min="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-6">
                    <label class="form-label fs-12 fw-semibold">Start Time *</label>
                    <input type="time" name="start_time" class="form-control form-control-sm" required>
                </div>
                <div class="col-6">
                    <label class="form-label fs-12 fw-semibold">End Time *</label>
                    <input type="time" name="end_time" class="form-control form-control-sm" required>
                </div>
                <div class="col-12">
                    <label class="form-label fs-12 fw-semibold">Reason</label>
                    <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="e.g. Venue unavailable"></textarea>
                </div>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" form="showRescheduleForm" class="btn btn-primary btn-sm"><i class="feather-clock me-1"></i> Reschedule</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal id="showCancelModal" title="Cancel Meeting" bodyOnly>
        <form action="{{ route('meetings.cancel', $meeting->id) }}" method="POST" id="showCancelForm">
            @csrf
            <p class="fs-13 text-secondary">You are about to cancel <strong>{{ $meeting->title }}</strong>. This will notify all participants.</p>
            <label class="form-label fs-12 fw-semibold">Cancellation Reason *</label>
            <textarea name="reason" class="form-control form-control-sm" rows="3" required placeholder="e.g. Rescheduled to next week, venue unavailable…"></textarea>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="modal">Go Back</button>
            <button type="submit" form="showCancelForm" class="btn btn-danger btn-sm"><i class="feather-x-circle me-1"></i> Cancel Meeting</button>
        </x-slot:footer>
    </x-ui.modal>
@endif
