{{-- resources/views/client/offboarding/index_employee.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        .employee-badge {
            background: #10b981;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            margin-left: 8px;
        }

        .tracker-step {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .tracker-icon {
            width: 40px;
            height: 40px;
            background: #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .tracker-icon.completed {
            background: #10b981;
            color: white;
        }

        .tracker-icon.active {
            background: #4f46e5;
            color: white;
        }

        .tracker-content {
            flex: 1;
        }

        .tracker-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .tracker-status {
            font-size: 12px;
            color: #64748b;
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-title">
                <h5>My Offboarding Request
                    {{-- <span class="employee-badge">Employee View</span> --}}
                </h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">My Offboarding</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            @if (!$hasActiveRequest)
                <a href="{{ route('offboarding.create') }}?employee_id={{ auth()->user()->id }}"
                    class="btn btn-primary btn-sm">
                    <i class="feather-user-minus me-2"></i> Initiate Offboarding
                </a>
            @endif
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">

        @if ($hasActiveRequest)
            @php $offboarding = $activeRequest; @endphp

            <!-- Status Banner -->
            <div
                class="alert {{ $offboarding->status == 'pending_approval' ? 'alert-warning' : ($offboarding->status == 'approved' ? 'alert-info' : 'alert-success') }} mb-4">
                <i class="feather-info"></i>
                <strong>Your offboarding request is {{ ucfirst(str_replace('_', ' ', $offboarding->status)) }}.</strong>
                @if ($offboarding->status == 'pending_approval')
                    Please wait for manager and HR approval.
                @elseif($offboarding->status == 'approved')
                    Your offboarding has been approved. Last working date:
                    {{ \Carbon\Carbon::parse($offboarding->last_working_date)->format('d M, Y') }}
                @elseif($offboarding->status == 'completed')
                    Your offboarding process has been completed.
                @endif
            </div>

            <!-- Progress Tracker -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Offboarding Progress</h5>
                </div>
                <div class="card-body">
                    <div class="tracker-step">
                        <div class="tracker-icon {{ $offboarding->request_date ? 'completed' : '' }}">
                            <i class="feather-file-text"></i>
                        </div>
                        <div class="tracker-content">
                            <div class="tracker-title">Notice Submitted</div>
                            <div class="tracker-status">
                                {{ \Carbon\Carbon::parse($offboarding->request_date)->format('d M, Y') }}</div>
                        </div>
                    </div>

                    <div class="tracker-step">
                        <div
                            class="tracker-icon {{ $offboarding->manager_review_status == 'approved' ? 'completed' : ($offboarding->manager_review_status == 'pending' ? 'active' : '') }}">
                            <i class="feather-user-check"></i>
                        </div>
                        <div class="tracker-content">
                            <div class="tracker-title">Manager Review</div>
                            <div class="tracker-status">
                                @if ($offboarding->manager_review_status == 'pending')
                                    <span class="text-warning">Awaiting manager approval</span>
                                @elseif($offboarding->manager_review_status == 'approved')
                                    <span class="text-success">Approved by manager</span>
                                @elseif($offboarding->manager_review_status == 'rejected')
                                    <span class="text-danger">Rejected by manager</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="tracker-step">
                        <div
                            class="tracker-icon {{ $offboarding->hr_review_status == 'approved' ? 'completed' : ($offboarding->hr_review_status == 'pending' && $offboarding->manager_review_status == 'approved' ? 'active' : '') }}">
                            <i class="feather-users"></i>
                        </div>
                        <div class="tracker-content">
                            <div class="tracker-title">HR Review</div>
                            <div class="tracker-status">
                                @if ($offboarding->hr_review_status == 'pending')
                                    <span>Waiting for HR approval</span>
                                @elseif($offboarding->hr_review_status == 'approved')
                                    <span class="text-success">Approved by HR</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="tracker-step">
                        <div class="tracker-icon {{ $offboarding->last_working_date ? 'completed' : '' }}">
                            <i class="feather-calendar"></i>
                        </div>
                        <div class="tracker-content">
                            <div class="tracker-title">Last Working Date</div>
                            <div class="tracker-status">
                                {{ \Carbon\Carbon::parse($offboarding->last_working_date)->format('d M, Y') }}</div>
                        </div>
                    </div>

                    <div class="tracker-step">
                        <div
                            class="tracker-icon {{ $offboarding->knowledge_transfer_status == 'completed' ? 'completed' : ($offboarding->knowledge_transfer_status == 'in_progress' ? 'active' : '') }}">
                            <i class="feather-upload"></i>
                        </div>
                        <div class="tracker-content">
                            <div class="tracker-title">Knowledge Transfer</div>
                            <div class="tracker-status">
                                @if ($offboarding->knowledge_transfer_status == 'not_started')
                                    <span>Not started yet</span>
                                @elseif($offboarding->knowledge_transfer_status == 'in_progress')
                                    <span class="text-warning">In progress</span>
                                @elseif($offboarding->knowledge_transfer_status == 'completed')
                                    <span class="text-success">Completed</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="tracker-step">
                        <div
                            class="tracker-icon {{ $offboarding->asset_return_status == 'completed' ? 'completed' : '' }}">
                            <i class="feather-box"></i>
                        </div>
                        <div class="tracker-content">
                            <div class="tracker-title">Asset Clearance</div>
                            <div class="tracker-status">{{ ucfirst($offboarding->asset_return_status) }}</div>
                        </div>
                    </div>

                    <div class="tracker-step">
                        <div
                            class="tracker-icon {{ $offboarding->final_settlement_status == 'paid' ? 'completed' : ($offboarding->final_settlement_status == 'processing' ? 'active' : '') }}">
                            <i class="feather-credit-card"></i>
                        </div>
                        <div class="tracker-content">
                            <div class="tracker-title">Final Settlement</div>
                            <div class="tracker-status">
                                @if ($offboarding->final_settlement_status == 'pending')
                                    <span>Pending</span>
                                @elseif($offboarding->final_settlement_status == 'processing')
                                    <span class="text-warning">Processing</span>
                                @elseif($offboarding->final_settlement_status == 'paid')
                                    <span class="text-success">Paid</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Request Details -->
            <div class="card">
                <div class="card-header">
                    <h5>Request Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="200">Request Code</th>
                            <td>{{ $offboarding->request_code }}</td>
                        </tr>
                        <tr>
                            <th>Reason for Leaving</th>
                            <td>{{ ucfirst($offboarding->reason) }} @if ($offboarding->reason_detail)
                                    - {{ $offboarding->reason_detail }}
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Resignation Date</th>
                            <td>{{ \Carbon\Carbon::parse($offboarding->resignation_date)->format('d M, Y') }}</td>
                        </tr>
                        <tr>
                            <th>Last Working Date</th>
                            <td>{{ \Carbon\Carbon::parse($offboarding->last_working_date)->format('d M, Y') }}</td>
                        </tr>
                        <tr>
                            <th>Notice Period</th>
                            <td>{{ \Carbon\Carbon::parse($offboarding->resignation_date)->diffInDays(\Carbon\Carbon::parse($offboarding->last_working_date)) }}
                                days</td>
                        </tr>
                        @if ($offboarding->feedback)
                            <tr>
                                <th>Your Feedback</th>
                                <td>{{ $offboarding->feedback }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>
        @else
            <!-- No Active Request -->
            <div class="text-center py-5">
                <i class="feather-inbox" style="font-size: 64px; color: #cbd5e1;"></i>
                <h4 class="mt-3">No Offboarding Request Found</h4>
                <p class="text-muted">You haven't initiated any offboarding request yet.</p>
                {{-- <a href="{{ route('offboarding.create') }}?employee_id={{ auth()->user()->id }}" class="btn btn-primary">
                    <i class="feather-user-minus"></i> Initiate Offboarding Request
                </a> --}}
            </div>
        @endif
    </div>
@endsection
