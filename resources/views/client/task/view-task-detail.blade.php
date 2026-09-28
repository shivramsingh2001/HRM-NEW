@extends('client.layout.master')

@section('style')
    <style>
        /* Simplified Card Design — compact, attractive: small font/padding/margin,
           subtle shadow + hover lift (same anatomy as .stats-card elsewhere). */
        .main-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 10px;
            box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
            transition: box-shadow .2s, border-color .2s;
        }

        .main-card:hover {
            box-shadow: 0 4px 12px -4px rgba(30, 50, 110, .12);
            border-color: #dfe5f0;
        }

        .card-header-custom {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 12px;
            font-weight: 600;
            font-size: 12.5px;
            color: #374151;
            border-radius: 10px 10px 0 0;
        }

        .card-body-custom {
            padding: 12px;
        }

        /* Task Header */
        .task-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: white;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .deadline-pill {
            font-size: 10px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .deadline-pill.overdue { background: rgba(255,255,255,.2); color: #fecaca; }
        .deadline-pill.soon { background: rgba(255,255,255,.2); color: #fde68a; }
        .deadline-pill.ok { background: rgba(255,255,255,.2); color: #bbf7d0; }

        .group-progress-track { background: #e3edfe; border-radius: 999px; height: 8px; overflow: hidden; flex: 1; }
        .group-progress-fill { background: #1e3a8a; height: 100%; border-radius: 999px; transition: width .3s; }

        .task-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .task-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .task-code {
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }

        /* Badge Styles */
        .status-badge,
        .priority-badge {
            padding: 2px 7px;
            border-radius: 12px;
            font-size: 10.5px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .status-badge::before,
        .priority-badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-pending::before {
            background: #92400e;
        }

        .status-in_progress {
            background-color: #dbeafe;
            color: #1e3a8a;
        }

        .status-in_progress::before {
            background: #1e3a8a;
        }

        .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-completed::before {
            background: #065f46;
        }

        .status-approved {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-approved::before {
            background: #166534;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-rejected::before {
            background: #991b1b;
        }

        .priority-low {
            background-color: #d1fae5;
            color: #065f46;
        }

        .priority-low::before {
            background: #065f46;
        }

        .priority-medium {
            background-color: #fef3c7;
            color: #92400e;
        }

        .priority-medium::before {
            background: #92400e;
        }

        .priority-high {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .priority-high::before {
            background: #991b1b;
        }

        .priority-critical {
            background-color: #e3edfe;
            color: #1e3a8a;
        }

        .priority-critical::before {
            background: #1e3a8a;
        }

        /* Detail Items */
        .detail-section {
            margin-bottom: 12px;
        }

        .detail-section:last-child {
            margin-bottom: 0;
        }

        .detail-section-title {
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }

        .detail-item {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid #f3f4f6;
            align-items: center;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 500;
            color: #4b5563;
            width: 110px;
            min-width: 110px;
            font-size: 12px;
        }

        .detail-value {
            flex: 1;
            color: #374151;
            font-size: 12px;
            line-height: 1.5;
        }

        /* .employee-info/.employee-avatar/.employee-details/.employee-name/
           .employee-email are centralized in client.layout.head (Team Leave's
           pattern) — no local .user-info/.user-avatar/.user-name/.user-email copy. */

        /* Updates Section */
        .update-card {
            background: #f9fafb;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 6px;
            border: 1px solid #e5e7eb;
        }

        .update-card:last-child {
            margin-bottom: 0;
        }

        .update-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .update-meta {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .update-date {
            font-size: 10.5px;
            color: #6b7280;
            background: #e5e7eb;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .update-remarks {
            display: flex;
            gap: 6px;
            color: #374151;
            font-size: 12px;
            line-height: 1.5;
            background: white;
            border: 1px solid #eef0f3;
            border-left: 2px solid #1e3a8a;
            border-radius: 4px;
            padding: 6px 8px;
            margin-top: 4px;
        }

        .update-remarks i {
            color: #1e3a8a;
            font-size: 11px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .update-remarks.is-empty {
            color: #9ca3af;
            font-style: italic;
            border-left-color: #d1d5db;
        }

        .update-remarks.is-empty i {
            color: #9ca3af;
        }

        /* Attachments */
        .file-attachment {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            background: #e3edfe;
            border-radius: 6px;
            color: #1e3a8a;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid #bfd3f7;
            font-size: 12px;
        }

        .file-attachment:hover {
            background: #bfd3f7;
            text-decoration: none;
            color: #16295e;
        }

        .voice-attachment {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            background: #e3edfe;
            border-radius: 6px;
            color: #1e3a8a;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid #bfd3f7;
            font-size: 12px;
        }

        .voice-attachment:hover {
            background: #bfd3f7;
            text-decoration: none;
            color: #16295e;
        }

        /* Deadline Status */
        .deadline-status {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 6px;
        }

        .deadline-overdue {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .deadline-soon {
            background-color: #fef3c7;
            color: #d97706;
        }

        .deadline-ok {
            background-color: #d1fae5;
            color: #059669;
        }

        /* Buttons */
        .btn-custom {
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 18px 16px;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 26px;
            margin-bottom: 6px;
            color: #d1d5db;
        }

        .empty-state p {
            font-size: 12px;
            margin-bottom: 0;
        }

        /* Compact Table Layout */
        .table-compact {
            font-size: 13px;
        }

        .table-compact th,
        .table-compact td {
            padding: 8px 12px;
        }

        /* Audio Player Compact */
        audio {
            height: 32px;
            width: 100%;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .card-body-custom {
                padding: 12px;
            }

            .detail-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .detail-label {
                width: 100%;
                min-width: 100%;
            }

            .update-header {
                flex-direction: column;
                gap: 6px;
                align-items: flex-start;
            }

            .task-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
        }

        /* ==================== STATUS UPDATE / REVIEW MODALS ==================== */
        #statusUpdateModal .modal-header,
        #approvalModal .modal-header {
            background: #fff !important;
            border-bottom: 1px solid #edf2f7 !important;
            padding: 10px 16px !important;
        }
        #statusUpdateModal .modal-header .fs-18,
        #approvalModal .modal-header .fs-18 {
            font-size: 13px !important;
            color: #1e293b !important;
        }
        #statusUpdateModal .form-group label,
        #approvalModal .form-group label {
            font-size: 11px !important;
        }
        #statusUpdateModal .form-control,
        #approvalModal .form-control {
            font-size: 11.5px !important;
            padding: 6px 10px !important;
        }
        #statusUpdateModal .btn,
        #approvalModal .btn {
            font-size: 11.5px !important;
            padding: 6px 14px !important;
        }
        #statusUpdateModal .btn-primary,
        #approvalModal .btn-primary {
            background: #1e3a8a !important;
            border-color: #1e3a8a !important;
        }
        #statusUpdateModal .btn-primary:hover,
        #approvalModal .btn-primary:hover {
            background: #16295e !important;
            border-color: #16295e !important;
        }
        #statusUpdateModal .btn-modal-cancel,
        #approvalModal .btn-modal-cancel {
            background: #eef3fd !important;
            border: 1px solid #bfd3f7 !important;
            color: #1e3a8a !important;
        }
        #statusUpdateModal .btn-modal-cancel:hover,
        #approvalModal .btn-modal-cancel:hover {
            background: #dbeafe !important;
            color: #1e3a8a !important;
        }
    </style>
@endsection

@section('content-area')
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Tasks Management</h5>
            </div>
            <ul class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ url()->previous() }}">Tasks</a></li>
                <li class="breadcrumb-item">Task Details</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding:30px;">
        <!-- Task Header -->
        <div class="task-header mb-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h2 class="task-title text-white mb-2">{{ $task->title }}</h2>
                    <div class="task-meta">
                        <span class="task-code">{{ $task->task_code }}</span>
                        <span class="status-badge status-{{ $task->status }}">
                            {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                        </span>
                        <span class="priority-badge priority-{{ $task->priority }}">
                            {{ ucfirst($task->priority) }}
                        </span>
                        @if ($isOverdue)
                            <span class="deadline-pill overdue"><i class="feather-alert-triangle"></i>Overdue {{ abs($daysRemaining) }}d</span>
                        @elseif ($daysRemaining !== null && $daysRemaining <= 2 && !in_array($task->status, ['completed', 'approved', 'rejected', 'cancelled']))
                            <span class="deadline-pill soon"><i class="feather-clock"></i>{{ $daysRemaining == 0 ? 'Due today' : $daysRemaining . 'd left' }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    @if ($myAssignment && in_array($myAssignment->individual_status, ['pending', 'in_progress', 'blocked']) && !in_array($task->status, ['approved', 'rejected', 'cancelled']))
                        <button class="btn btn-light btn-sm btn-custom" onclick="showStatusUpdateModal({{ $task->id }})">
                            <i class="feather-edit me-1"></i>Update Status
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="row g-2">
            <!-- Left Column - Task Details -->
            <div class="col-lg-8">
                <!-- Basic Information -->
                <div class="main-card">
                    <div class="card-header-custom">
                        <span>Task Information</span>
                    </div>
                    <div class="card-body-custom">
                        <div class="detail-section">
                            <table class="table table-compact table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <td class="detail-label">Task Code:</td>
                                        <td class="detail-value">
                                            <span class="badge bg-light text-dark">{{ $task->task_code }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">Status:</td>
                                        <td class="detail-value">
                                            <span class="status-badge status-{{ $task->status }}">
                                                {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">Priority:</td>
                                        <td class="detail-value">
                                            <span class="priority-badge priority-{{ $task->priority }}">
                                                {{ ucfirst($task->priority) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">Task Date:</td>
                                        <td class="detail-value">{{ $formattedTaskDate }}</td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">Deadline:</td>
                                        <td class="detail-value">
                                            <div class="d-flex align-items-center">
                                                <span>{{ $formattedDeadlineDate }}</span>
                                                @if ($daysRemaining !== null && !in_array($task->status, ['completed', 'approved', 'rejected', 'cancelled']))
                                                    @if ($daysRemaining < 0)
                                                        <span class="deadline-status deadline-overdue ms-2">
                                                            Overdue {{ abs($daysRemaining) }}d
                                                        </span>
                                                    @elseif ($daysRemaining == 0)
                                                        <span class="deadline-status deadline-soon ms-2">
                                                            Due today
                                                        </span>
                                                    @elseif ($daysRemaining <= 2)
                                                        <span class="deadline-status deadline-soon ms-2">
                                                            {{ $daysRemaining }}d left
                                                        </span>
                                                    @else
                                                        <span class="deadline-status deadline-ok ms-2">
                                                            {{ $daysRemaining }}d left
                                                        </span>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @if ($task->project_name)
                                        <tr>
                                            <td class="detail-label">Project:</td>
                                            <td class="detail-value">
                                                @if ($task->project_id)
                                                    <a href="{{ route('project.view-details', ['id' => encrypt($task->project_id)]) }}" style="color:#1e3a8a; font-weight:500;">{{ $task->project_name }}</a>
                                                @else
                                                    <div>{{ $task->project_name }}</div>
                                                @endif
                                                @if ($task->project_code)
                                                    <small class="text-muted">{{ $task->project_code }}</small>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <!-- Description -->
                        @if ($task->description)
                            <div class="detail-section mt-3">
                                <div class="detail-section-title">Description</div>
                                <div class="detail-value small text-muted">
                                    {!! nl2br(e($task->description)) !!}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Updates History -->
                <div class="main-card mt-2">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <span>Updates History</span>
                        <span class="badge rounded-pill" style="background:#1e3a8a;color:#fff;">{{ $updates->count() }}</span>
                    </div>
                    <div class="card-body-custom">
                        @if ($updates->count() > 0)
                            <div class="updates-list">
                                @foreach ($updates as $update)
                                    <div class="update-card">
                                        <div class="update-header">
                                            <div class="update-meta">
                                                <div class="employee-info">
                                                    @if ($update->updated_by_image)
                                                        <img src="{{ file_url($update->updated_by_image, 'profile_photo') }}" alt="Avatar"
                                                            class="employee-avatar">
                                                    @else
                                                        <img src="{{ asset('assets/images/avatar/1.png') }}" alt="Avatar"
                                                            class="employee-avatar">
                                                    @endif
                                                    <div class="employee-details">
                                                        <div class="employee-name">{{ $update->updated_by_name }}</div>
                                                    </div>
                                                </div>
                                                <span class="update-date">
                                                    {{ \Carbon\Carbon::parse($update->date)->format('d M, Y h:i A') }}
                                                </span>
                                            </div>
                                            <span class="status-badge status-{{ $update->status ?? 'pending' }}">
                                                {{ ucfirst(str_replace('_', ' ', $update->status ?? 'pending')) }}
                                            </span>
                                        </div>
                                        @if (trim((string) $update->remarks) !== '')
                                            <div class="update-remarks">
                                                <i class="feather-message-square"></i>
                                                <span>{{ $update->remarks }}</span>
                                            </div>
                                        @else
                                            <div class="update-remarks is-empty">
                                                <i class="feather-message-square"></i>
                                                <span>No remarks added</span>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="empty-state py-3">
                                <i class="feather-clock"></i>
                                <p>No updates yet</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column - Assignment & Attachments -->
            <div class="col-lg-4">
                <!-- Assignment Details -->
                <div class="main-card">
                    <div class="card-header-custom">
                        <span>Assignment</span>
                    </div>
                    <div class="card-body-custom">
                        <div class="detail-section">
                            <div class="mb-3">
                                <div class="detail-label mb-2">Assigned By</div>
                                <div class="employee-info">
                                    <img src="{{ file_url($task->assigned_by_image, 'profile_photo') ?: asset('assets/images/avatar/1.png') }}"
                                        class="employee-avatar">
                                    <div class="employee-details">
                                        <div class="employee-name">{{ $task->assigned_by_name }}</div>
                                        <div class="employee-email">{{ $task->assigned_by_email }}</div>
                                    </div>
                                </div>
                            </div>

                            @if ($task->task_mode === 'group')
                                @php
                                    $memberTotal = $task->members->count();
                                    $memberCompleted = $task->members->where('individual_status', 'completed')->count();
                                    $groupPct = $memberTotal > 0 ? round(($memberCompleted / $memberTotal) * 100) : 0;
                                @endphp
                                <div>
                                    <div class="detail-label mb-2">
                                        Group Members
                                        <span class="badge" style="background:#1e3a8a;color:#fff;">{{ $memberTotal }}</span>
                                    </div>
                                    <div class="detail-label mb-2" style="font-weight:400;font-size:11px;">
                                        Rule:
                                        <strong>{{ ucfirst(str_replace('_', ' ', $task->group_completion_rule ?? 'not set')) }}</strong>
                                        @if ($task->group_completion_rule === 'percentage')
                                            ({{ $task->completion_threshold }}%)
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <div class="group-progress-track">
                                            <div class="group-progress-fill" style="width:{{ $groupPct }}%;"></div>
                                        </div>
                                        <small style="font-weight:700; color:#1e3a8a; font-size:11px;">{{ $memberCompleted }}/{{ $memberTotal }}</small>
                                    </div>
                                    @foreach ($task->members as $m)
                                        <div class="d-flex align-items-center justify-content-between mb-2 p-2"
                                            style="background:#f9fafb;border-radius:6px;">
                                            <div class="employee-info">
                                                <img src="{{ file_url($m->profile_image, 'profile_photo') ?: asset('assets/images/avatar/1.png') }}"
                                                    class="employee-avatar">
                                                <div class="employee-details">
                                                    <div class="employee-name">
                                                        {{ $m->name }}
                                                        @if ($m->member_role === 'lead')
                                                            <span class="badge" style="font-size:9px; background:#1e3a8a; color:#fff;">LEAD</span>
                                                        @endif
                                                    </div>
                                                    <div class="employee-email">{{ $m->email }}</div>
                                                </div>
                                            </div>
                                            <span class="status-badge status-{{ $m->individual_status ?? 'pending' }}">
                                                {{ ucfirst(str_replace('_', ' ', $m->individual_status ?? 'pending')) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div>
                                    <div class="detail-label mb-2">Assigned To</div>
                                    <div class="employee-info">
                                        <img src="{{ file_url($task->assigned_to_image, 'profile_photo') ?: asset('assets/images/avatar/1.png') }}"
                                            class="employee-avatar">
                                        <div class="employee-details">
                                            <div class="employee-name">{{ $task->assigned_to_name }}</div>
                                            <div class="employee-email">{{ $task->assigned_to_email }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <!-- Attachments -->
                <div class="main-card mt-2">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <span>Attachments</span>
                        <button type="button" class="btn btn-sm btn-primary" id="attachFileBtn" style="font-size:10.5px;padding:3px 10px;">
                            <i class="feather-paperclip me-1"></i>Add
                        </button>
                    </div>
                    <div class="card-body-custom">
                        <div class="detail-section">
                            @if ($task->file)
                                @php
                                    $filePath = file_url($task->file, 'task_document');
                                    $fileName = basename($task->file);
                                @endphp
                                <div class="mb-3">
                                    <a href="{{ $filePath }}" target="_blank" class="file-attachment">
                                        <i class="feather-file-text me-2"></i>
                                        {{ $fileName }}
                                    </a>
                                </div>
                            @endif

                            @if ($task->voice_file)
                                @php
                                    $voicePath = file_url($task->voice_file, 'task_voice');
                                @endphp
                                <div class="mb-3">
                                    <div class="detail-label mb-2">Voice Message</div>
                                    <div class="d-flex align-items-center gap-2">
                                        <audio controls preload="metadata">
                                            <source src="{{ $voicePath }}" type="audio/mpeg">
                                        </audio>
                                        <a href="{{ $voicePath }}" download
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="feather-download"></i>
                                        </a>
                                    </div>
                                </div>
                            @endif

                            <input type="file" id="attachmentFileInput" multiple style="display:none;"
                                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.mp3,.wav,.m4a">

                            <div id="attachmentList">
                                @forelse ($attachments as $att)
                                    <div class="d-flex align-items-center justify-content-between mb-2 p-2" style="background:#f9fafb;border-radius:6px;" data-attachment-id="{{ $att->id }}">
                                        <a href="{{ $att->file_url }}" target="_blank" class="file-attachment" style="flex:1; min-width:0;">
                                            <i class="feather-paperclip me-2"></i>
                                            <span class="text-truncate">{{ $att->file_name }}</span>
                                            <small class="text-muted ms-1">({{ $att->formatted_size }})</small>
                                        </a>
                                        <button type="button" class="btn btn-sm text-danger delete-attachment-btn" data-id="{{ $att->id }}" style="font-size:11px;">
                                            <i class="feather-trash-2"></i>
                                        </button>
                                    </div>
                                @empty
                                    @if (!$task->file && !$task->voice_file)
                                        <div class="text-muted" style="font-size:11.5px;">No attachments yet.</div>
                                    @endif
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comments -->
                <div class="main-card mt-2">
                    <div class="card-header-custom">
                        <span>Comments <span class="badge" id="commentCount" style="background:#1e3a8a;color:#fff;">{{ $comments->count() }}</span></span>
                    </div>
                    <div class="card-body-custom">
                        <form id="addCommentForm" class="mb-3">
                            @csrf
                            <textarea class="form-control" id="commentInput" rows="2" placeholder="Write a comment..." style="font-size:11.5px;" required></textarea>
                            <button type="submit" class="btn btn-primary btn-sm mt-2" style="font-size:11px;">Post Comment</button>
                        </form>
                        <div id="commentList">
                            @forelse ($comments as $comment)
                                <div class="mb-3 pb-2" style="border-bottom:1px solid #f1f5f9;" data-comment-id="{{ $comment->id }}">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <strong style="font-size:11.5px; color:#1e293b;">{{ $comment->user->name ?? 'Unknown' }}</strong>
                                        <div class="d-flex align-items-center gap-2">
                                            <small class="text-muted" style="font-size:10px;">{{ $comment->created_at->diffForHumans() }}</small>
                                            @if (app(\App\Services\TaskPermissionService::class)->canManageOwnedResource(auth()->user(), $comment->user_id))
                                                <button type="button" class="btn btn-sm text-danger delete-comment-btn" data-id="{{ $comment->id }}" style="font-size:10px; padding:0 4px;">
                                                    <i class="feather-x"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                    <div style="font-size:11.5px; color:#334155; margin-top:2px;">{{ $comment->comment }}</div>
                                </div>
                            @empty
                                <div class="text-muted" id="noCommentsMsg" style="font-size:11.5px;">No comments yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Approval Status -->
                <div class="main-card mt-2">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <span>Approval {{ $task->task_mode === 'group' ? 'History' : 'Status' }}</span>
                        @if ($approvals->count() > 1)
                            <span class="badge" style="background:#1e3a8a;color:#fff;">{{ $approvals->count() }}</span>
                        @endif
                    </div>
                    <div class="card-body-custom">
                        @forelse ($approvals as $approval)
                            <div class="detail-section" style="{{ !$loop->last ? 'border-bottom:1px solid #f1f5f9; padding-bottom:10px; margin-bottom:10px;' : '' }}">
                                <table class="table table-compact table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="detail-label">Status:</td>
                                            <td class="detail-value">
                                                <span class="status-badge status-{{ $approval->approval_status }}">
                                                    {{ ucfirst($approval->approval_status) }}
                                                </span>
                                            </td>
                                        </tr>
                                        @if ($task->task_mode === 'group')
                                            <tr>
                                                <td class="detail-label">Member:</td>
                                                <td class="detail-value">{{ $approval->requested_by_name }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td class="detail-label">Approved By:</td>
                                            <td class="detail-value">
                                                <div class="employee-info">
                                                    @if ($approval->approved_by_image)
                                                        <img src="{{ file_url($approval->approved_by_image, 'profile_photo') }}" alt="Avatar"
                                                            class="employee-avatar">
                                                    @else
                                                        <img src="{{ asset('assets/images/avatar/1.png') }}"
                                                            alt="Avatar" class="employee-avatar">
                                                    @endif
                                                    <div class="employee-details">
                                                        <div class="employee-name">{{ $approval->approved_by_name }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        @if ($approval->approval_date)
                                            <tr>
                                                <td class="detail-label">Date:</td>
                                                <td class="detail-value">
                                                    {{ \Carbon\Carbon::parse($approval->approval_date)->format('d M, Y') }}
                                                </td>
                                            </tr>
                                        @endif
                                        @if ($approval->remarks)
                                            <tr>
                                                <td class="detail-label">Remarks:</td>
                                                <td class="detail-value small">{{ $approval->remarks }}</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        @empty
                            <div class="empty-state py-3">
                                <i class="feather-clock"></i>
                                <p>No approval record</p>
                            </div>
                        @endforelse

                        @if (in_array($task->status, ['completed']) && Auth::id() == $task->assigned_by_id && $approvals->isEmpty())
                            <div class="mt-3">
                                <button class="btn btn-primary w-100 btn-sm"
                                    onclick="showApprovalModal({{ $task->id }})">
                                    <i class="feather-check-circle me-2"></i>Review Task
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Update Status Drawer -->
    <div class="modal fade-scale" id="statusUpdateModal" tabindex="-1" aria-labelledby="statusUpdateModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Update Task Status</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="statusUpdateForm">
                                @csrf
                                <input type="hidden" id="statusTaskId" name="task_id">
                                <div id="statusUpdateError" class="alert alert-danger d-none"></div>

                                <div class="form-group mb-3">
                                    <label class="fw-semibold" for="status">New Status *</label>
                                    <select name="status" id="status" class="form-control" required>
                                        <option value="" disabled selected>Select Status</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="hold">On Hold</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                    <small class="text-danger error-text status_error"></small>
                                </div>

                                <div class="form-group mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="extendDeadline" name="extend_deadline" value="1">
                                        <label class="form-check-label fw-semibold" for="extendDeadline" style="font-size:11.5px;">Request deadline extension</label>
                                    </div>
                                    <div id="deadlineFields" style="display:none;" class="mt-2">
                                        <label class="fw-semibold" for="newDeadline">New Deadline *</label>
                                        <input type="date" class="form-control" id="newDeadline" name="new_deadline" min="{{ date('Y-m-d') }}">
                                        <small class="text-danger error-text new_deadline_error"></small>
                                        <small class="text-muted d-block mt-1">Current deadline: {{ $formattedDeadlineDate }}</small>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="fw-semibold" for="remarks">Remarks</label>
                                    <textarea name="remarks" id="remarks" class="form-control" rows="3" placeholder="Add remarks about this status update..."></textarea>
                                    <small class="text-danger error-text remarks_error"></small>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary" id="statusUpdateBtn">
                                        <span class="spinner-border spinner-border-sm d-none me-2"></span>Update Status
                                    </button>
                                    <a href="#" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Review/Approval Drawer -->
    <div class="modal fade-scale" id="approvalModal" tabindex="-1" aria-labelledby="approvalModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Review Task</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="approvalForm">
                                @csrf
                                <input type="hidden" id="approvalTaskId" name="task_id">
                                <div id="approvalError" class="alert alert-danger d-none"></div>

                                <div class="form-group mb-3">
                                    <label class="fw-semibold" for="approval_status">Decision *</label>
                                    <select name="status" id="approval_status" class="form-control" required>
                                        <option value="" disabled selected>-- Select Decision --</option>
                                        <option value="approved">Approve</option>
                                        <option value="rejected">Reject (send back for rework)</option>
                                    </select>
                                    <small class="text-danger error-text approval_status_error"></small>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="fw-semibold" for="approval_remarks">Remarks *</label>
                                    <textarea name="remarks" id="approval_remarks" class="form-control" rows="3" required placeholder="Add remarks about this decision..."></textarea>
                                    <small class="text-danger error-text approval_remarks_error"></small>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary" id="approvalSubmitBtn">
                                        <span class="spinner-border spinner-border-sm d-none me-2"></span>Submit Decision
                                    </button>
                                    <a href="#" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</a>
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
        // Simple modal functions
        function showStatusUpdateModal(taskId) {
            $('#statusUpdateForm')[0].reset();
            $('#statusUpdateForm .error-text').text('');
            $('#statusUpdateError').addClass('d-none').text('');
            $('#deadlineFields').hide();
            $('#statusTaskId').val(taskId);
            new bootstrap.Modal(document.getElementById('statusUpdateModal')).show();
        }

        function showApprovalModal(taskId) {
            $('#approvalForm')[0].reset();
            $('#approvalForm .error-text').text('');
            $('#approvalError').addClass('d-none').text('');
            $('#approvalTaskId').val(taskId);
            new bootstrap.Modal(document.getElementById('approvalModal')).show();
        }

        $('#extendDeadline').on('change', function() {
            $('#deadlineFields').toggle(this.checked);
            $('#newDeadline').prop('required', this.checked);
        });

        // Form submissions
        $('#statusUpdateForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const updateBtn = $('#statusUpdateBtn');

            updateBtn.prop('disabled', true);
            updateBtn.find('.spinner-border').removeClass('d-none');
            $('#statusUpdateForm .error-text').text('');
            $('#statusUpdateError').addClass('d-none').text('');

            $.ajax({
                url: '{{ route('task.update-status') }}',
                method: 'POST',
                data: form.serialize(),
                success: function(response) {
                    updateBtn.prop('disabled', false);
                    updateBtn.find('.spinner-border').addClass('d-none');

                    if (response.success) {
                        showToast(response.message || 'Status updated', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(response.message || 'Failed to update', 'error');
                    }
                },
                error: function(xhr) {
                    updateBtn.prop('disabled', false);
                    updateBtn.find('.spinner-border').addClass('d-none');
                    if (xhr.status === 422 && xhr.responseJSON?.errors) {
                        const errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(function(field) {
                            $('.' + field + '_error').text(Array.isArray(errors[field]) ? errors[field][0] : errors[field]);
                        });
                    }
                    showToast(xhr.responseJSON?.message || 'Error updating status', 'error');
                }
            });
        });

        $('#approvalForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = $('#approvalSubmitBtn');

            submitBtn.prop('disabled', true);
            submitBtn.find('.spinner-border').removeClass('d-none');
            $('#approvalForm .error-text').text('');
            $('#approvalError').addClass('d-none').text('');

            $.ajax({
                url: '{{ route('task.approval-status') }}',
                method: 'POST',
                data: form.serialize(),
                success: function(response) {
                    submitBtn.prop('disabled', false);
                    submitBtn.find('.spinner-border').addClass('d-none');

                    if (response.success) {
                        showToast(response.message || 'Decision recorded', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('approvalModal'))?.hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(response.message || 'Failed to submit decision', 'error');
                    }
                },
                error: function(xhr) {
                    submitBtn.prop('disabled', false);
                    submitBtn.find('.spinner-border').addClass('d-none');
                    if (xhr.status === 422 && xhr.responseJSON?.errors) {
                        const errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(function(field) {
                            $('.approval_' + field + '_error').text(Array.isArray(errors[field]) ? errors[field][0] : errors[field]);
                        });
                    }
                    showToast(xhr.responseJSON?.message || 'Error submitting decision', 'error');
                }
            });
        });

        // ==================== COMMENTS ====================
        const currentTaskId = {{ $task->id }};

        $('#addCommentForm').on('submit', function(e) {
            e.preventDefault();
            const input = $('#commentInput');
            const comment = input.val().trim();
            if (!comment) return;

            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true);

            $.ajax({
                url: '{{ url('tasks') }}/' + currentTaskId + '/comments',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}', comment: comment },
                success: function(response) {
                    submitBtn.prop('disabled', false);
                    if (response.success) {
                        const c = response.data;
                        $('#noCommentsMsg').remove();
                        const html = `<div class="mb-3 pb-2" style="border-bottom:1px solid #f1f5f9;" data-comment-id="${c.id}">
                            <div class="d-flex justify-content-between align-items-start">
                                <strong style="font-size:11.5px; color:#1e293b;">${c.user_name}</strong>
                                <div class="d-flex align-items-center gap-2">
                                    <small class="text-muted" style="font-size:10px;">${c.created_at_human}</small>
                                    <button type="button" class="btn btn-sm text-danger delete-comment-btn" data-id="${c.id}" style="font-size:10px; padding:0 4px;"><i class="feather-x"></i></button>
                                </div>
                            </div>
                            <div style="font-size:11.5px; color:#334155; margin-top:2px;">${$('<div>').text(c.comment).html()}</div>
                        </div>`;
                        $('#commentList').prepend(html);
                        $('#commentCount').text(parseInt($('#commentCount').text() || '0') + 1);
                        input.val('');
                        showToast('Comment added', 'success');
                    } else {
                        showToast(response.message || 'Failed to add comment', 'error');
                    }
                },
                error: function(xhr) {
                    submitBtn.prop('disabled', false);
                    showToast(xhr.responseJSON?.message || 'Error adding comment', 'error');
                }
            });
        });

        $(document).on('click', '.delete-comment-btn', function() {
            if (!confirm('Delete this comment?')) return;
            const id = $(this).data('id');
            const row = $(this).closest('[data-comment-id]');
            $.ajax({
                url: '{{ url('tasks/comments') }}/' + id + '/delete',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(200, function() { $(this).remove(); });
                        $('#commentCount').text(Math.max(0, parseInt($('#commentCount').text() || '0') - 1));
                        showToast('Comment deleted', 'success');
                    } else {
                        showToast(response.message || 'Failed to delete comment', 'error');
                    }
                },
                error: function(xhr) {
                    showToast(xhr.responseJSON?.message || 'Error deleting comment', 'error');
                }
            });
        });

        // ==================== ATTACHMENTS ====================
        $('#attachFileBtn').on('click', function() {
            $('#attachmentFileInput').trigger('click');
        });

        $('#attachmentFileInput').on('change', function() {
            const files = this.files;
            if (!files.length) return;

            const formData = new FormData();
            for (let i = 0; i < files.length; i++) {
                formData.append('files[]', files[i]);
            }

            const btn = $('#attachFileBtn');
            const originalHtml = btn.html();
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

            $.ajax({
                url: '{{ url('tasks') }}/' + currentTaskId + '/attachments',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    btn.prop('disabled', false).html(originalHtml);
                    if (response.success) {
                        response.data.forEach(function(att) {
                            const html = `<div class="d-flex align-items-center justify-content-between mb-2 p-2" style="background:#f9fafb;border-radius:6px;" data-attachment-id="${att.id}">
                                <a href="${att.file_url}" target="_blank" class="file-attachment" style="flex:1; min-width:0;">
                                    <i class="feather-paperclip me-2"></i>
                                    <span class="text-truncate">${att.file_name}</span>
                                    <small class="text-muted ms-1">(${att.formatted_size})</small>
                                </a>
                                <button type="button" class="btn btn-sm text-danger delete-attachment-btn" data-id="${att.id}" style="font-size:11px;"><i class="feather-trash-2"></i></button>
                            </div>`;
                            $('#attachmentList').prepend(html);
                        });
                        showToast(response.message, 'success');
                    } else {
                        showToast(response.message || 'Failed to upload', 'error');
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html(originalHtml);
                    showToast(xhr.responseJSON?.message || 'Error uploading file', 'error');
                }
            });

            $(this).val('');
        });

        $(document).on('click', '.delete-attachment-btn', function() {
            if (!confirm('Delete this attachment?')) return;
            const id = $(this).data('id');
            const row = $(this).closest('[data-attachment-id]');
            $.ajax({
                url: '{{ url('tasks/attachments') }}/' + id + '/delete',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(200, function() { $(this).remove(); });
                        showToast('Attachment deleted', 'success');
                    } else {
                        showToast(response.message || 'Failed to delete attachment', 'error');
                    }
                },
                error: function(xhr) {
                    showToast(xhr.responseJSON?.message || 'Error deleting attachment', 'error');
                }
            });
        });

        function showToast(message, type = 'success') {
            const toast = $(`<div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`);

            $('.toast-container').remove();
            $('body').append('<div class="toast-container position-fixed top-0 end-0 p-3"></div>');
            $('.toast-container').append(toast);

            const bsToast = new bootstrap.Toast(toast[0]);
            bsToast.show();

            setTimeout(() => bsToast.hide(), 3000);
        }
    </script>
@endsection
