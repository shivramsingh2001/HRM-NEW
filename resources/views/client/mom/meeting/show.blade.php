{{-- resources/views/client/mom/meeting/show.blade.php --}}
@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Cards */
        .card {
            background: white;
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: white;
        }

        .card-header h6 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
        }

        .card-header i {
            color: var(--primary);
            margin-right: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.25rem;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .info-icon {
            width: 2rem;
            height: 2rem;
            background: var(--primary-light);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 0.875rem;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            word-break: break-word;
        }

        .info-value a {
            color: var(--primary);
            text-decoration: none;
        }

        .info-value a:hover {
            text-decoration: underline;
        }

        /* Participants & Writers */
        .participants-list,
        .writers-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .participant-badge,
        .writer-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            background: var(--bg-light);
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .participant-badge i,
        .writer-badge i {
            font-size: 0.75rem;
            color: var(--primary);
        }

        /* Description Box */
        .description-box {
            background: var(--bg-light);
            padding: 1rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        /* MOM Content */
        .mom-content {
            background: var(--bg-light);
            padding: 1.25rem;
            border-radius: var(--radius-md);
            margin-top: 0.5rem;
        }

        .mom-content p {
            margin-bottom: 0.75rem;
            line-height: 1.6;
        }

        .mom-content ul,
        .mom-content ol {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }

        /* Task Card */
        .task-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }

        .task-card:hover {
            box-shadow: var(--shadow-md);
            border-color: transparent;
        }

        .task-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .task-title-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .task-number {
            width: 2rem;
            height: 2rem;
            background: var(--primary);
            color: white;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .task-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .task-code {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-family: monospace;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-in-progress {
            background: #fed7aa;
            color: #9a3412;
        }

        .status-pending {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .task-body {
            padding: 1rem 1.25rem;
        }

        .task-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .task-meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .task-meta-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .task-meta-value {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .priority-badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .priority-critical {
            background: #fee2e2;
            color: #991b1b;
        }

        .priority-high {
            background: #fed7aa;
            color: #9a3412;
        }

        .priority-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .priority-low {
            background: #d1fae5;
            color: #065f46;
        }

        .deadline-overdue {
            color: var(--danger);
            font-weight: 600;
        }

        .task-description {
            background: var(--bg-light);
            padding: 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.813rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin: 1rem 0;
        }

        .attachment-links {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin: 1rem 0;
        }

        .attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.875rem;
            background: var(--bg-light);
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .attachment-link:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .view-task-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--primary);
            color: white;
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .view-task-btn:hover {
            background: #4338ca;
            color: white;
        }

        /* Alert Boxes */
        .alert-box {
            padding: 1rem;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
        }

        .alert-info {
            background: #dbeafe;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .alert-warning {
            background: #fed7aa;
            border: 1px solid #fdba74;
            color: #9a3412;
        }

        .alert-success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-box a {
            color: inherit;
            font-weight: 600;
            text-decoration: underline;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.813rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: #4338ca;
            color: white;
        }

        .btn-secondary {
            background: white;
            color: var(--text-secondary);
            border: 1px solid var(--border);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.813rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: var(--bg-light);
            border-color: var(--text-muted);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: var(--bg-light);
            border-radius: var(--radius-md);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .action-buttons {
                justify-content: flex-start;
            }

            .card-body {
                padding: 1rem;
            }

            .info-grid {
                gap: 1rem;
            }
        }
    </style>
@endsection

@section('content-area')

    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Meeting Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('meetings.index') }}">Meetings</a></li>
                <li class="breadcrumb-item active">Meeting Details</li>
            </ul>
        </div>
    </div>
    <div class="main-content" style="padding: 30px !important;">
        <div class="card">
            <div class="card-header">
                <h6><i class="fas fa-info-circle"></i> Meeting Information</h6>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Meeting Date</div>
                            <div class="info-value">{{ \Carbon\Carbon::parse($meeting->meeting_date)->format('l, d F Y') }}</div>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Time</div>
                            <div class="info-value">{{ date('h:i A', strtotime($meeting->start_time)) }} -
                                {{ date('h:i A', strtotime($meeting->end_time)) }}</div>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-video"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Meeting Type</div>
                            <div class="info-value">{{ ucfirst($meeting->meeting_type) }}</div>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Location / Venue</div>
                            <div class="info-value">{{ $meeting->location }}</div>
                        </div>
                    </div>
                </div>

                @if ($meeting->description)
                    <div class="mt-3">
                        <div class="info-label">Description</div>
                        <div class="description-box">{{ $meeting->description }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h6><i class="fas fa-users"></i> Participants & Team</h6>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div>
                        <div class="info-label">Meeting Participants</div>
                        <div class="participants-list">
                            @forelse($meeting->participants as $participant)
                                <span class="participant-badge">
                                    <i class="fas fa-user-circle"></i>
                                    {{ $participant->user->name ?? 'N/A' }}
                                    <small>({{ $participant->user->employee_id ?? ($participant->user->email ?? 'N/A') }})</small>
                                </span>
                            @empty
                                <span class="text-muted">No participants listed</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <div class="info-label">MOM Writers</div>
                        <div class="writers-list">
                            @forelse($meeting->momWriters as $writer)
                                <span class="writer-badge">
                                    <i class="fas fa-pen"></i>
                                    {{ $writer->user->name ?? 'N/A' }}
                                    <small>({{ $writer->user->employee_id ?? ($writer->user->email ?? 'N/A') }})</small>
                                </span>
                            @empty
                                <span class="text-muted">No MOM writers assigned</span>
                            @endforelse
                        </div>
                    </div>

                    @if ($meeting->reminder_minutes_before)
                        <div>
                            <div class="info-label">Reminder</div>
                            <div class="info-value">
                                <i class="fas fa-bell me-1"></i>
                                {{ $meeting->reminder_minutes_before }} minutes before meeting
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($meeting->mom_content)
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-file-alt"></i> Minutes of Meeting</h6>
                </div>
                <div class="card-body">
                    <div class="mom-content">
                        {!! $meeting->mom_content !!}
                    </div>
                   
                </div>
            </div>
        @else
            {{-- <div class="alert-box alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>
                <strong>No Minutes of Meeting yet!</strong> 
                <!--<a href="{{ route('meetings.mom.create', $meeting->id) }}">Click here to create MOM</a>-->
            </span>
        </div> --}}
        @endif

        <div class="card">
            <div class="card-header">
                <h6><i class="fas fa-tasks"></i> Action Items & Tasks</h6>
            </div>
            <div class="card-body">
                @if (isset($meeting->tasks) && $meeting->tasks->count() > 0)
                    @foreach ($meeting->tasks as $task)
                        <div class="task-card">
                            <div class="task-header">
                                <div class="task-title-section">
                                    <div class="task-number">{{ $loop->iteration }}</div>
                                    <div>
                                        <div class="task-title">{{ $task->title }}</div>
                                        @if ($task->task_code)
                                            <div class="task-code">{{ $task->task_code }}</div>
                                        @endif
                                    </div>
                                </div>
                                <span class="status-badge status-{{ str_replace('_', '-', $task->status) }}">
                                    {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                </span>
                            </div>

                            <div class="task-body">
                                <div class="task-meta-grid">
                                    <div class="task-meta-item">
                                        <div class="task-meta-label">Assigned To</div>
                                        <div class="task-meta-value">
                                            <i class="fas fa-user-check me-1"></i>
                                            {{ $task->assignments->first()->assignedTo->name ?? 'Not Assigned' }}
                                        </div>
                                    </div>

                                    <div class="task-meta-item">
                                        <div class="task-meta-label">Deadline</div>
                                        <div class="task-meta-value">
                                            <i class="fas fa-calendar-alt me-1"></i>
                                            {{ \Carbon\Carbon::parse($task->deadline_date)->format('d M, Y') }}
                                            @if (\Carbon\Carbon::parse($task->deadline_date)->isPast() && $task->status != 'completed')
                                                <span class="deadline-overdue ms-2">(Overdue)</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="task-meta-item">
                                        <div class="task-meta-label">Priority</div>
                                        <div class="task-meta-value">
                                            <span class="priority-badge priority-{{ $task->priority }}">
                                                {{ ucfirst($task->priority) }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="task-meta-item">
                                        <div class="task-meta-label">Project</div>
                                        <div class="task-meta-value">
                                            <i class="fas fa-project-diagram me-1"></i>
                                            {{ $task->project->name ?? 'No Project' }}
                                        </div>
                                    </div>
                                </div>

                                @if ($task->description)
                                    <div class="task-description">
                                        {!! nl2br(e(\Illuminate\Support\Str::limit($task->description, 300))) !!}
                                        @if (strlen($task->description) > 300)
                                            <span class="text-muted">...</span>
                                        @endif
                                    </div>
                                @endif

                                @if ($task->file || $task->voice_file)
                                    <div class="attachment-links">
                                        @if ($task->file)
                                            <a href="{{ asset($task->file) }}" class="attachment-link" target="_blank">
                                                <i class="fas fa-paperclip"></i> View Document
                                            </a>
                                        @endif
                                        @if ($task->voice_file)
                                            <a href="{{ asset($task->voice_file) }}" class="attachment-link"
                                                target="_blank">
                                                <i class="fas fa-headphones"></i> Listen Voice Note
                                            </a>
                                        @endif
                                    </div>
                                @endif

                                <div>
                                    <a href="{{ route('task.view-detail', $task->id) }}" class="view-task-btn">
                                        <i class="fas fa-eye"></i> View Task Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <p>No tasks have been created for this meeting yet.</p>
                        @if (!$meeting->mom_content)
                            <!--<a href="{{ route('meetings.mom.create', $meeting->id) }}" class="btn-primary">-->
                            <!--    <i class="fas fa-plus-circle"></i> Create MOM & Tasks-->
                            <!--</a>-->
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
