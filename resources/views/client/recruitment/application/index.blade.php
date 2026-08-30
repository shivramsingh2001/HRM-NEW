{{-- resources/views/client/recruitment/job-openings/applications.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        /* ── Schedule / Feedback Modal ── */
        .schedule-form-group {
            margin-bottom: 15px;
        }

        .schedule-form-group label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
            color: #334155;
        }

        .schedule-form-group .required:after {
            content: "*";
            color: #dc3545;
            margin-left: 4px;
        }

        .schedule-form-control {
            width: 100%;
            padding: 8px 12px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .schedule-form-control:focus {
            border-color: #4f46e5;
            outline: none;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }

        /* ── Stats Cards ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .stats-card {
            background: #fff;
            border: 1px solid #eef2f6;
            border-radius: 8px;
            padding: 10px 12px;
            text-align: center;
            transition: all 0.2s;
        }

        .stats-card:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
            border-color: #cbd5e1;
        }

        .stats-number {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }

        .stats-label {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-top: 4px;
        }

        /* ── Filter ── */
        .filter-wrapper {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #eef2f6;
            padding: 12px 15px;
            margin-bottom: 15px;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 10px;
        }

        .filter-item {
            flex: 0 0 auto;
            min-width: 160px;
        }

        .filter-select {
            width: 100%;
            height: 34px;
            padding: 5px 24px 5px 8px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #f8fafc;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 6px center;
            background-size: 12px;
            appearance: none;
            cursor: pointer;
        }

        .filter-select:focus {
            border-color: #4f46e5;
            outline: none;
        }

        .search-input {
            width: 100%;
            height: 34px;
            padding: 5px 8px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }

        .search-input:focus {
            border-color: #4f46e5;
            outline: none;
        }

        .reset-btn {
            height: 34px;
            padding: 0 14px;
            background: #fff;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .reset-btn:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        /* ── Table ── */
        .table th {
            background: #f8fafc;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .3px;
            color: #475569;
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .table td {
            font-size: 12px;
            padding: 10px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        /* ── Stage Badges ── */
        .stage-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
        }

        .stage-application_received {
            background: #e0e7ff;
            color: #3730a3;
        }

        .stage-cv_shortlisted {
            background: #d1fae5;
            color: #065f46;
        }

        .stage-cv_rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .stage-interview_scheduled {
            background: #fef3c7;
            color: #92400e;
        }

        .stage-interview_completed {
            background: #dbeafe;
            color: #1e40af;
        }

        .stage-offer_released {
            background: #dbeafe;
            color: #1e40af;
        }

        .stage-offer_accepted {
            background: #dcfce7;
            color: #166534;
        }

        .stage-onboarded {
            background: #d1fae5;
            color: #065f46;
        }

        .stage-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .stage-cv_rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ── Action Btn ── */
        .action-btn {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: #fff;
            color: #4f46e5;
            border-color: #4f46e5;
        }

        .dropdown-item {
            font-size: 12px;
            padding: 6px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── Misc ── */
        .resume-link {
            color: #4f46e5;
            text-decoration: none;
            font-size: 11px;
        }

        .resume-link:hover {
            text-decoration: underline;
        }

        .pagination {
            margin: 0;
            gap: 4px;
        }

        .page-link {
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 11px;
            padding: 5px 10px;
            border-radius: 6px !important;
        }

        .page-item.active .page-link {
            background: #4f46e5;
            border-color: #4f46e5;
        }

        .empty-state {
            padding: 40px 20px;
            text-align: center;
        }

        .empty-state i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 12px;
        }

        .empty-state h4 {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        .empty-state p {
            font-size: 11px;
            color: #64748b;
        }

        .job-info-header {
            background: #f8fafc;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .job-title-code {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .job-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .job-code {
            background: #eef2ff;
            padding: 3px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 11px;
            color: #4f46e5;
        }

        @media(max-width:768px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-item {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header" style="margin-bottom:10px;">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-5" style="font-size:16px;">Job Applications</h5>
            </div>
            <ul class="breadcrumb" style="margin-left:12px;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('job-openings.index') }}">Job Openings</a></li>
                <li class="breadcrumb-item"><a
                        href="{{ route('job-openings.show', $jobOpening->id) }}">{{ $jobOpening->title }}</a></li>
                <li class="breadcrumb-item active">Applications</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding:10px 15px !important;">

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert"
                style="font-size:12px; padding:8px 12px;">
                <i class="feather-check-circle me-1"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:8px;"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert"
                style="font-size:12px; padding:8px 12px;">
                <i class="feather-alert-circle me-1"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:8px;"></button>
            </div>
        @endif

        {{-- Job Info Header --}}
        <div class="job-info-header">
            <div class="job-title-code">
                <span class="job-title">{{ $jobOpening->title }}</span>
                <span class="job-code">{{ $jobOpening->job_code }}</span>
                @if ($jobOpening->location)
                    <span class="text-muted" style="font-size:11px;"><i class="feather-map-pin"></i>
                        {{ $jobOpening->location }}</span>
                @endif
            </div>
            <div>
                <span class="badge bg-secondary" style="font-size:10px;">
                    <i class="feather-briefcase"></i> {{ ucfirst(str_replace('_', ' ', $jobOpening->employment_type)) }}
                </span>
                @if ($jobOpening->status === 'published')
                    <span class="badge bg-success" style="font-size:10px;">Published</span>
                @elseif($jobOpening->status === 'draft')
                    <span class="badge bg-warning" style="font-size:10px;">Draft</span>
                @else
                    <span class="badge bg-danger" style="font-size:10px;">Closed</span>
                @endif
            </div>
        </div>

        {{-- Stats --}}
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-number">{{ $applicationStats['total_applications'] }}</div>
                <div class="stats-label">Total</div>
            </div>
            <div class="stats-card">
                <div class="stats-number" style="color:#7c3aed;">{{ $applicationStats['application_received'] }}</div>
                <div class="stats-label">Received</div>
            </div>
            <div class="stats-card">
                <div class="stats-number text-success">{{ $applicationStats['shortlisted'] }}</div>
                <div class="stats-label">Shortlisted</div>
            </div>
            <div class="stats-card">
                <div class="stats-number text-warning">{{ $applicationStats['interview_scheduled'] }}</div>
                <div class="stats-label">Interviewing</div>
            </div>
            <div class="stats-card">
                <div class="stats-number text-info">{{ $applicationStats['interview_completed'] }}</div>
                <div class="stats-label">Selected</div>
            </div>
            <div class="stats-card">
                <div class="stats-number text-primary">{{ $applicationStats['offered'] }}</div>
                <div class="stats-label">Offered</div>
            </div>
            <div class="stats-card">
                <div class="stats-number text-success">{{ $applicationStats['onboarded'] }}</div>
                <div class="stats-label">Onboarded</div>
            </div>
            <div class="stats-card">
                <div class="stats-number text-danger">{{ $applicationStats['rejected'] }}</div>
                <div class="stats-label">Rejected</div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="filter-wrapper">
            <form action="{{ route('job-openings.applications', $jobOpening->id) }}" method="GET">
                <div class="filter-row">
                    <div class="filter-item">
                        <select name="stage" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Stages</option>
                            @foreach ([
            'application_received' => 'Application Received',
            'cv_shortlisted' => 'CV Shortlisted',
            'cv_rejected' => 'CV Rejected',
            'interview_scheduled' => 'Interview Scheduled',
            'interview_completed' => 'Interview Completed',
            'offer_released' => 'Offer Released',
            'offer_accepted' => 'Offer Accepted',
            'onboarded' => 'Onboarded',
            'rejected' => 'Rejected',
        ] as $val => $label)
                                <option value="{{ $val }}" {{ request('stage') === $val ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item" style="flex:1;min-width:200px;">
                        <input type="text" name="search" class="search-input"
                            placeholder="Search by name, email or phone…" value="{{ request('search') }}"
                            onkeyup="if(event.keyCode===13)this.form.submit()">
                    </div>
                    <div class="filter-item" style="min-width:auto;">
                        <a href="{{ route('job-openings.applications', $jobOpening->id) }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        {{-- Applications Table --}}
        <div class="card stretch stretch-full">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0" style="font-size:13px;font-weight:600;">
                    <i class="feather-users"></i> Applicants List
                </h5>
                <span class="badge bg-light text-dark" style="font-size:10px;">
                    {{ $applications->total() }} Total
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th width="40">#</th>
                                <th>Candidate</th>
                                <th>Contact</th>
                                <th>Experience</th>
                                <th>Current CTC</th>
                                <th>Expected CTC</th>
                                <th>Applied</th>
                                <th>Stage</th>
                                <th width="60">Resume</th>
                                <th width="80">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($applications as $index => $application)
                                @php
                                    $candidate = $application->candidate;
                                    $stage = $application->current_stage;
                                    // Latest SCHEDULED interview (for Complete Interview action)
                                    $scheduledInterview = $application
                                        ->interviews()
                                        ->where('status', 'scheduled')
                                        ->orderBy('interview_round', 'desc')
                                        ->first();
                                @endphp
                                <tr id="app-row-{{ $application->id }}">
                                    <td>{{ $applications->firstItem() + $index }}</td>
                                    <td>
                                        <div class="fw-semibold" style="font-size:12px;">{{ $candidate->full_name }}
                                        </div>
                                        @if ($candidate->current_company)
                                            <small class="text-muted"
                                                style="font-size:10px;">{{ $candidate->current_company }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div style="font-size:11px;">
                                            <div><i class="feather-mail"></i> {{ $candidate->email }}</div>
                                            <div><i class="feather-phone"></i> {{ $candidate->phone }}</div>
                                        </div>
                                    </td>
                                    <td><span
                                            style="font-size:11px;">{{ $candidate->total_experience ?? 'Fresher' }}</span>
                                    </td>
                                    <td>
                                        <span style="font-size:11px;">
                                            {{ $candidate->current_ctc ? '₹' . number_format($candidate->current_ctc) : '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size:11px;">
                                            {{ $candidate->expected_ctc ? '₹' . number_format($candidate->expected_ctc) : '—' }}
                                        </span>
                                    </td>
                                    <td><span
                                            style="font-size:11px;">{{ $application->applied_date->format('d M Y') }}</span>
                                    </td>
                                    <td>
                                        <span class="stage-badge stage-{{ $stage }}">
                                            {{ ucfirst(str_replace('_', ' ', $stage)) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($candidate->resume_url)
                                            <a href="{{ asset($candidate->resume_url) }}" target="_blank" class="resume-link">
                                                <i class="feather-file-text"></i> View
                                            </a>
                                        @else
                                            <span class="text-muted" style="font-size:10px;">None</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <a href="#" class="action-btn" data-bs-toggle="dropdown"
                                                data-bs-offset="0,5">
                                                <i class="feather-more-vertical"></i>
                                            </a>
                                            <ul class="dropdown-menu dropdown-menu-end">

                                                {{-- ① Received → shortlist or CV reject --}}
                                                @if ($stage === 'application_received')
                                                    <li>
                                                        <button class="dropdown-item text-success"
                                                            onclick="shortlistApplication({{ $application->id }})">
                                                            <i class="feather-check-circle"></i> Shortlist CV
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item text-danger"
                                                            onclick="rejectApplication({{ $application->id }})">
                                                            <i class="feather-x-circle"></i> Reject CV
                                                        </button>
                                                    </li>
                                                @endif

                                                {{-- ② cv_shortlisted → schedule interview OR reject --}}
                                                @if ($stage === 'cv_shortlisted')
                                                    <li>
                                                        <button class="dropdown-item"
                                                            onclick="showScheduleModal({{ $application->id }}, '{{ addslashes($candidate->full_name) }}')">
                                                            <i class="feather-calendar"></i> Schedule Interview
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item text-danger"
                                                            onclick="rejectApplication({{ $application->id }})">
                                                            <i class="feather-x-circle"></i> Reject
                                                        </button>
                                                    </li>
                                                @endif

                                                {{-- ③ interview_scheduled → complete (submit feedback) --}}
                                                @if ($stage === 'interview_scheduled' && $scheduledInterview)
                                                    <li>
                                                        <button class="dropdown-item text-primary"
                                                            onclick="showFeedbackModal(
                                                            {{ $scheduledInterview->id }},
                                                            '{{ addslashes($candidate->full_name) }}',
                                                            '{{ addslashes($scheduledInterview->round_name) }}',
                                                            '{{ $scheduledInterview->scheduled_date->format('d M Y') }}'
                                                        )">
                                                            <i class="feather-check-square"></i> Submit Feedback
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item text-danger"
                                                            onclick="rejectApplication({{ $application->id }})">
                                                            <i class="feather-x-circle"></i> Reject
                                                        </button>
                                                    </li>
                                                @endif

                                                {{-- ④ interview_completed → release offer OR start onboarding --}}
                                                @if ($stage === 'interview_completed')
                                                    <li>
                                                        <button class="dropdown-item text-success"
                                                            onclick="showOfferModal({{ $application->id }}, '{{ addslashes($candidate->full_name) }}')">
                                                            <i class="feather-file-text"></i> Release Offer
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item text-info"
                                                            href="{{ route('employee.create', ['applicationId' => $application->id]) }}">
                                                            <i class="feather-user-plus"></i> Start Onboarding
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item text-danger"
                                                            onclick="rejectApplication({{ $application->id }})">
                                                            <i class="feather-x-circle"></i> Reject
                                                        </button>
                                                    </li>
                                                @endif

                                                {{-- ⑤ offer_released → accept / reject offer --}}
                                                @if ($stage === 'offer_released')
                                                    <li>
                                                        <button class="dropdown-item text-success"
                                                            onclick="updateOfferStatus({{ $application->id }}, 'accepted')">
                                                            <i class="feather-thumbs-up"></i> Mark Offer Accepted
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item text-danger"
                                                            onclick="updateOfferStatus({{ $application->id }}, 'rejected')">
                                                            <i class="feather-thumbs-down"></i> Mark Offer Rejected
                                                        </button>
                                                    </li>
                                                @endif

                                                {{-- ⑥ offer_accepted → start onboarding --}}
                                                @if ($stage === 'offer_accepted')
                                                    <li>
                                                        <a class="dropdown-item text-success"
                                                            href="{{ route('employee.create', ['applicationId' => $application->id]) }}">
                                                            <i class="feather-user-plus"></i> Start Onboarding
                                                        </a>
                                                    </li>
                                                @endif

                                                {{-- Always available: view interview details --}}
                                                <li>
                                                    <hr class="dropdown-divider my-1">
                                                </li>
                                                <li>
                                                    <a class="dropdown-item"
                                                        href="{{ route('recruitment.interview-details', $application->id) }}">
                                                        <i class="feather-eye"></i> Interview Details
                                                    </a>
                                                </li>

                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">
                                        <div class="empty-state">
                                            <i class="feather-inbox"></i>
                                            <h4>No Applications Found</h4>
                                            <p>No applications match the selected filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($applications->hasPages())
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="text-muted" style="font-size:11px;">
                                Showing {{ $applications->firstItem() }} to {{ $applications->lastItem() }}
                                of {{ $applications->total() }} entries
                            </div>
                            {{ $applications->appends(request()->query())->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>
@endsection

@section('create-modal')
    {{-- ── SHORTLIST MODAL ─────────────────────────────── --}}
    <div class="modal fade" id="shortlistModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title">Shortlist Candidate</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="shortlistForm" method="POST">
                    @csrf
                    <div class="modal-body py-3">
                        <p style="font-size:13px;">Are you sure you want to shortlist this candidate?</p>
                        <div class="mb-2">
                            <label class="form-label small">Remarks (Optional)</label>
                            <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="Add remarks…"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">Shortlist</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ── REJECT MODAL ────────────────────────────────── --}}
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title">Reject Candidate</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="rejectForm" method="POST">
                    @csrf
                    <div class="modal-body py-3">
                        <p style="font-size:13px;">Please provide a reason for rejection.</p>
                        <div class="mb-2">
                            <label class="form-label small">Reason <span class="text-danger">*</span></label>
                            <textarea name="remarks" id="rejectRemarks" class="form-control form-control-sm" rows="2"
                                placeholder="Enter rejection reason…" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm" id="rejectSubmitBtn">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ── SCHEDULE INTERVIEW MODAL ────────────────────── --}}
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="feather-calendar me-2"></i>Schedule Interview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="scheduleForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info py-2 mb-3" style="font-size:12px;">
                            <strong>Candidate:</strong> <span id="scheduleCandidateName"></span>
                            &nbsp;|&nbsp;
                            <strong>Position:</strong> {{ $jobOpening->title }}
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="schedule-form-group">
                                    <label class="required">Round #</label>
                                    <input type="number" name="interview_round" id="interview_round"
                                        class="schedule-form-control" value="1" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="schedule-form-group">
                                    <label class="required">Round Name</label>
                                    <input type="text" name="round_name" id="round_name"
                                        class="schedule-form-control" placeholder="e.g., Technical Round 1, HR Round"
                                        required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="schedule-form-group">
                                    <label class="required">Interview Type</label>
                                    <select name="interview_type" id="interview_type" class="schedule-form-control"
                                        required>
                                        <option value="">Select Type</option>
                                        <option value="online">Online (Zoom / Google Meet)</option>
                                        <option value="offline">Offline (In-Person)</option>
                                        <option value="telephonic">Telephonic</option>
                                        <option value="video">Video Call</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="schedule-form-group">
                                    <label class="required">Interviewer</label>
                                    <select name="interviewer_id" id="interviewer_id" class="schedule-form-control"
                                        required>
                                        <option value="">Select Interviewer</option>
                                        @php
                                            $interviewers = \App\Models\User::whereIn('role', [
                                                'hr',
                                                'manager',
                                                'admin',
                                                'super_admin',
                                            ])
                                                ->orderBy('name')
                                                ->get();
                                        @endphp
                                        @foreach ($interviewers as $iv)
                                            <option value="{{ $iv->id }}">{{ $iv->name }}
                                                ({{ ucfirst($iv->role) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="schedule-form-group">
                                    <label class="required">Date</label>
                                    <input type="date" name="scheduled_date" id="scheduled_date"
                                        class="schedule-form-control" min="{{ date('Y-m-d') }}" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="schedule-form-group">
                                    <label class="required">Time</label>
                                    <input type="time" name="scheduled_time" id="scheduled_time"
                                        class="schedule-form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="schedule-form-group">
                                    <label>Duration</label>
                                    <select name="duration_minutes" class="schedule-form-control">
                                        <option value="15">15 min</option>
                                        <option value="30" selected>30 min</option>
                                        <option value="45">45 min</option>
                                        <option value="60">60 min</option>
                                        <option value="90">90 min</option>
                                        <option value="120">120 min</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="schedule-form-group">
                            <label>Meeting Link / Location</label>
                            <input type="text" name="meeting_link" id="meeting_link" class="schedule-form-control"
                                placeholder="Zoom/Meet link or office address">
                        </div>

                        <!--<div class="schedule-form-group">-->
                        <!--    <label>Co-Interviewers</label>-->
                        <!--    <select name="co_interviewer_ids[]" class="schedule-form-control" multiple-->
                        <!--        style="height:auto;">-->
                        <!--        @foreach ($interviewers as $iv)-->
                        <!--            <option value="{{ $iv->id }}">{{ $iv->name }} ({{ ucfirst($iv->role) }})-->
                        <!--            </option>-->
                        <!--        @endforeach-->
                        <!--    </select>-->
                        <!--    <small class="text-muted">Hold Ctrl / Cmd to select multiple</small>-->
                        <!--</div>-->

                        <div class="schedule-form-group">
                            <label>Instructions for Candidate</label>
                            <textarea name="instructions" class="schedule-form-control" rows="2" placeholder="Special instructions…"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="scheduleSubmitBtn">
                            <i class="feather-calendar"></i> Schedule Interview
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ── FEEDBACK MODAL ──────────────────────────────── --}}
    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="feather-message-square me-2"></i>Interview Feedback & Decision</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="feedbackForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info py-2 mb-3" style="font-size:12px;">
                            <strong>Candidate:</strong> <span id="feedbackCandidateName"></span>
                            &nbsp;|&nbsp;
                            <strong>Round:</strong> <span id="feedbackRoundName"></span>
                            &nbsp;|&nbsp;
                            <strong>Date:</strong> <span id="feedbackDate"></span>
                        </div>

                        <div class="row">
                            @foreach ([
            'technical_skill' => 'Technical Skills',
            'communication_skill' => 'Communication',
            'problem_solving' => 'Problem Solving',
            'cultural_fit' => 'Cultural Fit',
            'experience_relevance' => 'Experience Relevance',
            'overall_rating' => 'Overall Rating',
        ] as $field => $label)
                                <div class="col-md-4">
                                    <div class="schedule-form-group">
                                        <label>{{ $label }}</label>
                                        <select name="{{ $field }}" class="schedule-form-control">
                                            <option value="">— Rating —</option>
                                            <option value="1">1 – Poor</option>
                                            <option value="2">2 – Below Avg</option>
                                            <option value="3">3 – Average</option>
                                            <option value="4">4 – Good</option>
                                            <option value="5">5 – Excellent</option>
                                        </select>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="schedule-form-group">
                                    <label>Strengths</label>
                                    <textarea name="strengths" class="schedule-form-control" rows="2" placeholder="Candidate's strengths…"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="schedule-form-group">
                                    <label>Areas for Improvement</label>
                                    <textarea name="weaknesses" class="schedule-form-control" rows="2"
                                        placeholder="Weaknesses / areas to improve…"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="schedule-form-group">
                            <label class="required">Detailed Feedback / Comments</label>
                            <textarea name="comments" class="schedule-form-control" rows="3" id="feedbackComments"
                                placeholder="Provide detailed feedback…" required></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="schedule-form-group">
                                    <label class="required">Recommendation</label>
                                    <select name="recommendation" class="schedule-form-control"
                                        id="feedbackRecommendation" required>
                                        <option value="">— Select —</option>
                                        <option value="strong_hire">Strong Hire</option>
                                        <option value="hire">Hire</option>
                                        <option value="maybe">Maybe / On Hold</option>
                                        <option value="no_hire">No Hire</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="schedule-form-group">
                                    <label class="required">Decision</label>
                                    <select name="decision" class="schedule-form-control" id="feedbackDecision" required>
                                        <option value="">— Select —</option>
                                        <option value="selected">✅ Selected – Move to Offer</option>
                                        <option value="next_round">🔄 Next Round – Schedule Another Interview</option>
                                        <option value="on_hold">⏸ On Hold</option>
                                        <option value="rejected">❌ Rejected</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div id="nextRoundDiv" style="display:none;">
                            <div class="schedule-form-group">
                                <label>Next Round Name (suggested)</label>
                                <input type="text" name="next_round_suggested" class="schedule-form-control"
                                    placeholder="e.g., Managerial Round, HR Round">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="feedbackSubmitBtn">
                            <i class="feather-save"></i> Submit Feedback & Decision
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ── OFFER MODAL ─────────────────────────────────── --}}
    <div class="modal fade" id="offerModal" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="feather-file-text me-2"></i>Release Offer Letter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="offerForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info py-2 mb-3" style="font-size:12px;">
                            <strong>Candidate:</strong> <span id="offerCandidateName"></span>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="schedule-form-group">
                                    <label class="required">Department</label>
                                    <select name="department_id" class="schedule-form-control" required>
                                        <option value="">Select Department</option>
                                        @foreach (\App\Models\Department::orderBy('name')->get() as $dept)
                                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="schedule-form-group">
                                    <label class="required">Designation</label>
                                    <select name="designation_id" class="schedule-form-control" required>
                                        <option value="">Select Designation</option>
                                        @foreach (\App\Models\Designation::orderBy('name')->get() as $desig)
                                            <option value="{{ $desig->id }}">{{ $desig->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="schedule-form-group">
                                    <label class="required">Employment Type</label>
                                    <select name="employment_type" class="schedule-form-control" required>
                                        <option value="">Select Type</option>
                                        <option value="full_time">Full Time</option>
                                        <option value="part_time">Part Time</option>
                                        <option value="contract">Contract</option>
                                        <option value="internship">Internship</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="schedule-form-group">
                                    <label class="required">Joining Date</label>
                                    <input type="date" name="joining_date" class="schedule-form-control"
                                        min="{{ date('Y-m-d') }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="schedule-form-group">
                                    <label class="required">Offered CTC (₹/year)</label>
                                    <input type="number" name="offered_ctc" class="schedule-form-control"
                                        placeholder="e.g., 600000" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="schedule-form-group">
                                    <label>Basic Salary (₹/month)</label>
                                    <input type="number" name="basic_salary" class="schedule-form-control"
                                        placeholder="e.g., 30000" min="0">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="schedule-form-group">
                                    <label>HRA (₹/month)</label>
                                    <input type="number" name="hra" class="schedule-form-control" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="schedule-form-group">
                                    <label>Other Allowances (₹/month)</label>
                                    <input type="number" name="other_allowances" class="schedule-form-control"
                                        min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="schedule-form-group">
                                    <label>Variable Pay (₹/year)</label>
                                    <input type="number" name="variable_pay" class="schedule-form-control"
                                        min="0">
                                </div>
                            </div>
                        </div>

                        <div class="schedule-form-group">
                            <label>Reporting Head</label>
                            <select name="reporting_head" class="schedule-form-control">
                                <option value="">Select Reporting Head</option>
                                @foreach ($interviewers as $iv)
                                    <option value="{{ $iv->id }}">{{ $iv->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm" id="offerSubmitBtn">
                            <i class="feather-send"></i> Release Offer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        // ── DOM Ready ──────────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            const scheduledDateInput = document.getElementById('scheduled_date');
            if (scheduledDateInput) scheduledDateInput.min = new Date().toISOString().split('T')[0];

            document.getElementById('feedbackDecision')?.addEventListener('change', function() {
                document.getElementById('nextRoundDiv').style.display =
                    this.value === 'next_round' ? 'block' : 'none';
            });
        });

        // ── Modal openers ──────────────────────────────────────────────────────────
        // ── Modal openers ──────────────────────────────────────────────────────────
        function shortlistApplication(id) {
            const form = document.getElementById('shortlistForm');
            form.action = `/recruitment/applications/${id}/shortlist`;
            const remarksField = form.querySelector('textarea[name="remarks"]');
            if (remarksField) remarksField.value = '';
            new bootstrap.Modal(document.getElementById('shortlistModal')).show();
        }

        function rejectApplication(id) {
            const form = document.getElementById('rejectForm');
            form.action = `/recruitment/applications/${id}/reject`;
            const remarksField = form.querySelector('textarea[name="remarks"]');
            if (remarksField) remarksField.value = '';
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        function showScheduleModal(id, candidateName) {
            const form = document.getElementById('scheduleForm');
            form.reset();
            form.action = `/recruitment/${id}/schedule-interview`;
            document.getElementById('scheduleCandidateName').textContent = candidateName;

            // Get next round number
            fetch(`/recruitment/${id}/interviews-count`)
                .then(res => res.json())
                .then(data => {
                    const nextRound = (data.count || 0) + 1;
                    document.getElementById('interview_round').value = nextRound;
                    document.getElementById('round_name').value = nextRound === 1 ? 'First Round' :
                        `Round ${nextRound}`;
                })
                .catch(() => {
                    document.getElementById('interview_round').value = 1;
                    document.getElementById('round_name').value = 'First Round';
                });

            new bootstrap.Modal(document.getElementById('scheduleModal')).show();
        }

        function showFeedbackModal(interviewId, candidateName, roundName, date) {
            const form = document.getElementById('feedbackForm');
            form.reset();
            form.action = `/recruitment/interviews/${interviewId}/feedback`;
            document.getElementById('feedbackCandidateName').textContent = candidateName;
            document.getElementById('feedbackRoundName').textContent = roundName;
            document.getElementById('feedbackDate').textContent = date;
            document.getElementById('nextRoundDiv').style.display = 'none';
            new bootstrap.Modal(document.getElementById('feedbackModal')).show();
        }

        function showOfferModal(applicationId, candidateName) {
            const form = document.getElementById('offerForm');
            form.reset();
            form.action = `/recruitment/applications/${applicationId}/release-offer`;
            document.getElementById('offerCandidateName').textContent = candidateName;
            new bootstrap.Modal(document.getElementById('offerModal')).show();
        }

        function updateOfferStatus(applicationId, status) {
            const label = status === 'accepted' ? 'accept' : 'reject';
            if (!confirm(`Are you sure you want to mark this offer as ${label}ed?`)) return;

            postJson(`/recruitment/applications/${applicationId}/offer-${status}`, {})
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else showToast(data.message || 'Failed', 'error');
                });
        }

        function bindAjaxForm(formId, modalId, submitBtnId, defaultBtnText) {
            const form = document.getElementById(formId);
            if (!form) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                console.log('remarks value:', formData.get('remarks')); // Debug

                const btn = document.getElementById(submitBtnId);
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing…';

                postJson(form.action, formData) // Pass FormData directly
                    .then(data => {
                        if (data.success) {
                            bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
                            showToast(data.message, 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            const msg = data.errors ?
                                Object.values(data.errors).flat().join('<br>') :
                                (data.message || 'An error occurred.');
                            showToast(msg, 'error');
                        }
                    })
                    .catch(() => showToast('Network error. Please try again.', 'error'))
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = defaultBtnText;
                    });
            });
        }

        function postJson(url, data) {
            let body;
            let contentType;

            if (data instanceof FormData) {
                // For FormData, don't set Content-Type header
                body = data;
                contentType = undefined;
            } else {
                // For regular objects/strings
                body = typeof data === 'string' ? data : new URLSearchParams(data);
                contentType = 'application/x-www-form-urlencoded';
            }

            const headers = {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            };

            if (contentType) {
                headers['Content-Type'] = contentType;
            }

            return fetch(url, {
                method: 'POST',
                headers: headers,
                body: body,
            }).then(r => r.json());
        }

        // Bind all forms
        bindAjaxForm('shortlistForm', 'shortlistModal', 'shortlistForm', 'Shortlist');
        bindAjaxForm('rejectForm', 'rejectModal', 'rejectForm', 'Reject');
        bindAjaxForm('scheduleForm', 'scheduleModal', 'scheduleSubmitBtn',
            '<i class="feather-calendar"></i> Schedule Interview');
        bindAjaxForm('feedbackForm', 'feedbackModal', 'feedbackSubmitBtn',
            '<i class="feather-save"></i> Submit Feedback & Decision');
        bindAjaxForm('offerForm', 'offerModal', 'offerSubmitBtn',
            '<i class="feather-send"></i> Release Offer');

        // Override submit-btn id for shortlist/reject (they use form id as btn id above — fix)
        document.getElementById('shortlistForm')?.addEventListener('submit', function() {}, true);

        // ── Toast ──────────────────────────────────────────────────────────────────
        function showToast(message, type = 'success') {
            document.querySelector('.toast-container')?.remove();
            const wrap = document.createElement('div');
            wrap.className = 'toast-container position-fixed top-0 end-0 p-3';
            wrap.style.zIndex = '9999';
            wrap.innerHTML = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 show"
             role="alert" aria-live="assertive">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    onclick="this.closest('.toast-container').remove()"></button>
            </div>
        </div>`;
            document.body.appendChild(wrap);
            setTimeout(() => wrap.remove(), 4000);
        }
    </script>
@endsection
