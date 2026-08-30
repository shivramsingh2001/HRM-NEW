@extends('client.layout.master')

@section('style')
    <style>
        /* Simplified Card Design */
        .main-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .card-header-custom {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 16px;
            font-weight: 600;
            color: #374151;
        }

        .card-body-custom {
            padding: 16px;
        }

        /* Task Header */
        .task-header {
            background: rgba(52, 84, 209, 0.85);
            color: white;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .task-title {
            font-size: 18px;
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
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
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
            color: #1e40af;
        }

        .status-in_progress::before {
            background: #1e40af;
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
            background-color: #f3e8ff;
            color: #5b21b6;
        }

        .priority-critical::before {
            background: #5b21b6;
        }

        /* Detail Items */
        .detail-section {
            margin-bottom: 16px;
        }

        .detail-section:last-child {
            margin-bottom: 0;
        }

        .detail-section-title {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }

        .detail-item {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            align-items: center;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 500;
            color: #4b5563;
            width: 120px;
            min-width: 120px;
            font-size: 13px;
        }

        .detail-value {
            flex: 1;
            color: #374151;
            font-size: 13px;
            line-height: 1.5;
        }

        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #e5e7eb;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 500;
            color: #374151;
            font-size: 13px;
        }

        .user-email {
            font-size: 11px;
            color: #6b7280;
        }

        /* Updates Section */
        .update-card {
            background: #f9fafb;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 8px;
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
            font-size: 11px;
            color: #6b7280;
            background: #e5e7eb;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .update-remarks {
            color: #4b5563;
            font-size: 13px;
            line-height: 1.5;
        }

        /* Attachments */
        .file-attachment {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            background: #e0f2fe;
            border-radius: 6px;
            color: #0369a1;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid #bae6fd;
            font-size: 12px;
        }

        .file-attachment:hover {
            background: #bae6fd;
            text-decoration: none;
            color: #075985;
        }

        .voice-attachment {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            background: #f3e8ff;
            border-radius: 6px;
            color: #7c3aed;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid #e9d5ff;
            font-size: 12px;
        }

        .voice-attachment:hover {
            background: #e9d5ff;
            text-decoration: none;
            color: #6d28d9;
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
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 24px 16px;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 32px;
            margin-bottom: 8px;
            color: #d1d5db;
        }

        .empty-state p {
            font-size: 13px;
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
                    </div>
                </div>
                <div>
                    <!--@if (in_array($task->status, ['pending', 'in_progress']) && Auth::id() == $task->assigned_to_id)
    -->
                    <!--    <button class="btn btn-light btn-sm btn-custom" onclick="showStatusUpdateModal({{ $task->id }})">-->
                    <!--        <i class="feather-edit me-1"></i>Update Status-->
                    <!--    </button>-->
                    <!--
    @endif-->
                    <!--@if (in_array($task->status, ['completed']) && Auth::id() == $task->assigned_by_id)
    -->
                    <!--    <button class="btn btn-light btn-sm btn-custom" onclick="showApprovalModal({{ $task->id }})">-->
                    <!--        <i class="feather-check-circle me-1"></i>Review Task-->
                    <!--    </button>-->
                    <!--
    @endif-->
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
                                                <!--@if ($daysRemaining !== null && !in_array($task->status, ['completed', 'approved', 'rejected']))
    -->
                                                <!--    @if ($daysRemaining < 0)
    -->
                                                <!--        <span class="deadline-status deadline-overdue ms-2">-->
                                                <!--            Overdue {{ abs($daysRemaining) }}d-->
                                                <!--        </span>-->
                                                <!--
@elseif($daysRemaining == 0)
    -->
                                                <!--        <span class="deadline-status deadline-soon ms-2">-->
                                                <!--            Due today-->
                                                <!--        </span>-->
                                                <!--
@elseif($daysRemaining <= 2)
    -->
                                                <!--        <span class="deadline-status deadline-soon ms-2">-->
                                                <!--            {{ $daysRemaining }}d left-->
                                                <!--        </span>-->
                                            <!--    @else-->
                                                <!--        <span class="deadline-status deadline-ok ms-2">-->
                                                <!--            {{ $daysRemaining }}d left-->
                                                <!--        </span>-->
                                                <!--
    @endif-->
                                                <!--
    @endif-->
                                            </div>
                                        </td>
                                    </tr>
                                    @if ($task->project_name)
                                        <tr>
                                            <td class="detail-label">Project:</td>
                                            <td class="detail-value">
                                                <div>{{ $task->project_name }}</div>
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
                        <span class="badge bg-primary rounded-pill">{{ $updates->count() }}</span>
                    </div>
                    <div class="card-body-custom">
                        @if ($updates->count() > 0)
                            <div class="updates-list">
                                @foreach ($updates as $update)
                                    <div class="update-card">
                                        <div class="update-header">
                                            <div class="update-meta">
                                                <div class="user-info">
                                                    @if ($update->updated_by_image)
                                                        <img src="{{ $update->updated_by_image }}" alt="Avatar"
                                                            class="user-avatar">
                                                    @else
                                                        <img src="{{ asset('assets/images/avatar/1.png') }}" alt="Avatar"
                                                            class="user-avatar">
                                                    @endif
                                                    <div class="user-details">
                                                        <div class="user-name">{{ $update->updated_by_name }}</div>
                                                    </div>
                                                </div>
                                                <span class="update-date">
                                                    {{ \Carbon\Carbon::parse($update->date)->format('d M, Y h:i A') }}
                                                </span>
                                            </div>
                                            <span class="status-badge status-{{ $update->status }}">
                                                {{ ucfirst(str_replace('_', ' ', $update->status)) }}
                                            </span>
                                        </div>
                                        @if ($update->remarks)
                                            <div class="update-remarks small">
                                                {{ $update->remarks }}
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
                                <div class="user-info">
                                    <img src="{{ $task->assigned_by_image ?: asset('assets/images/avatar/1.png') }}"
                                        class="user-avatar">
                                    <div class="user-details">
                                        <div class="user-name">{{ $task->assigned_by_name }}</div>
                                        <div class="user-email">{{ $task->assigned_by_email }}</div>
                                    </div>
                                </div>
                            </div>

                            @if ($task->task_mode === 'group')
                                <div>
                                    <div class="detail-label mb-2">
                                        Group Members
                                        <span class="badge bg-primary">{{ $task->members->count() }}</span>
                                    </div>
                                    <div class="detail-label mb-2" style="font-weight:400;font-size:11px;">
                                        Rule:
                                        <strong>{{ ucfirst(str_replace('_', ' ', $task->group_completion_rule)) }}</strong>
                                        @if ($task->group_completion_rule === 'percentage')
                                            ({{ $task->completion_threshold }}%)
                                        @endif
                                    </div>
                                    @foreach ($task->members as $m)
                                        <div class="d-flex align-items-center justify-content-between mb-2 p-2"
                                            style="background:#f9fafb;border-radius:6px;">
                                            <div class="user-info">
                                                <img src="{{ $m->profile_image ?: asset('assets/images/avatar/1.png') }}"
                                                    class="user-avatar">
                                                <div class="user-details">
                                                    <div class="user-name">
                                                        {{ $m->name }}
                                                        @if ($m->member_role === 'lead')
                                                            <span class="badge bg-warning text-dark"
                                                                style="font-size:9px;">LEAD</span>
                                                        @endif
                                                    </div>
                                                    <div class="user-email">{{ $m->email }}</div>
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
                                    <div class="user-info">
                                        <img src="{{ $task->assigned_to_image ?: asset('assets/images/avatar/1.png') }}"
                                            class="user-avatar">
                                        <div class="user-details">
                                            <div class="user-name">{{ $task->assigned_to_name }}</div>
                                            <div class="user-email">{{ $task->assigned_to_email }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <!-- Attachments -->
                @if ($task->file || $task->voice_file)
                    <div class="main-card mt-2">
                        <div class="card-header-custom">
                            <span>Attachments</span>
                        </div>
                        <div class="card-body-custom">
                            <div class="detail-section">
                                @if ($task->file)
                                    @php
                                        $filePath = asset($task->file);
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
                                        $voicePath = asset($task->voice_file);
                                    @endphp
                                    <div>
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
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Approval Status -->
                <div class="main-card mt-2">
                    <div class="card-header-custom">
                        <span>Approval Status</span>
                    </div>
                    <div class="card-body-custom">
                        @if ($approvals)
                            <div class="detail-section">
                                <table class="table table-compact table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="detail-label">Status:</td>
                                            <td class="detail-value">
                                                <span class="status-badge status-{{ $approvals->approval_status }}">
                                                    {{ ucfirst($approvals->approval_status) }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="detail-label">Approved By:</td>
                                            <td class="detail-value">
                                                <div class="user-info">
                                                    @if ($approvals->approved_by_image)
                                                        <img src="{{ $approvals->approved_by_image }}" alt="Avatar"
                                                            class="user-avatar">
                                                    @else
                                                        <img src="{{ asset('assets/images/avatar/1.png') }}"
                                                            alt="Avatar" class="user-avatar">
                                                    @endif
                                                    <div class="user-details">
                                                        <div class="user-name">{{ $approvals->approved_by_name }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        @if ($approvals->approval_date)
                                            <tr>
                                                <td class="detail-label">Date:</td>
                                                <td class="detail-value">
                                                    {{ \Carbon\Carbon::parse($approvals->approval_date)->format('d M, Y') }}
                                                </td>
                                            </tr>
                                        @endif
                                        @if ($approvals->remarks)
                                            <tr>
                                                <td class="detail-label">Remarks:</td>
                                                <td class="detail-value small">{{ $approvals->remarks }}</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="empty-state py-3">
                                <i class="feather-clock"></i>
                                <p>No approval record</p>
                            </div>
                        @endif

                        @if (in_array($task->status, ['completed']) && Auth::id() == $task->assigned_by_id && !$approvals)
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

@section('script-area')
    <script>
        // Simple modal functions
        function showStatusUpdateModal(taskId) {
            $('#statusTaskIds').val(taskId);
            $('#statusUpdateModal').modal('show');
        }

        function showApprovalModal(taskId) {
            $('#approvalTaskIds').val(taskId);
            $('#approvalModal').modal('show');
        }

        // Form submissions
        $('#statusUpdateForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const updateBtn = $('#statusUpdateBtn');

            updateBtn.prop('disabled', true);
            updateBtn.find('.spinner-border').removeClass('d-none');

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
                    showToast('Error updating status', 'error');
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
