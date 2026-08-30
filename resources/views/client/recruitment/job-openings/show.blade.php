@extends('client.layout.master')

@section('style')
    <style>
        /* Alert Styles */
        .alert {
            padding: 8px 12px;
            margin-bottom: 15px;
            border: 1px solid transparent;
            border-radius: 6px;
            font-size: 12px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeaa7;
        }

        /* Detail Cards */
        .detail-card {
            background: #fff;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 12px;
            border: 1px solid #eef2f6;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .detail-card-title {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #eef2f6;
            color: #1e293b;
        }

        .detail-card-title i {
            margin-right: 6px;
            font-size: 13px;
            color: #4f46e5;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 6px 0;
        }

        .info-label {
            min-width: 100px;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .info-value {
            flex: 1;
            font-size: 12px;
            color: #1e293b;
            word-break: break-word;
        }

        /* Two Column Layout */
        .two-column-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* Status Badges */
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
        }

        .status-published {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-draft {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-closed {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-on-hold {
            background-color: #f3e8ff;
            color: #5b21b6;
        }

        /* Employment Type Badges */
        .employment-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 500;
            display: inline-block;
        }

        .employment-full_time {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .employment-part_time {
            background-color: #f1f5f9;
            color: #475569;
        }

        .employment-contract {
            background-color: #fef3c7;
            color: #92400e;
        }

        .employment-internship {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .employment-temporary {
            background-color: #e5e7eb;
            color: #4b5563;
        }

        /* Skill Tags */
        .skill-tag {
            display: inline-block;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            color: #475569;
            margin: 2px 4px 2px 0;
        }

        /* Statistics Cards */
        .stat-mini-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 8px 12px;
            text-align: center;
            border: 1px solid #eef2f6;
        }

        .stat-mini-number {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }

        .stat-mini-number.text-success {
            color: #10b981;
        }

        .stat-mini-number.text-info {
            color: #3b82f6;
        }

        .stat-mini-number.text-primary {
            color: #4f46e5;
        }

        .stat-mini-label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        /* Progress Bar */
        .progress-custom {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin: 8px 0;
        }

        .progress-bar-custom {
            height: 100%;
            background: #4f46e5;
            border-radius: 3px;
            transition: width 0.3s;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-sm-custom {
            padding: 4px 12px;
            font-size: 11px;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
        }

        .btn-info {
            background: #0ea5e9;
            color: white;
        }

        .btn-info:hover {
            background: #0284c7;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-secondary {
            background: #64748b;
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
        }

        .btn-outline-secondary {
            background: transparent;
            color: #64748b;
            border: 1px solid #cbd5e1;
        }

        .btn-outline-secondary:hover {
            background: #f8fafc;
        }

        /* Job Code Display */
        .job-code {
            background: #f1f5f9;
            padding: 4px 10px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            font-weight: 600;
            color: #4f46e5;
            display: inline-block;
        }

        /* Description Text */
        .description-text {
            font-size: 12px;
            line-height: 1.5;
            color: #334155;
            white-space: pre-wrap;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .two-column-layout {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .info-item {
                flex-direction: column;
                gap: 2px;
            }

            .info-label {
                min-width: auto;
            }

            .detail-card {
                padding: 10px 12px;
            }

            .action-buttons {
                justify-content: center;
            }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header" style="margin-bottom: 10px;">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-5" style="font-size: 16px;">Job Opening Details</h5>
            </div>
            <ul class="breadcrumb" style="margin-left: 12px;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('job-openings.index') }}">Job Openings</a></li>
                <li class="breadcrumb-item active">{{ $jobOpening->title }}</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 10px 15px !important;">
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="feather-check-circle" style="margin-right: 6px;"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size: 8px;"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="feather-alert-circle" style="margin-right: 6px;"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size: 8px;"></button>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="feather-alert-triangle" style="margin-right: 6px;"></i>
                {{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size: 8px;"></button>
            </div>
        @endif

        <!-- Action Buttons -->
        <div class="detail-card" style="padding: 10px 16px;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="action-buttons">
                    <a href="{{ route('job-openings.edit', $jobOpening->id) }}" class="btn-sm-custom btn-primary">
                        <i class="feather-edit"></i> Edit
                    </a>
                    <a href="{{ route('job-openings.applications', $jobOpening->id) }}" class="btn-sm-custom btn-info">
                        <i class="feather-users"></i> Applications ({{ $applicationStats['total_applications'] ?? 0 }})
                    </a>
                    @if ($jobOpening->status == 'draft')
                        <button type="button" class="btn-sm-custom btn-success" onclick="publishJob()">
                            <i class="feather-paper-plane"></i> Publish
                        </button>
                    @endif
                    @if ($jobOpening->status == 'published')
                        <button type="button" class="btn-sm-custom btn-warning" onclick="closeJob()">
                            <i class="feather-x-circle"></i> Close
                        </button>
                    @endif
                    <button type="button" class="btn-sm-custom btn-secondary" onclick="duplicateJob()">
                        <i class="feather-copy"></i> Duplicate
                    </button>
                </div>
                <a href="{{ route('job-openings.index') }}" class="btn-sm-custom btn-outline-secondary">
                    <i class="feather-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <!-- Statistics Row -->
        <div class="two-column-layout">
            <!-- Stats Cards -->
            <div class="detail-card">
                <div class="detail-card-title">
                    <i class="feather-bar-chart-2"></i> Hiring Progress
                </div>
                <div class="info-grid">
                    <div class="stat-mini-card">
                        <div class="stat-mini-number">{{ $applicationStats['total_applications'] ?? 0 }}</div>
                        <div class="stat-mini-label">Total Applications</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-number text-success">{{ $applicationStats['shortlisted'] ?? 0 }}</div>
                        <div class="stat-mini-label">Shortlisted</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-number text-info">{{ $applicationStats['interview_scheduled'] ?? 0 }}</div>
                        <div class="stat-mini-label">Interviewing</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-number text-primary">{{ $applicationStats['onboarded'] ?? 0 }}</div>
                        <div class="stat-mini-label">Onboarded</div>
                    </div>
                </div>

                <!-- Fill Rate Progress -->
                @php
                    $fillRate = isset($jobOpening->no_of_vacancies) && $jobOpening->no_of_vacancies > 0
                        ? round((($applicationStats['onboarded'] ?? 0) / $jobOpening->no_of_vacancies) * 100, 2)
                        : 0;
                @endphp
                <div style="margin-top: 8px;">
                    <div style="display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 4px;">
                        <span>Fill Rate</span>
                        <span>{{ $fillRate }}% ({{ $applicationStats['onboarded'] ?? 0 }}/{{ $jobOpening->no_of_vacancies }})</span>
                    </div>
                    <div class="progress-custom">
                        <div class="progress-bar-custom" style="width: {{ $fillRate }}%"></div>
                    </div>
                </div>
            </div>

            <!-- Job Overview -->
            <div class="detail-card">
                <div class="detail-card-title">
                    <i class="feather-briefcase"></i> Job Overview
                </div>
                <div class="info-item">
                    <div class="info-label">Job Code</div>
                    <div class="info-value"><span class="job-code">{{ $jobOpening->job_code }}</span></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        @if ($jobOpening->status == 'published')
                            <span class="status-badge status-published">Published</span>
                        @elseif($jobOpening->status == 'draft')
                            <span class="status-badge status-draft">Draft</span>
                        @elseif($jobOpening->status == 'closed')
                            <span class="status-badge status-closed">Closed</span>
                        @elseif($jobOpening->status == 'on_hold')
                            <span class="status-badge status-on-hold">On Hold</span>
                        @else
                            <span class="status-badge">{{ ucfirst($jobOpening->status) }}</span>
                        @endif
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Employment Type</div>
                    <div class="info-value">
                        <span class="employment-badge employment-{{ $jobOpening->employment_type }}">
                            {{ ucfirst(str_replace('_', ' ', $jobOpening->employment_type)) }}
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Location</div>
                    <div class="info-value">{{ $jobOpening->location ?? 'Not specified' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Vacancies</div>
                    <div class="info-value">{{ $jobOpening->no_of_vacancies }}</div>
                </div>
            </div>
        </div>

        <!-- Basic Information -->
        <div class="detail-card">
            <div class="detail-card-title">
                <i class="feather-info"></i> Basic Information
            </div>
            <div class="two-column-layout">
                <div class="info-item">
                    <div class="info-label">Job Title</div>
                    <div class="info-value"><strong>{{ $jobOpening->title }}</strong></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Department</div>
                    <div class="info-value">{{ $jobOpening->department->name ?? 'N/A' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Designation</div>
                    <div class="info-value">{{ $jobOpening->designation->name ?? 'N/A' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Hiring Lead</div>
                    <div class="info-value">{{ $jobOpening->hiringLead->name ?? 'N/A' }}</div>
                </div>
                @if ($jobOpening->published_date)
                    <div class="info-item">
                        <div class="info-label">Published Date</div>
                        <div class="info-value">{{ \Carbon\Carbon::parse($jobOpening->published_date)->format('d M Y, h:i A') }}</div>
                    </div>
                @endif
                @if ($jobOpening->closed_date)
                    <div class="info-item">
                        <div class="info-label">Closed Date</div>
                        <div class="info-value">{{ \Carbon\Carbon::parse($jobOpening->closed_date)->format('d M Y, h:i A') }}</div>
                    </div>
                @endif
                <div class="info-item">
                    <div class="info-label">Created</div>
                    <div class="info-value">{{ $jobOpening->created_at->format('d M Y, h:i A') }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Last Updated</div>
                    <div class="info-value">{{ $jobOpening->updated_at->format('d M Y, h:i A') }}</div>
                </div>
            </div>
        </div>

        <!-- Job Description -->
        <div class="detail-card">
            <div class="detail-card-title">
                <i class="feather-file-text"></i> Job Description
            </div>
            <div class="description-text">
                {!! nl2br(e($jobOpening->description)) !!}
            </div>
        </div>

        <!-- Requirements & Skills -->
        <div class="two-column-layout">
            <!-- Requirements -->
            <div class="detail-card">
                <div class="detail-card-title">
                    <i class="feather-award"></i> Requirements
                </div>
                @if ($jobOpening->experience_required)
                    <div class="info-item">
                        <div class="info-label">Experience</div>
                        <div class="info-value">{{ $jobOpening->experience_required }}</div>
                    </div>
                @endif
                @if ($jobOpening->qualification_required)
                    <div class="info-item">
                        <div class="info-label">Qualification</div>
                        <div class="info-value">{{ $jobOpening->qualification_required }}</div>
                    </div>
                @endif
                @if ($jobOpening->requirements)
                    <div class="info-item">
                        <div class="info-label">Additional</div>
                        <div class="info-value description-text">{{ $jobOpening->requirements }}</div>
                    </div>
                @endif
                @if (!$jobOpening->experience_required && !$jobOpening->qualification_required && !$jobOpening->requirements)
                    <div class="text-muted" style="font-size: 11px; text-align: center;">No requirements specified</div>
                @endif
            </div>

            <!-- Skills -->
            <div class="detail-card">
                <div class="detail-card-title">
                    <i class="feather-cpu"></i> Skills Required
                </div>
                @if ($jobOpening->skills_required)
                    @php
                        $skills = explode(',', $jobOpening->skills_required);
                    @endphp
                    <div>
                        @foreach ($skills as $skill)
                            <span class="skill-tag">{{ trim($skill) }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="text-muted" style="font-size: 11px; text-align: center;">No skills specified</div>
                @endif
            </div>
        </div>

        <!-- Responsibilities -->
        @if ($jobOpening->responsibilities)
            <div class="detail-card">
                <div class="detail-card-title">
                    <i class="feather-list"></i> Key Responsibilities
                </div>
                <div class="description-text">
                    {!! nl2br(e($jobOpening->responsibilities)) !!}
                </div>
            </div>
        @endif

        <!-- Compensation -->
        @if ($jobOpening->salary_range_min || $jobOpening->salary_range_max)
            <div class="detail-card">
                <div class="detail-card-title">
                    <i class="feather-dollar-sign"></i> Compensation
                </div>
                <div class="info-item">
                    <div class="info-label">Salary Range</div>
                    <div class="info-value">
                        @if ($jobOpening->salary_range_min && $jobOpening->salary_range_max)
                            ₹{{ number_format($jobOpening->salary_range_min) }} - ₹{{ number_format($jobOpening->salary_range_max) }}
                        @elseif($jobOpening->salary_range_min)
                            From ₹{{ number_format($jobOpening->salary_range_min) }}
                        @elseif($jobOpening->salary_range_max)
                            Up to ₹{{ number_format($jobOpening->salary_range_max) }}
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Publish Modal -->
    <div class="modal fade" id="publishModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title">Publish Job Opening</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    <p>Are you sure you want to publish <strong>{{ $jobOpening->title }}</strong>?</p>
                    <small class="text-muted">Once published, it will be visible on the career page.</small>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <form id="publishForm" method="POST" action="{{ route('job-openings.publish', $jobOpening->id) }}"
                        style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">Publish</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Close Modal -->
    <div class="modal fade" id="closeModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title">Close Job Opening</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="closeForm" method="POST" action="{{ route('job-openings.close', $jobOpening->id) }}">
                    @csrf
                    <div class="modal-body py-3">
                        <p>Are you sure you want to close <strong>{{ $jobOpening->title }}</strong>?</p>
                        <div class="mb-2">
                            <label class="form-label small">Reason (Optional)</label>
                            <textarea name="reason" class="form-control form-control-sm" rows="2"
                                placeholder="Enter reason for closing..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning btn-sm">Close Job</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Duplicate Form -->
    <form id="duplicateForm" method="POST" action="{{ route('job-openings.duplicate', $jobOpening->id) }}" style="display: none;">
        @csrf
    </form>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Auto-hide alerts after 4 seconds
            setTimeout(function() {
                $('.alert').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 4000);
        });

        function publishJob() {
            new bootstrap.Modal(document.getElementById('publishModal')).show();
        }

        function closeJob() {
            new bootstrap.Modal(document.getElementById('closeModal')).show();
        }

        function duplicateJob() {
            if (confirm('Are you sure you want to duplicate this job opening?')) {
                document.getElementById('duplicateForm').submit();
            }
        }
    </script>
@endsection