{{-- resources/views/client/offboarding/show.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== OFFBOARDING STATUS BADGES ==================== */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending_approval { background: #fef3c7; color: #92400e; }
        .status-approved { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-cancelled { background: #f1f5f9; color: #475569; }

        /* ==================== STAGE BADGES ==================== */
        .stage-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }

        .stage-completed { background: #d1fae5; color: #065f46; }
        .stage-in_progress { background: #dbeafe; color: #1e40af; }
        .stage-pending { background: #fef3c7; color: #92400e; }
        .stage-not_started { background: #f1f5f9; color: #64748b; }

        /* ==================== CLEARANCE BADGES ==================== */
        .clearance-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }

        .clearance-pending, .clearance-partial { background: #fef3c7; color: #92400e; }
        .clearance-in_progress { background: #dbeafe; color: #1e40af; }
        .clearance-completed { background: #d1fae5; color: #065f46; }

        /* ==================== INFO CARDS ==================== */
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 20px;
            border: 1px solid #edf2f7;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            transition: all 0.2s;
        }

        .info-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

        .info-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eef2f6;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-title i { color: #4f46e5; font-size: 16px; }

        .info-row {
            display: flex;
            margin-bottom: 10px;
            font-size: 13px;
            line-height: 1.5;
        }

        .info-label {
            width: 140px;
            font-weight: 600;
            color: #64748b;
            flex-shrink: 0;
        }

        .info-value { flex: 1; color: #1e293b; word-break: break-word; }

        /* ==================== ACTION BUTTONS ==================== */
        .action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }

        .btn-sm-custom {
            padding: 6px 14px;
            font-size: 12px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-sm-custom i { font-size: 12px; margin-right: 5px; }

        /* ==================== STATUS BANNER ==================== */
        .status-banner {
            background: #f8fafc;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-left: 4px solid #4f46e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .status-banner-info { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .status-banner-label { font-size: 12px; color: #64748b; font-weight: 500; }
        .status-banner-value { font-size: 14px; font-weight: 600; }

        /* Rejected state banner */
        .status-banner.is-rejected { border-left-color: #ef4444; background: #fff5f5; }

        /* ==================== REJECTION REASON DISPLAY ==================== */
        .rejection-info-card {
            background: #fff5f5;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .rejection-info-card .rejection-title {
            font-size: 13px;
            font-weight: 700;
            color: #991b1b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .rejection-info-card .rejection-detail {
            font-size: 13px;
            color: #7f1d1d;
            margin-bottom: 4px;
        }

        /* ==================== PROGRESS TRACKER ==================== */
        .progress-tracker {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #edf2f7;
        }

        .tracker-step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
            position: relative;
        }

        .tracker-step:last-child { margin-bottom: 0; }

        .tracker-step:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 40px;
            width: 2px;
            height: calc(100% - 20px);
            background: #e2e8f0;
        }

        .tracker-icon {
            width: 40px;
            height: 40px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }

        .tracker-icon.completed { background: #10b981; color: white; }
        .tracker-icon.in_progress { background: #4f46e5; color: white; }
        .tracker-icon.pending { background: #f1f5f9; color: #94a3b8; }
        .tracker-icon.rejected_stage { background: #ef4444; color: white; }

        .tracker-content { flex: 1; }
        .tracker-title { font-weight: 600; margin-bottom: 5px; font-size: 14px; }
        .tracker-status { font-size: 12px; color: #64748b; }
        .tracker-date { font-size: 11px; color: #94a3b8; margin-top: 3px; }

        /* ==================== EXIT INTERVIEW CARD ==================== */
        .exit-interview-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .exit-interview-card .info-title {
            border-bottom-color: rgba(255,255,255,0.2);
            color: white;
        }

        .exit-interview-card .info-title i { color: white; }
        .exit-interview-card .info-label { color: rgba(255,255,255,0.8); }
        .exit-interview-card .info-value { color: white; }

        .exit-interview-card .rating-section {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }

        /* ==================== ROLE BANNER ==================== */
        .role-banner {
            background: #f8fafc;
            border-radius: 10px;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* ==================== MODAL STYLES ==================== */
        .modal-form-group { margin-bottom: 15px; }

        .modal-form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 5px;
            display: block;
        }

        .modal-form-group .required:after { content: "*"; color: #dc3545; margin-left: 4px; }

        .rating-select {
            width: 100%;
            padding: 8px 12px;
            font-size: 13px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .knowledge-transfer-btn {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            margin-left: 10px;
            cursor: pointer;
        }

        .knowledge-transfer-btn:hover { background: #4338ca; }

        /* Reject modal stage info */
        .reject-stage-info {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 14px;
            font-size: 12px;
            color: #9a3412;
        }

        .reject-stage-info strong { display: block; margin-bottom: 4px; }

        @media (max-width: 768px) {
            .info-row { flex-direction: column; margin-bottom: 12px; }
            .info-label { width: 100%; margin-bottom: 4px; }
            .status-banner { flex-direction: column; align-items: flex-start; }
            .action-buttons { width: 100%; }
            .info-card { padding: 15px; }
        }
    </style>
@endsection

@section('content-area')
    @php
        $userRole     = auth()->user()->role ?? 'employee';
        $isAdminOrHR  = in_array($userRole, ['admin', 'hr']);
        $isManager    = $userRole == 'manager';
        $isEmployee   = $userRole == 'employee';
        $isRequester  = $offboarding->employee_id == auth()->user()->id;
        $isTeamMember = false;

        if ($isManager && $offboarding->employee->jobDetails) {
            $isTeamMember = $offboarding->employee->jobDetails->reporting_head == auth()->user()->id;
        }

        // ---- Permission flags ----
        $canApproveManager =
            ($isAdminOrHR || ($isManager && $isTeamMember)) &&
            $offboarding->manager_review_status == 'pending' &&
            $offboarding->status == 'pending_approval';

        $canApproveHR =
            $isAdminOrHR &&
            $offboarding->manager_review_status == 'approved' &&
            $offboarding->hr_review_status == 'pending';

        // Standalone reject: admin/HR can reject at any non-terminal stage
        $canReject =
            $isAdminOrHR &&
            !in_array($offboarding->status, ['completed', 'cancelled', 'rejected']);

        // Manager can also reject during their review window
        $canManagerReject =
            ($isAdminOrHR || ($isManager && $isTeamMember)) &&
            $offboarding->manager_review_status == 'pending' &&
            $offboarding->status == 'pending_approval';

        $canStartKT =
            $isAdminOrHR &&
            $offboarding->status == 'approved' &&
            $offboarding->knowledge_transfer_status == 'not_started';

        $canCompleteKT =
            $isAdminOrHR &&
            $offboarding->knowledge_transfer_status == 'in_progress';

        $canUpdateClearance = $isAdminOrHR && $offboarding->status == 'approved';

        $canProcessSettlement =
            $isAdminOrHR &&
            $offboarding->asset_return_status == 'completed' &&
            $offboarding->final_settlement_status == 'pending';

        $canMarkSettlementPaid =
            $isAdminOrHR &&
            $offboarding->final_settlement_status == 'processing';

        $canComplete =
            $isAdminOrHR &&
            $offboarding->knowledge_transfer_status == 'completed' &&
            $offboarding->asset_return_status == 'completed' &&
            $offboarding->final_settlement_status == 'paid' &&
            $offboarding->status == 'approved';

        $canCancel =
            ($isAdminOrHR || $isRequester) &&
            in_array($offboarding->status, ['pending_approval', 'approved']);

        $canConductExit =
            $isAdminOrHR &&
            !$offboarding->exitInterview &&
            !in_array($offboarding->status, ['cancelled', 'rejected']);

        $canUpdateExitInterview =
            $isAdminOrHR &&
            $offboarding->exitInterview &&
            $offboarding->exitInterview->status != 'completed';

        // ---- Which stage are we at (for reject modal label) ----
        if ($offboarding->manager_review_status == 'pending') {
            $currentRejectStage      = 'manager_review';
            $currentRejectStageLabel = 'Manager Review Stage';
            $currentRejectStageDesc  = 'This will reject the request at the Manager Review stage.';
        } elseif ($offboarding->manager_review_status == 'approved' && $offboarding->hr_review_status == 'pending') {
            $currentRejectStage      = 'hr_review';
            $currentRejectStageLabel = 'HR Review Stage';
            $currentRejectStageDesc  = 'This will reject the request at the HR Review stage.';
        } else {
            $currentRejectStage      = 'post_approval';
            $currentRejectStageLabel = 'Post-Approval Stage';
            $currentRejectStageDesc  = 'This will force-reject an already approved request. This is an admin override action.';
        }
    @endphp

    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-5">Offboarding Details</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                @if ($isAdminOrHR)
                    <li class="breadcrumb-item"><a href="{{ route('offboarding.index') }}">Offboarding</a></li>
                @elseif ($isManager)
                    <li class="breadcrumb-item"><a href="{{ route('offboarding.manager') }}">Team Offboarding</a></li>
                @else
                    <li class="breadcrumb-item"><a href="{{ route('offboarding.employee') }}">My Offboarding</a></li>
                @endif
                <li class="breadcrumb-item active">{{ $offboarding->request_code }}</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">

        {{-- Role-based warnings --}}
        @if ($isEmployee && !$isRequester)
            <div class="role-banner">
                <i class="feather-alert-circle text-danger"></i>
                <strong>Access Restricted:</strong> You can only view your own offboarding requests.
                <a href="{{ route('offboarding.index') }}" class="ms-auto">Go to My Requests →</a>
            </div>
        @endif

        @if ($isManager && !$isTeamMember && !$isRequester)
            <div class="role-banner">
                <i class="feather-alert-circle text-warning"></i>
                <strong>View Only:</strong> This employee doesn't report to you. You cannot take actions on this request.
            </div>
        @endif

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="feather-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="feather-alert-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Rejection reason banner (shown when rejected) --}}
        @if ($offboarding->status === 'rejected')
            <div class="rejection-info-card">
                <div class="rejection-title">
                    <i class="feather-x-circle"></i> This offboarding request has been rejected
                </div>
                @if ($offboarding->manager_review_status === 'rejected' && $offboarding->manager_review_comments)
                    <div class="rejection-detail">
                        <strong>Rejected at:</strong> Manager Review Stage
                    </div>
                    <div class="rejection-detail">
                        <strong>Rejected by:</strong> {{ $offboarding->managerReviewBy->name ?? 'N/A' }}
                        @if ($offboarding->manager_review_at)
                            on {{ \Carbon\Carbon::parse($offboarding->manager_review_at)->format('d M Y, h:i A') }}
                        @endif
                    </div>
                    <div class="rejection-detail">
                        <strong>Reason:</strong> {{ $offboarding->manager_review_comments }}
                    </div>
                @elseif ($offboarding->hr_review_status === 'rejected' && $offboarding->hr_review_comments)
                    <div class="rejection-detail">
                        <strong>Rejected at:</strong> HR Review Stage
                    </div>
                    <div class="rejection-detail">
                        <strong>Rejected by:</strong> {{ $offboarding->hrReviewBy->name ?? 'N/A' }}
                        @if ($offboarding->hr_review_at)
                            on {{ \Carbon\Carbon::parse($offboarding->hr_review_at)->format('d M Y, h:i A') }}
                        @endif
                    </div>
                    <div class="rejection-detail">
                        <strong>Reason:</strong> {{ $offboarding->hr_review_comments }}
                    </div>
                @elseif ($offboarding->feedback)
                    <div class="rejection-detail"><strong>Reason:</strong> {{ $offboarding->feedback }}</div>
                @endif
            </div>
        @endif

        {{-- Status Banner --}}
        <div class="status-banner {{ $offboarding->status === 'rejected' ? 'is-rejected' : '' }}">
            <div class="status-banner-info">
                <span class="status-banner-label">Current Status:</span>
                <span class="status-badge status-{{ $offboarding->status }}">
                    {{ $offboarding->status_label }}
                </span>
                <span class="status-banner-label">Request Code:</span>
                <span class="status-banner-value">{{ $offboarding->request_code }}</span>
            </div>

            @if ($canApproveManager || $canApproveHR || $canReject || $canStartKT || $canCompleteKT ||
                 $canUpdateClearance || $canProcessSettlement || $canMarkSettlementPaid || $canComplete || $canCancel)
                <div class="action-buttons">

                    @if ($canApproveManager)
                        <button class="btn btn-success btn-sm-custom" onclick="showApproveManagerModal()">
                            <i class="feather-check-circle"></i> Approve (Manager)
                        </button>
                    @endif

                    @if ($canApproveHR)
                        <button class="btn btn-primary btn-sm-custom" onclick="showHRApproveModal()">
                            <i class="feather-check-circle"></i> Approve (HR)
                        </button>
                    @endif

                    {{-- Single unified Reject button for admin/HR --}}
                    @if ($canReject)
                        <button class="btn btn-danger btn-sm-custom" onclick="showRejectModal()">
                            <i class="feather-x-circle"></i> Reject
                        </button>
                    @elseif ($canManagerReject && !$canApproveManager)
                        {{-- Manager reject only (when they haven't approved yet) --}}
                        <button class="btn btn-danger btn-sm-custom" onclick="showManagerRejectModal()">
                            <i class="feather-x-circle"></i> Reject
                        </button>
                    @endif

                    @if ($canStartKT)
                        <button class="btn btn-info btn-sm-custom" onclick="showStartKTModal()">
                            <i class="feather-upload"></i> Start KT
                        </button>
                    @endif

                    @if ($canCompleteKT)
                        <button class="btn btn-success btn-sm-custom" onclick="showCompleteKTModal()">
                            <i class="feather-check-square"></i> Complete KT
                        </button>
                    @endif

                    @if ($canUpdateClearance)
                        <button class="btn btn-warning btn-sm-custom" onclick="showClearanceModal()">
                            <i class="feather-shield"></i> Update Clearance
                        </button>
                    @endif

                    @if ($canProcessSettlement)
                        <button class="btn btn-info btn-sm-custom" onclick="showSettlementModal()">
                            <i class="feather-dollar-sign"></i> Process Settlement
                        </button>
                    @endif

                    @if ($canMarkSettlementPaid)
                        <button class="btn btn-success btn-sm-custom" onclick="showMarkPaidModal()">
                            <i class="feather-credit-card"></i> Mark as Paid
                        </button>
                    @endif

                    @if ($canComplete)
                        <button class="btn btn-primary btn-sm-custom" onclick="showCompleteModal()">
                            <i class="feather-check-square"></i> Complete Process
                        </button>
                    @endif

                    @if ($canCancel)
                        <button class="btn btn-secondary btn-sm-custom" onclick="showCancelModal()">
                            <i class="feather-slash"></i> Cancel
                        </button>
                    @endif
                </div>
            @endif
        </div>

        {{-- Progress Tracker --}}
        <div class="progress-tracker">
            <div class="info-title">
                <i class="feather-clock"></i> Offboarding Progress
            </div>

            @php
                $stages = [
                    [
                        'label'  => 'Notice Submitted',
                        'date'   => $offboarding->request_date,
                        'status' => $offboarding->request_date ? 'completed' : 'pending',
                        'note'   => null,
                    ],
                    [
                        'label'  => 'Manager Review',
                        'date'   => $offboarding->manager_review_at,
                        'status' => match($offboarding->manager_review_status) {
                            'approved' => 'completed',
                            'rejected' => 'rejected_stage',
                            default    => 'in_progress',
                        },
                        'note'   => $offboarding->manager_review_status === 'rejected'
                            ? ($offboarding->manager_review_comments ?? null)
                            : null,
                    ],
                    [
                        'label'  => 'HR Review',
                        'date'   => $offboarding->hr_review_at,
                        'status' => match($offboarding->hr_review_status) {
                            'approved' => 'completed',
                            'rejected' => 'rejected_stage',
                            default    => ($offboarding->manager_review_status == 'approved' ? 'in_progress' : 'pending'),
                        },
                        'note'   => $offboarding->hr_review_status === 'rejected'
                            ? ($offboarding->hr_review_comments ?? null)
                            : null,
                    ],
                    [
                        'label'  => 'Last Working Date',
                        'date'   => $offboarding->last_working_date,
                        'status' => in_array($offboarding->status, ['approved', 'completed']) ? 'completed' : 'pending',
                        'note'   => null,
                    ],
                    [
                        'label'  => 'Knowledge Transfer',
                        'date'   => $offboarding->knowledge_transfer_completed_at,
                        'status' => match($offboarding->knowledge_transfer_status) {
                            'completed'   => 'completed',
                            'in_progress' => 'in_progress',
                            default       => 'pending',
                        },
                        'note'   => null,
                    ],
                    [
                        'label'  => 'Asset Clearance',
                        'date'   => null,
                        'status' => match($offboarding->asset_return_status) {
                            'completed' => 'completed',
                            'partial'   => 'in_progress',
                            default     => 'pending',
                        },
                        'note'   => null,
                    ],
                    [
                        'label'  => 'Exit Interview',
                        'date'   => $offboarding->exit_interview_date,
                        'status' => match($offboarding->exit_interview_status) {
                            'completed' => 'completed',
                            'scheduled' => 'in_progress',
                            default     => 'pending',
                        },
                        'note'   => null,
                    ],
                    [
                        'label'  => 'Final Settlement',
                        'date'   => $offboarding->settlement_paid_date,
                        'status' => match($offboarding->final_settlement_status) {
                            'paid'       => 'completed',
                            'processing' => 'in_progress',
                            default      => 'pending',
                        },
                        'note'   => null,
                    ],
                ];
            @endphp

            @foreach ($stages as $stage)
                <div class="tracker-step">
                    <div class="tracker-icon {{ $stage['status'] }}">
                        @if ($stage['status'] === 'completed')
                            <i class="feather-check"></i>
                        @elseif ($stage['status'] === 'rejected_stage')
                            <i class="feather-x"></i>
                        @elseif ($stage['status'] === 'in_progress')
                            <i class="feather-loader"></i>
                        @else
                            <i class="feather-circle"></i>
                        @endif
                    </div>
                    <div class="tracker-content">
                        <div class="tracker-title">{{ $stage['label'] }}</div>
                        <div class="tracker-status">
                            @if ($stage['status'] === 'completed')
                                <span class="text-success">Completed</span>
                            @elseif ($stage['status'] === 'rejected_stage')
                                <span class="text-danger">Rejected</span>
                            @elseif ($stage['status'] === 'in_progress')
                                <span class="text-primary">In Progress</span>
                            @else
                                <span class="text-muted">Pending</span>
                            @endif
                        </div>
                        @if ($stage['date'])
                            <div class="tracker-date">{{ \Carbon\Carbon::parse($stage['date'])->format('d M Y') }}</div>
                        @endif
                        @if ($stage['note'])
                            <div class="tracker-date text-danger">Reason: {{ $stage['note'] }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            {{-- ======================== LEFT COLUMN ======================== --}}
            <div class="col-md-6">

                {{-- Employee Information --}}
                <div class="info-card">
                    <div class="info-title"><i class="feather-user"></i> Employee Information</div>
                    <div class="info-row">
                        <div class="info-label">Employee Name</div>
                        <div class="info-value"><strong>{{ $offboarding->employee->name ?? 'N/A' }}</strong></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Employee ID</div>
                        <div class="info-value">{{ $offboarding->employee->employee_id ?? 'N/A' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email</div>
                        <div class="info-value">{{ $offboarding->employee->email ?? 'N/A' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Phone</div>
                        <div class="info-value">{{ $offboarding->employee->contact ?? 'N/A' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Department</div>
                        <div class="info-value">{{ $offboarding->employee->jobDetails->Department->name ?? 'N/A' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Designation</div>
                        <div class="info-value">{{ $offboarding->employee->jobDetails->Designation->name ?? 'N/A' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Reporting Manager</div>
                        <div class="info-value">{{ $offboarding->employee->jobDetails->reportingHead->name ?? 'N/A' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Joining Date</div>
                        <div class="info-value">
                            {{ $offboarding->employee->jobDetails->joining_date
                                ? \Carbon\Carbon::parse($offboarding->employee->jobDetails->joining_date)->format('d M Y')
                                : 'N/A' }}
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Total Tenure</div>
                        <div class="info-value">
                            @if ($offboarding->employee->jobDetails->joining_date)
                                {{ \Carbon\Carbon::parse($offboarding->employee->jobDetails->joining_date)
                                    ->diffInDays(\Carbon\Carbon::parse($offboarding->last_working_date)) }} days
                            @else
                                N/A
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Offboarding Details --}}
                <div class="info-card">
                    <div class="info-title"><i class="feather-file-text"></i> Offboarding Details</div>
                    <div class="info-row">
                        <div class="info-label">Request Date</div>
                        <div class="info-value">{{ \Carbon\Carbon::parse($offboarding->request_date)->format('d M Y') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Last Working Date</div>
                        <div class="info-value">
                            {{ \Carbon\Carbon::parse($offboarding->last_working_date)->format('d M Y') }}
                            @php $daysLeft = \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($offboarding->last_working_date), false); @endphp
                            @if ($daysLeft > 0 && $offboarding->status == 'approved')
                                <span class="badge bg-info ms-2">{{ $daysLeft }} days left</span>
                            @endif
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Resignation Date</div>
                        <div class="info-value">
                            {{ $offboarding->resignation_date
                                ? \Carbon\Carbon::parse($offboarding->resignation_date)->format('d M Y')
                                : 'N/A' }}
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Notice Period</div>
                        <div class="info-value">
                            @if ($offboarding->resignation_date)
                                {{ \Carbon\Carbon::parse($offboarding->resignation_date)
                                    ->diffInDays(\Carbon\Carbon::parse($offboarding->last_working_date)) }} days
                            @else
                                N/A
                            @endif
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Reason</div>
                        <div class="info-value"><span class="badge bg-secondary">{{ $offboarding->reason_label }}</span></div>
                    </div>
                    @if ($offboarding->reason_detail)
                        <div class="info-row">
                            <div class="info-label">Reason Details</div>
                            <div class="info-value">{{ $offboarding->reason_detail }}</div>
                        </div>
                    @endif
                    @if ($offboarding->feedback && ($isAdminOrHR || $isRequester))
                        <div class="info-row">
                            <div class="info-label">Employee Feedback</div>
                            <div class="info-value">{{ $offboarding->feedback }}</div>
                        </div>
                    @endif
                    <div class="info-row">
                        <div class="info-label">Eligible for Rehire</div>
                        <div class="info-value">
                            @if ($offboarding->eligible_for_rehire)
                                <span class="badge bg-success">Yes</span>
                            @else
                                <span class="badge bg-danger">No</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Clearance Status --}}
                @if ($isAdminOrHR || $isManager)
                    <div class="info-card">
                        <div class="info-title"><i class="feather-shield"></i> Clearance Status</div>
                        <div class="info-row">
                            <div class="info-label">Asset Return</div>
                            <div class="info-value">
                                <span class="clearance-badge clearance-{{ $offboarding->asset_return_status }}">
                                    <i class="feather-box"></i>
                                    {{ ucfirst(str_replace('_', ' ', $offboarding->asset_return_status)) }}
                                </span>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Document Return</div>
                            <div class="info-value">
                                <span class="clearance-badge clearance-{{ $offboarding->document_return_status }}">
                                    <i class="feather-file-text"></i>
                                    {{ ucfirst(str_replace('_', ' ', $offboarding->document_return_status)) }}
                                </span>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Overall Clearance</div>
                            <div class="info-value">
                                <span class="clearance-badge clearance-{{ $offboarding->clearance_status }}">
                                    <i class="feather-check-circle"></i>
                                    {{ ucfirst(str_replace('_', ' ', $offboarding->clearance_status)) }}
                                </span>
                            </div>
                        </div>
                        @if ($offboarding->knowledge_transfer_status != 'not_started')
                            <div class="info-row">
                                <div class="info-label">Knowledge Transfer</div>
                                <div class="info-value">
                                    <span class="stage-badge stage-{{ $offboarding->knowledge_transfer_status }}">
                                        {{ ucfirst(str_replace('_', ' ', $offboarding->knowledge_transfer_status)) }}
                                    </span>
                                    @if ($canCompleteKT && $offboarding->knowledge_transfer_status == 'in_progress')
                                        <button class="knowledge-transfer-btn" onclick="showCompleteKTModal()">Complete KT</button>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- ======================== RIGHT COLUMN ======================== --}}
            <div class="col-md-6">

                {{-- Approval Information --}}
                <div class="info-card">
                    <div class="info-title"><i class="feather-check-circle"></i> Approval Information</div>
                    <div class="info-row">
                        <div class="info-label">Created By</div>
                        <div class="info-value">{{ $offboarding->createdBy->name ?? 'N/A' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Created At</div>
                        <div class="info-value">{{ $offboarding->created_at->format('d M Y, h:i A') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Manager Review</div>
                        <div class="info-value">
                            @if ($offboarding->manager_review_status == 'approved')
                                <span class="text-success">✓ Approved</span>
                                @if ($offboarding->managerReviewBy)
                                    <small class="text-muted d-block">
                                        by {{ $offboarding->managerReviewBy->name }}
                                        on {{ \Carbon\Carbon::parse($offboarding->manager_review_at)->format('d M Y') }}
                                    </small>
                                @endif
                            @elseif ($offboarding->manager_review_status == 'rejected')
                                <span class="text-danger">✗ Rejected</span>
                                @if ($offboarding->manager_review_comments)
                                    <small class="text-danger d-block">{{ $offboarding->manager_review_comments }}</small>
                                @endif
                            @else
                                <span class="text-warning">⏳ Pending</span>
                            @endif
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">HR Review</div>
                        <div class="info-value">
                            @if ($offboarding->hr_review_status == 'approved')
                                <span class="text-success">✓ Approved</span>
                                @if ($offboarding->hrReviewBy)
                                    <small class="text-muted d-block">
                                        by {{ $offboarding->hrReviewBy->name }}
                                        on {{ \Carbon\Carbon::parse($offboarding->hr_review_at)->format('d M Y') }}
                                    </small>
                                @endif
                            @elseif ($offboarding->hr_review_status == 'rejected')
                                <span class="text-danger">✗ Rejected</span>
                                @if ($offboarding->hr_review_comments)
                                    <small class="text-danger d-block">{{ $offboarding->hr_review_comments }}</small>
                                @endif
                            @else
                                <span class="text-warning">⏳ Pending</span>
                            @endif
                        </div>
                    </div>
                    @if ($offboarding->approved_by)
                        <div class="info-row">
                            <div class="info-label">Final Approved By</div>
                            <div class="info-value">{{ $offboarding->approvedBy->name ?? 'N/A' }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Final Approved At</div>
                            <div class="info-value">
                                {{ $offboarding->approved_at
                                    ? \Carbon\Carbon::parse($offboarding->approved_at)->format('d M Y, h:i A')
                                    : 'N/A' }}
                            </div>
                        </div>
                    @endif
                    @if ($offboarding->completed_at)
                        <div class="info-row">
                            <div class="info-label">Completed At</div>
                            <div class="info-value">{{ \Carbon\Carbon::parse($offboarding->completed_at)->format('d M Y, h:i A') }}</div>
                        </div>
                    @endif
                </div>

                {{-- Settlement Information --}}
                @if ($isAdminOrHR)
                    <div class="info-card">
                        <div class="info-title"><i class="fa-solid fa-rupee-sign"></i> Settlement Information</div>
                        <div class="info-row">
                            <div class="info-label">Settlement Status</div>
                            <div class="info-value">
                                <span class="stage-badge stage-{{ $offboarding->final_settlement_status }}">
                                    {{ ucfirst($offboarding->final_settlement_status) }}
                                </span>
                                @if ($canMarkSettlementPaid)
                                    <button class="knowledge-transfer-btn" onclick="showMarkPaidModal()">Mark as Paid</button>
                                @endif
                            </div>
                        </div>
                        @if ($offboarding->full_final_settlement)
                            <div class="info-row">
                                <div class="info-label">Amount</div>
                                <div class="info-value"><strong>₹{{ number_format($offboarding->full_final_settlement, 2) }}</strong></div>
                            </div>
                        @endif
                        @if ($offboarding->settlement_paid_date)
                            <div class="info-row">
                                <div class="info-label">Paid Date</div>
                                <div class="info-value">{{ \Carbon\Carbon::parse($offboarding->settlement_paid_date)->format('d M Y') }}</div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Exit Interview --}}
                @if ($offboarding->exitInterview)
                    @php $ei = $offboarding->exitInterview; @endphp
                    <div class="info-card exit-interview-card">
                        <div class="info-title">
                            <i class="feather-mic"></i> Exit Interview Details
                            @if ($ei->status)
                                <span class="stage-badge stage-{{ $ei->status }}" style="background:rgba(255,255,255,0.2);">
                                    {{ ucfirst($ei->status) }}
                                </span>
                            @endif
                        </div>
                        <div class="info-row">
                            <div class="info-label">Interview Date</div>
                            <div class="info-value">{{ \Carbon\Carbon::parse($ei->interview_date)->format('d M Y, h:i A') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Interviewer</div>
                            <div class="info-value">{{ $ei->interviewer->name ?? 'N/A' }}</div>
                        </div>
                        <div class="rating-section">
                            <div class="info-title" style="border-bottom-color:rgba(255,255,255,0.2);font-size:12px;">
                                <i class="feather-star"></i> Ratings (1-5)
                            </div>
                            @foreach ([
                                'Work Environment'  => $ei->work_environment_rating,
                                'Management'        => $ei->management_rating,
                                'Career Growth'     => $ei->career_growth_rating,
                                'Compensation'      => $ei->compensation_rating,
                                'Work-Life Balance' => $ei->work_life_balance_rating,
                            ] as $label => $rating)
                                <div class="info-row">
                                    <div class="info-label">{{ $label }}</div>
                                    <div class="info-value">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <i class="feather-star {{ $i <= ($rating ?? 0) ? 'text-warning' : 'text-white opacity-50' }}"></i>
                                        @endfor
                                        <span class="ms-2">({{ $rating ?? 'N/A' }}/5)</span>
                                    </div>
                                </div>
                            @endforeach
                            @php
                                $ratings   = array_filter([$ei->work_environment_rating, $ei->management_rating, $ei->career_growth_rating, $ei->compensation_rating, $ei->work_life_balance_rating]);
                                $avgRating = !empty($ratings) ? array_sum($ratings) / count($ratings) : 0;
                            @endphp
                            <div class="info-row">
                                <div class="info-label">Overall Rating</div>
                                <div class="info-value">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="feather-star {{ $i <= round($avgRating) ? 'text-warning' : 'text-white opacity-50' }}"></i>
                                    @endfor
                                    <span class="ms-2">({{ number_format($avgRating, 1) }}/5)</span>
                                </div>
                            </div>
                        </div>
                        @if ($ei->primary_reason)
                            <div class="info-row mt-2"><div class="info-label">Primary Reason</div><div class="info-value">{{ $ei->primary_reason }}</div></div>
                        @endif
                        @if ($ei->what_would_improve)
                            <div class="info-row"><div class="info-label">What would improve?</div><div class="info-value">{{ $ei->what_would_improve }}</div></div>
                        @endif
                        @if ($ei->would_recommend !== null)
                            <div class="info-row">
                                <div class="info-label">Would Recommend</div>
                                <div class="info-value">
                                    @if ($ei->would_recommend) <span class="badge bg-success">Yes</span> @else <span class="badge bg-danger">No</span> @endif
                                </div>
                            </div>
                        @endif
                        @if ($ei->feedback_comments)
                            <div class="info-row"><div class="info-label">Additional Comments</div><div class="info-value">{{ $ei->feedback_comments }}</div></div>
                        @endif
                        @if ($ei->suggestions)
                            <div class="info-row"><div class="info-label">Suggestions</div><div class="info-value">{{ $ei->suggestions }}</div></div>
                        @endif
                        @if ($canUpdateExitInterview && $offboarding->exit_interview_status == 'scheduled')
                            <div style="display:flex;gap:10px;margin-top:15px;flex-wrap:wrap;">
                                <button class="btn btn-outline-light btn-sm" onclick="showCompleteExitInterviewModal({{ $ei->id }})">
                                    <i class="feather-check-circle"></i> Mark as Completed
                                </button>
                            </div>
                        @endif
                    </div>
                @else
                    @if ($canConductExit)
                        <div class="info-card">
                            <div class="info-title"><i class="feather-mic"></i> Exit Interview</div>
                            <div class="text-center py-4">
                                <i class="feather-mic-off" style="font-size:48px;color:#cbd5e1;"></i>
                                <p class="text-muted mt-2 mb-3">No exit interview recorded yet.</p>
                                <button class="btn btn-primary btn-sm-custom" onclick="showExitInterviewModal()">
                                    <i class="feather-plus"></i> Conduct Exit Interview
                                </button>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection

@section('create-modal')

    {{-- ==================== MODAL 1: Approve Manager ==================== --}}
    <div class="modal fade" id="approveManagerModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Approve — Manager Review</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('offboarding.manager-review', $offboarding->id) }}">
                    @csrf
                    <input type="hidden" name="action" value="approve">
                    <div class="modal-body">
                        <p>Approve this offboarding request at the Manager Review stage?</p>
                        <div class="modal-form-group">
                            <label>Comments (Optional)</label>
                            <textarea name="comments" class="form-control form-control-sm" rows="2" placeholder="Add any comments..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL 2: Approve HR ==================== --}}
    <div class="modal fade" id="approveHRModal" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Approve — HR Review</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('offboarding.hr-review', $offboarding->id) }}">
                    @csrf
                    <input type="hidden" name="action" value="approve">
                    <div class="modal-body">
                        <div class="modal-form-group">
                            <label>Last Working Date</label>
                            <input type="date" name="last_working_date" class="form-control form-control-sm"
                                value="{{ $offboarding->last_working_date ?? '' }}">
                        </div>
                        <div class="modal-form-group">
                            <label>HR Comments</label>
                            <textarea name="comments" class="form-control form-control-sm" rows="2" placeholder="Add HR comments..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL 3: REJECT (unified, stage-aware) ==================== --}}
    {{--
        This single modal handles rejection at any stage.
        The controller's reject() method auto-detects which review stage to update.
        A hidden field `reject_stage` is passed as an informational hint.
    --}}
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header text-white">
                    <h6 class="modal-title"><i class="feather-x-circle me-2"></i>Reject Offboarding Request</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('offboarding.reject', $offboarding->id) }}">
                    @csrf
                    <input type="hidden" name="reject_stage" value="{{ $currentRejectStage }}">
                    <div class="modal-body">

                        {{-- Stage info box --}}
                        <div class="reject-stage-info">
                            <strong>
                                <i class="feather-info"></i>
                                Rejecting at: {{ $currentRejectStageLabel }}
                            </strong>
                            {{ $currentRejectStageDesc }}
                        </div>

                        {{-- <div class="info-row mb-2" style="font-size:13px;">
                            <div class="info-label">Employee</div>
                            <div class="info-value"><strong>{{ $offboarding->employee->name ?? 'N/A' }}</strong></div>
                        </div>
                        <div class="info-row mb-3" style="font-size:13px;">
                            <div class="info-label">Request Code</div>
                            <div class="info-value">{{ $offboarding->request_code }}</div>
                        </div> --}}

                        <div class="modal-form-group">
                            <label class="required">Rejection Reason <span style="color:red"></span></label>
                            <textarea name="rejection_reason" class="form-control form-control-sm" rows="3"
                                placeholder="Provide a clear reason for rejection..." required minlength="3"></textarea>
                            <small class="text-muted">This reason will be visible to the employee and recorded in the system.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="feather-x-circle me-1"></i> Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL 4: Start Knowledge Transfer ==================== --}}
    <div class="modal fade" id="startKTModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Start Knowledge Transfer</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('offboarding.knowledge-transfer.start', $offboarding->id) }}">
                    @csrf
                    <div class="modal-body">
                        <p>Start the knowledge transfer process for <strong>{{ $offboarding->employee->name }}</strong>?</p>
                        <div class="modal-form-group">
                            <label>Notes (Optional)</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Add any notes about KT..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Start KT</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL 5: Complete Knowledge Transfer ==================== --}}
    <div class="modal fade" id="completeKTModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Complete Knowledge Transfer</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('offboarding.knowledge-transfer.complete', $offboarding->id) }}">
                    @csrf
                    <div class="modal-body">
                        <p>Mark knowledge transfer as completed?</p>
                        <div class="modal-form-group">
                            <label>Completion Notes</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Add completion notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">Complete KT</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL 6: Update Clearance ==================== --}}
    <div class="modal fade" id="clearanceModal" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Update Clearance Status</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('offboarding.asset-clearance.update', $offboarding->id) }}">
                    @csrf
                    <div class="modal-body">
                        <div class="modal-form-group">
                            <label class="required">Asset Return Status</label>
                            <select name="asset_return_status" class="form-control form-control-sm" required>
                                <option value="pending"   {{ $offboarding->asset_return_status == 'pending'   ? 'selected' : '' }}>Pending</option>
                                <option value="partial"   {{ $offboarding->asset_return_status == 'partial'   ? 'selected' : '' }}>Partial</option>
                                <option value="completed" {{ $offboarding->asset_return_status == 'completed' ? 'selected' : '' }}>Completed</option>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label class="required">Document Return Status</label>
                            <select name="document_return_status" class="form-control form-control-sm" required>
                                <option value="pending"   {{ $offboarding->document_return_status == 'pending'   ? 'selected' : '' }}>Pending</option>
                                <option value="partial"   {{ $offboarding->document_return_status == 'partial'   ? 'selected' : '' }}>Partial</option>
                                <option value="completed" {{ $offboarding->document_return_status == 'completed' ? 'selected' : '' }}>Completed</option>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label class="required">Overall Clearance Status</label>
                            <select name="clearance_status" class="form-control form-control-sm" required>
                                <option value="pending"     {{ $offboarding->clearance_status == 'pending'     ? 'selected' : '' }}>Pending</option>
                                <option value="in_progress" {{ $offboarding->clearance_status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="completed"   {{ $offboarding->clearance_status == 'completed'   ? 'selected' : '' }}>Completed</option>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label>Remarks (Optional)</label>
                            <textarea name="clearance_remarks" class="form-control form-control-sm" rows="2" placeholder="Add any remarks..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Update Clearance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL 7: Process Final Settlement ==================== --}}
    <div class="modal fade" id="settlementModal" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Process Final Settlement</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('offboarding.final-settlement.process', $offboarding->id) }}">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-warning mb-3">
                            <i class="feather-alert-triangle"></i>
                            <strong>Note:</strong> Asset clearance must be completed before processing settlement.
                        </div>
                        <div class="modal-form-group">
                            <label class="required">Full & Final Settlement Amount (₹)</label>
                            <input type="number" name="full_final_settlement" class="form-control form-control-sm"
                                step="1000" placeholder="Enter settlement amount" required
                                value="{{ $offboarding->full_final_settlement }}">
                        </div>
                        <div class="modal-form-group">
                            <label>Settlement Notes</label>
                            <textarea name="settlement_notes" class="form-control form-control-sm" rows="2" placeholder="Add any notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning btn-sm">Process Settlement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL 8: Mark Settlement Paid ==================== --}}
    <div class="modal fade" id="markPaidModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Mark Settlement as Paid</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('offboarding.final-settlement.paid', $offboarding->id) }}">
                    @csrf
                    <div class="modal-body">
                        <div class="modal-form-group">
                            <label class="required">Payment Date</label>
                            <input type="date" name="settlement_paid_date" class="form-control form-control-sm"
                                value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="modal-form-group">
                            <label>Payment Reference / Transaction ID</label>
                            <input type="text" name="payment_reference" class="form-control form-control-sm"
                                placeholder="Transaction ID / Cheque No / UTR">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">Mark as Paid</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL 9: Complete Process ==================== --}}
    <div class="modal fade" id="completeModal" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Complete Offboarding Process</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('offboarding.complete', $offboarding->id) }}">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-success mb-3">
                            <i class="feather-check-circle"></i> <strong>Ready to complete offboarding!</strong>
                            <ul class="mb-0 mt-2">
                                <li>✓ Knowledge Transfer: <strong>{{ ucfirst($offboarding->knowledge_transfer_status) }}</strong></li>
                                <li>✓ Asset Clearance: <strong>{{ ucfirst($offboarding->asset_return_status) }}</strong></li>
                                <li>✓ Final Settlement: <strong>{{ ucfirst($offboarding->final_settlement_status) }}</strong></li>
                            </ul>
                        </div>
                        <div class="modal-form-group">
                            <label>Completion Remarks</label>
                            <textarea name="completion_remarks" class="form-control form-control-sm" rows="3"
                                placeholder="Any final remarks..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Complete Offboarding</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL 10: Cancel ==================== --}}
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Cancel Offboarding Request</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('offboarding.cancel', $offboarding->id) }}">
                    @csrf
                    <div class="modal-body">
                        <p>Are you sure you want to cancel this offboarding request? This action restores the employee to active status.</p>
                        <div class="modal-form-group">
                            <label>Cancellation Reason (Optional)</label>
                            <textarea name="cancellation_reason" class="form-control form-control-sm" rows="2" placeholder="Enter reason..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger btn-sm">Cancel Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL 11: Complete Exit Interview ==================== --}}
    <div class="modal fade" id="completeExitInterviewModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Complete Exit Interview</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="completeExitInterviewForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <p>Mark this exit interview as completed?</p>
                        <div class="modal-form-group">
                            <label>Completion Notes</label>
                            <textarea name="completion_notes" class="form-control form-control-sm" rows="3" placeholder="Add any notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">Mark as Completed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL 12: Exit Interview Create ==================== --}}
    <div class="modal fade" id="exitInterviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="feather-mic"></i> Exit Interview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('offboarding.exit-interview.store', $offboarding->id) }}">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="modal-form-group">
                                    <label class="required">Interviewer</label>
                                    <select name="interviewer_id" class="rating-select" required>
                                        <option value="">Select Interviewer</option>
                                        @foreach ($hrUsers as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="modal-form-group">
                                    <label class="required">Interview Date & Time</label>
                                    <input type="datetime-local" name="interview_date" class="rating-select" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            @foreach ([
                                'work_environment_rating'  => 'Work Environment',
                                'management_rating'        => 'Management',
                                'career_growth_rating'     => 'Career Growth',
                            ] as $field => $label)
                                <div class="col-md-4">
                                    <div class="modal-form-group">
                                        <label>{{ $label }}</label>
                                        <select name="{{ $field }}" class="rating-select">
                                            <option value="">Select Rating</option>
                                            @for ($i = 1; $i <= 5; $i++)
                                                <option value="{{ $i }}">{{ $i }} - {{ ['','Very Poor','Poor','Average','Good','Excellent'][$i] }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="row">
                            @foreach ([
                                'compensation_rating'      => 'Compensation & Benefits',
                                'work_life_balance_rating' => 'Work-Life Balance',
                            ] as $field => $label)
                                <div class="col-md-6">
                                    <div class="modal-form-group">
                                        <label>{{ $label }}</label>
                                        <select name="{{ $field }}" class="rating-select">
                                            <option value="">Select Rating</option>
                                            @for ($i = 1; $i <= 5; $i++)
                                                <option value="{{ $i }}">{{ $i }} - {{ ['','Very Poor','Poor','Average','Good','Excellent'][$i] }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="modal-form-group">
                            <label>Primary Reason for Leaving</label>
                            <textarea name="primary_reason" class="rating-select" rows="2" placeholder="Why is the employee leaving?"></textarea>
                        </div>
                        <div class="modal-form-group">
                            <label>What would have made you stay?</label>
                            <textarea name="what_would_improve" class="rating-select" rows="2" placeholder="Improvements that would have encouraged staying..."></textarea>
                        </div>
                        <div class="modal-form-group">
                            <label>Would you recommend this company?</label>
                            <select name="would_recommend" class="rating-select">
                                <option value="">Select</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label>Additional Comments</label>
                            <textarea name="feedback_comments" class="rating-select" rows="2" placeholder="Any additional feedback..."></textarea>
                        </div>
                        <div class="modal-form-group">
                            <label>Suggestions for Improvement</label>
                            <textarea name="suggestions" class="rating-select" rows="2" placeholder="Suggestions for the company..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="feather-save"></i> Save Exit Interview</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@section('script-area')
    <script>
        // Simple modal openers — forms now have hard-coded actions (no JS action setting needed)
        function showApproveManagerModal() { $('#approveManagerModal').modal('show'); }
        function showHRApproveModal()       { $('#approveHRModal').modal('show'); }
        function showRejectModal()          { $('#rejectModal').modal('show'); }
        function showStartKTModal()         { $('#startKTModal').modal('show'); }
        function showCompleteKTModal()      { $('#completeKTModal').modal('show'); }
        function showClearanceModal()       { $('#clearanceModal').modal('show'); }
        function showSettlementModal()      { $('#settlementModal').modal('show'); }
        function showMarkPaidModal()        { $('#markPaidModal').modal('show'); }
        function showCompleteModal()        { $('#completeModal').modal('show'); }
        function showCancelModal()          { $('#cancelModal').modal('show'); }
        function showExitInterviewModal()   { $('#exitInterviewModal').modal('show'); }

        function showCompleteExitInterviewModal(interviewId) {
            $('#completeExitInterviewForm').attr(
                'action',
                '{{ route("offboarding.exit-interview.update", [$offboarding->id, "__EXITID__"]) }}'.replace('__EXITID__', interviewId)
            );
            $('#completeExitInterviewModal').modal('show');
        }
    </script>
@endsection