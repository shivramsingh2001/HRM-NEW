{{-- resources/views/client/recruitment/job-openings/interview-details.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        .profile-header {
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
        }

        .profile-avatar {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, .2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
        }

        .info-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #eef2f6;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
        }

        .info-title {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eef2f6;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-row {
            display: flex;
            margin-bottom: 8px;
            font-size: 12px;
        }

        .info-label {
            width: 130px;
            font-weight: 600;
            color: #64748b;
            flex-shrink: 0;
        }

        .info-value {
            flex: 1;
            color: #1e293b;
        }

        /* ── Timeline ── */
        .timeline-wrap {
            padding-left: 10px;
        }

        .timeline-item {
            position: relative;
            padding-left: 28px;
            padding-bottom: 24px;
            border-left: 2px solid #e2e8f0;
            margin-left: 8px;
        }

        .timeline-item:last-child {
            border-left-color: transparent;
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -9px;
            top: 2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #0D6EFD;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #0D6EFD;
        }

        .timeline-dot.completed {
            background: #10b981;
            box-shadow: 0 0 0 2px #10b981;
        }

        .timeline-dot.scheduled {
            background: #f59e0b;
            box-shadow: 0 0 0 2px #f59e0b;
        }

        .timeline-dot.cancelled {
            background: #ef4444;
            box-shadow: 0 0 0 2px #ef4444;
        }

        .timeline-content {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
        }

        .timeline-content.completed {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .timeline-content.cancelled {
            background: #fff1f2;
            border-color: #fecdd3;
        }

        /* ── Feedback block ── */
        .feedback-block {
            background: #fefce8;
            border-left: 3px solid #eab308;
            border-radius: 6px;
            padding: 10px 12px;
            margin-top: 10px;
        }

        .rating-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 8px;
        }

        .rating-item {
            font-size: 11px;
        }

        .rating-item label {
            font-weight: 600;
            color: #64748b;
            display: block;
            margin-bottom: 2px;
        }

        .stars {
            display: inline-flex;
            gap: 2px;
        }

        .stars i {
            font-size: 11px;
        }

        /* ── Status Badges ── */
        .sbadge {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
            margin-left: 4px;
        }

        .sb-scheduled {
            background: #fef3c7;
            color: #92400e;
        }

        .sb-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .sb-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .sb-selected {
            background: #d1fae5;
            color: #065f46;
        }

        .sb-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .sb-next_round {
            background: #dbeafe;
            color: #0D6EFD;
        }

        .sb-on_hold {
            background: #fef3c7;
            color: #92400e;
        }

        /* ── Stage badge (matches applications page) ── */
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
            color: #0D6EFD;
        }

        .stage-offer_released {
            background: #dbeafe;
            color: #0D6EFD;
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
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Interview Details" :crumbs="[['label' => 'Job Openings', 'url' => route('job-openings.index')], ['label' => 'Applications', 'url' => route('job-openings.applications', $jobOpening->id)]]" :back="route('job-openings.applications', $jobOpening->id)" />

    <div class="main-content" style="padding:10px 20px !important;">

        {{-- Profile Header --}}
        <div class="profile-header">
            <div class="row align-items-center g-3">
                <div class="col-auto">
                    <div class="profile-avatar">
                        {{ strtoupper(substr($candidate->full_name, 0, 2)) }}
                    </div>
                </div>
                <div class="col">
                    <h4 class="mb-1" style="font-size:18px;">{{ $candidate->full_name }}</h4>
                    <p class="mb-1 opacity-75" style="font-size:12px;">
                        Applying for: <strong>{{ $jobOpening->title }}</strong>
                        <span class="badge bg-light text-dark ms-1"
                            style="font-size:10px;">{{ $jobOpening->job_code }}</span>
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <span class="badge bg-light text-dark" style="font-size:10px;">
                            <i class="feather-mail"></i> {{ $candidate->email }}
                        </span>
                        <span class="badge bg-light text-dark" style="font-size:10px;">
                            <i class="feather-phone"></i> {{ $candidate->phone }}
                        </span>
                    </div>
                </div>
                <div class="col-auto text-end">
                    <span class="stage-badge stage-{{ $application->current_stage }}">
                        {{ ucfirst(str_replace('_', ' ', $application->current_stage)) }}
                    </span>
                    <br>
                    <small class="opacity-75" style="font-size:10px;">
                        Applied: {{ $application->applied_date->format('d M Y') }}
                    </small>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- Left: Candidate Details --}}
            <div class="col-md-4">

                <div class="info-card">
                    <div class="info-title"><i class="feather-user"></i> Personal Information</div>
                    @foreach ([
            'Full Name' => $candidate->full_name,
            'Email' => $candidate->email,
            'Phone' => $candidate->phone,
            'Alternate Phone' => $candidate->alternate_phone ?? null,
            'Date of Birth' => optional($candidate->date_of_birth)->format('d M Y'),
            'Gender' => $candidate->gender ? ucfirst($candidate->gender) : null,
            'Current Location' => $candidate->current_location ?? null,
        ] as $label => $value)
                        @if ($value)
                            <div class="info-row">
                                <div class="info-label">{{ $label }}</div>
                                <div class="info-value">{{ $value }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="info-card">
                    <div class="info-title"><i class="feather-briefcase"></i> Professional Info</div>
                    @foreach ([
            'Experience' => $candidate->total_experience ?? 'Fresher',
            'Current Company' => $candidate->current_company ?? null,
            'Current CTC' => $candidate->current_ctc ? '₹' . number_format($candidate->current_ctc) : 'N/A',
            'Expected CTC' => $candidate->expected_ctc ? '₹' . number_format($candidate->expected_ctc) : 'N/A',
            'Notice Period' => $candidate->notice_period ?? null,
            'Qualification' => $candidate->qualification ?? null,
        ] as $label => $value)
                        @if ($value)
                            <div class="info-row">
                                <div class="info-label">{{ $label }}</div>
                                <div class="info-value">{{ $value }}</div>
                            </div>
                        @endif
                    @endforeach

                    @if ($candidate->skills)
                        <div class="info-row">
                            <div class="info-label">Skills</div>
                            <div class="info-value">
                                @foreach (explode(',', $candidate->skills) as $skill)
                                    <span class="badge bg-light text-dark me-1 mb-1" style="font-size:10px;">
                                        {{ trim($skill) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                @if ($candidate->resume_url)
                    <div class="info-card">
                        <div class="info-title"><i class="feather-file-text"></i> Resume</div>
                        <a href="{{ file_url($candidate->resume_url, 'candidate_resume') }}" target="_blank"
                            class="btn btn-outline-primary btn-sm w-100">
                            <i class="feather-download"></i> Download / View Resume
                        </a>
                    </div>
                @endif

                {{-- Quick Stats --}}
                <div class="info-card">
                    <div class="info-title"><i class="feather-bar-chart-2"></i> Interview Summary</div>
                    @php
                        $totalRounds = $interviews->count();
                        $completedRounds = $interviews->where('status', 'completed')->count();
                        $scheduledRounds = $interviews->where('status', 'scheduled')->count();
                    @endphp
                    <div class="d-flex justify-content-around text-center mt-1">
                        <div>
                            <div style="font-size:22px;font-weight:700;color:#0D6EFD;">{{ $totalRounds }}</div>
                            <div style="font-size:10px;color:#64748b;text-transform:uppercase;">Total</div>
                        </div>
                        <div>
                            <div style="font-size:22px;font-weight:700;color:#10b981;">{{ $completedRounds }}</div>
                            <div style="font-size:10px;color:#64748b;text-transform:uppercase;">Completed</div>
                        </div>
                        <div>
                            <div style="font-size:22px;font-weight:700;color:#f59e0b;">{{ $scheduledRounds }}</div>
                            <div style="font-size:10px;color:#64748b;text-transform:uppercase;">Scheduled</div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right: Interview Timeline --}}
            <div class="col-md-8">
                <div class="info-card">
                    <div class="info-title"><i class="feather-calendar"></i> Interview Timeline</div>

                    @if ($interviews->isEmpty())
                        <div class="text-center py-4">
                            <i class="feather-calendar" style="font-size:48px;color:#cbd5e1;"></i>
                            <p class="mt-2 text-muted" style="font-size:13px;">No interviews scheduled yet.</p>
                            {{-- Only show button if stage is schedulable --}}
                            @if (in_array($application->current_stage, ['cv_shortlisted', 'interview_completed']))
                                <button class="btn btn-primary btn-sm mt-1"
                                    onclick="showScheduleModal({{ $application->id }}, '{{ addslashes($candidate->full_name) }}')">
                                    <i class="feather-calendar"></i> Schedule First Interview
                                </button>
                            @endif
                        </div>
                    @else
                        <div class="timeline-wrap">
                            @foreach ($interviews as $interview)
                                @php $feedback = $interview->feedbacks->first(); @endphp

                                <div class="timeline-item">
                                    <div class="timeline-dot {{ $interview->status }}"></div>

                                    <div class="timeline-content {{ $interview->status }}">
                                        {{-- Header --}}
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1 mb-2">
                                            <div>
                                                <strong style="font-size:13px;color:#1e293b;">
                                                    Round {{ $interview->interview_round }}: {{ $interview->round_name }}
                                                </strong>
                                                <span class="sbadge sb-{{ $interview->status }}">
                                                    {{ ucfirst($interview->status) }}
                                                </span>
                                                @if ($interview->outcome)
                                                    <span class="sbadge sb-{{ $interview->outcome }}">
                                                        {{ ucfirst(str_replace('_', ' ', $interview->outcome)) }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-muted" style="font-size:11px;">
                                                <i class="feather-calendar"></i>
                                                {{ $interview->scheduled_date->format('d M Y') }}
                                                at {{ $interview->scheduled_time->format('h:i A') }}
                                                ({{ $interview->duration_minutes }} min)
                                            </div>
                                        </div>

                                        {{-- Details grid --}}
                                        <div class="row g-2 mb-2" style="font-size:12px;">
                                            <div class="col-sm-6">
                                                <span class="text-muted">Interviewer:</span>
                                                <strong>{{ $interview->interviewer->name ?? '—' }}</strong>
                                            </div>
                                            <div class="col-sm-6">
                                                <span class="text-muted">Type:</span>
                                                <strong>{{ ucfirst($interview->interview_type) }}</strong>
                                            </div>
                                            @if ($interview->coInterviewers->isNotEmpty())
                                                <div class="col-12">
                                                    <span class="text-muted">Co-Interviewers:</span>
                                                    @foreach ($interview->coInterviewers as $co)
                                                        <span
                                                            class="badge bg-light text-dark me-1">{{ $co->name }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if ($interview->meeting_link)
                                                <div class="col-12">
                                                    <span class="text-muted">Link / Location:</span>
                                                    <a href="{{ $interview->meeting_link }}" target="_blank"
                                                        style="font-size:11px;">
                                                        {{ $interview->meeting_link }}
                                                    </a>
                                                </div>
                                            @endif
                                            @if ($interview->instructions)
                                                <div class="col-12">
                                                    <span class="text-muted">Instructions:</span>
                                                    <span style="font-size:11px;">{{ $interview->instructions }}</span>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Feedback block --}}
                                        @if ($feedback)
                                            <div class="feedback-block">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <strong style="font-size:12px;">
                                                        <i class="feather-message-circle"></i> Feedback
                                                    </strong>
                                                    <small class="text-muted">
                                                        By: {{ $feedback->interviewer->name ?? 'N/A' }}
                                                    </small>
                                                </div>

                                                {{-- Ratings --}}
                                                <div class="rating-row">
                                                    @foreach ([
            'Technical' => $feedback->technical_skill,
            'Communication' => $feedback->communication_skill,
            'Problem Solving' => $feedback->problem_solving,
            'Cultural Fit' => $feedback->cultural_fit,
            'Experience' => $feedback->experience_relevance,
            'Overall' => $feedback->overall_rating,
        ] as $rLabel => $rVal)
                                                        @if ($rVal)
                                                            <div class="rating-item">
                                                                <label>{{ $rLabel }}</label>
                                                                <div class="stars">
                                                                    @for ($i = 1; $i <= 5; $i++)
                                                                        <i
                                                                            class="feather-star {{ $i <= $rVal ? 'text-warning' : 'text-muted' }}"></i>
                                                                    @endfor
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>

                                                @if ($feedback->strengths)
                                                    <div class="mb-1" style="font-size:11px;">
                                                        <strong>Strengths:</strong> {{ $feedback->strengths }}
                                                    </div>
                                                @endif
                                                @if ($feedback->weaknesses)
                                                    <div class="mb-1" style="font-size:11px;">
                                                        <strong>Areas to improve:</strong> {{ $feedback->weaknesses }}
                                                    </div>
                                                @endif
                                                <div class="mb-1" style="font-size:11px;">
                                                    <strong>Comments:</strong> {{ $feedback->comments }}
                                                </div>
                                                <div style="font-size:11px;">
                                                    <strong>Recommendation:</strong>
                                                    <span
                                                        class="sbadge sb-{{ str_contains($feedback->recommendation, 'hire') ? 'selected' : 'on_hold' }}"
                                                        style="font-size:10px;">
                                                        {{ ucfirst(str_replace('_', ' ', $feedback->recommendation)) }}
                                                    </span>
                                                    @if ($feedback->next_round_suggested)
                                                        &nbsp;· <span class="text-muted">Next:
                                                            {{ $feedback->next_round_suggested }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @elseif($interview->status === 'scheduled')
                                            <div class="text-muted" style="font-size:11px;margin-top:8px;">
                                                <i class="feather-clock"></i> Feedback pending after interview.
                                            </div>
                                        @endif

                                    </div>{{-- .timeline-content --}}
                                </div>{{-- .timeline-item --}}
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Application Activity Log --}}
                @if ($application->logs->isNotEmpty())
                    <div class="info-card">
                        <div class="info-title"><i class="feather-clock"></i> Activity Log</div>
                        <div class="timeline-wrap">
                            @foreach ($application->logs as $log)
                                <div class="timeline-item">
                                    <div class="timeline-dot" style="background:#94a3b8;box-shadow:0 0 0 2px #94a3b8;">
                                    </div>
                                    <div class="timeline-content" style="background:#fff;">
                                        <div class="d-flex justify-content-between flex-wrap gap-1">
                                            <strong style="font-size:12px;">
                                                {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                            </strong>
                                            <small class="text-muted">
                                                {{ $log->created_at->format('d M Y, h:i A') }}
                                            </small>
                                        </div>
                                        <div class="mt-1" style="font-size:11px;">
                                            @if ($log->from_stage)
                                                <span class="badge bg-secondary" style="font-size:9px;">
                                                    {{ ucfirst(str_replace('_', ' ', $log->from_stage)) }}
                                                </span>
                                                <i class="feather-arrow-right" style="font-size:10px;"></i>
                                            @endif
                                            <span class="badge bg-primary" style="font-size:9px;">
                                                {{ ucfirst(str_replace('_', ' ', $log->to_stage)) }}
                                            </span>
                                        </div>
                                        @if ($log->remarks)
                                            <div class="mt-1 text-muted" style="font-size:11px;">{{ $log->remarks }}
                                            </div>
                                        @endif
                                        <div class="mt-1 text-muted" style="font-size:10px;">
                                            By: {{ $log->actionBy->name ?? 'System' }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        {{-- Reuse schedule modal if it gets embedded (e.g., page includes schedule modal) --}}

        function showScheduleModal(id, candidateName) {
            // Redirect back to applications list where modal lives
            window.location.href = "{{ route('job-openings.applications', $jobOpening->id) }}";
        }
    </script>
@endsection
