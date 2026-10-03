{{-- resources/views/client/recruitment/job-openings/index.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>




        .clear-all-link {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 12px;
            text-decoration: none;
            padding: 4px 10px;
            border-radius: 20px;
            transition: all 0.2s;
        }

        .clear-all-link:hover {
            background: #fee2e2;
            color: #ef4444;
        }



        .filter-select {
            width: 100%;
            height: 36px;
            padding: 6px 28px 6px 10px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 8px center;
            background-size: 14px;
            appearance: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-select:focus {
            background-color: white;
            border-color: var(--primary);
            box-shadow: var(--shadow-focus);
            outline: none;
        }



        /* Active Filter Tags */
        .active-filters {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px dashed #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .active-filters-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .filter-tag {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            padding: 3px 10px 3px 8px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }

        .filter-tag .remove-tag {
            color: #94a3b8;
            margin-left: 2px;
            cursor: pointer;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }

        .filter-tag .remove-tag:hover {
            color: #ef4444;
        }

        .filter-tag.clear-all {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            padding: 3px 10px;
        }

        /* ==================== ADD/EDIT JOB DRAWER ====================
           Same .ui-drawer pattern as client/user/view-user.blade.php's
           employee drawer — .form-row/.form-group/.required have no
           built-in styling in this app (Bootstrap 5, not the Bootstrap 4
           classes they're named after), so without this block the paired
           fields (Department/Designation, etc.) just stack full-width. */
        .ui-drawer,
        .ui-drawer .form-control,
        .ui-drawer .form-select,
        .ui-drawer label {
            font-size: 12.5px;
        }

        .ui-drawer .form-label {
            font-weight: 400;
            margin-bottom: 4px;
            display: inline-block;
        }

        .ui-drawer .form-group {
            margin-bottom: 10px;
        }

        .ui-drawer .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .ui-drawer .required {
            color: #ef4444;
            margin-left: 2px;
        }

        @media (max-width: 480px) {
            .ui-drawer .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* theme.min.css's .btn-primary sets background/border-color with
           !important (vendor default), so the override needs !important too
           — see the identical note in client/user/view-user.blade.php. */
        .add-job-btn {
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD) !important;
            border-color: #0D6EFD !important;
        }

        .add-job-btn:hover {
            background: #0B5ED7 !important;
            border-color: #0B5ED7 !important;
        }

        /* ==================== JOB STATUS BADGES ==================== */
        .job-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
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
            background-color: #dbeafe;
            color: #0D6EFD;
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
            color: #0D6EFD;
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








        .dropdown-item {
            font-size: 12px;
            padding: 6px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dropdown-item i {
            font-size: 12px;
        }

        /* Application Count Badge */
        .app-count {
            background: var(--primary-light);
            color: var(--primary);
            padding: 4px 8px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .app-count:hover {
            background: var(--primary);
            color: white;
        }

        /* Vacancy Badge */
        .vacancy-badge {
            background: #f1f5f9;
            color: #475569;
            padding: 4px 8px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        /* Empty State */
        .empty-state {
            padding: 48px 24px;
            text-align: center;
        }

        .empty-state i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-state h4 {
            font-size: 16px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 12px;
            color: #64748b;
        }





        /* Responsive */
        @media (max-width: 992px) {



            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {




            .stats-grid {
                grid-template-columns: 1fr;
            }

        }

        /* Toast Notification */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
@endsection

@section('content-area')
    <!-- Page Header -->
    <x-ui.page-header title="Job Openings Management" current="Job Openings">
        <x-slot:actions>
            <button type="button" class="btn btn-primary btn-sm add-job-btn" onclick="openAddJobDrawer()">
                <i class="feather-plus me-1"></i>
                <span>New Job Opening</span>
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <!-- Main Content -->
    <div class="main-content" style="padding: 20px !important;">

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stats-card" data-status="all">
                <div class="stats-info">
                    <h3>{{ $stats['total'] ?? 0 }}</h3>
                    <p>Total Jobs</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-briefcase"></i>
                </div>
            </div>
            <div class="stats-card" data-status="published">
                <div class="stats-info">
                    <h3>{{ $stats['published'] ?? 0 }}</h3>
                    <p>Published</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-check-circle"></i>
                </div>
            </div>
            <div class="stats-card" data-status="draft">
                <div class="stats-info">
                    <h3>{{ $stats['draft'] ?? 0 }}</h3>
                    <p>Draft</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-edit"></i>
                </div>
            </div>
            <div class="stats-card" data-status="closed">
                <div class="stats-info">
                    <h3>{{ $stats['closed'] ?? 0 }}</h3>
                    <p>Closed</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-x-circle"></i>
                </div>
            </div>
            <div class="stats-card" data-status="on_hold">
                <div class="stats-info">
                    <h3>{{ $stats['on_hold'] ?? 0 }}</h3>
                    <p>On Hold</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-pause-circle"></i>
                </div>
            </div>
            <div class="stats-card" data-applications="true">
                <div class="stats-info">
                    <h3>{{ $stats['total_applications'] ?? 0 }}</h3>
                    <p>Total Applications</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-users"></i>
                </div>
            </div>
        </div>

        <!-- Modern Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Job Openings
                    @php
                        $activeFilterCount = collect(
                            request()->only(['status', 'department_id', 'employment_type', 'search']),
                        )
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span class="badge bg-primary ms-2">{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (
                    request()->hasAny(['status', 'department_id', 'employment_type', 'search']) &&
                        request('status') != 'all')
                    <a href="{{ route('job-openings.index') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('job-openings.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <!-- Status Filter -->
                    <div class="filter-item">
                        <select name="status" class="filter-select" id="statusFilter">
                            <option value="all" {{ request('status') == 'all' || !request('status') ? 'selected' : '' }}>All Status</option>
                            <option value="published" {{ request('status') == 'published' ? 'selected' : '' }}>Published</option>
                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                            <option value="on_hold" {{ request('status') == 'on_hold' ? 'selected' : '' }}>On Hold</option>
                        </select>
                    </div>

                    <!-- Department Filter -->
                    <div class="filter-item">
                        <select name="department_id" class="filter-select">
                            <option value="">All Departments</option>
                            @foreach ($departments ?? [] as $department)
                                <option value="{{ $department->id }}"
                                    {{ request('department_id') == $department->id ? 'selected' : '' }}>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Employment Type Filter -->
                    <div class="filter-item">
                        <select name="employment_type" class="filter-select">
                            <option value="">All Types</option>
                            <option value="full_time" {{ request('employment_type') == 'full_time' ? 'selected' : '' }}>Full Time</option>
                            <option value="part_time" {{ request('employment_type') == 'part_time' ? 'selected' : '' }}>Part Time</option>
                            <option value="contract" {{ request('employment_type') == 'contract' ? 'selected' : '' }}>Contract</option>
                            <option value="internship" {{ request('employment_type') == 'internship' ? 'selected' : '' }}>Internship</option>
                            <option value="temporary" {{ request('employment_type') == 'temporary' ? 'selected' : '' }}>Temporary</option>
                        </select>
                    </div>

                    <!-- Search Filter -->
                    <div class="filter-item" style="flex: 1; min-width: 200px;">
                        <input type="text" name="search" class="filter-select" placeholder="Search by title, code or location..."
                               value="{{ request('search') }}" style="background-image: none; padding-left: 32px;">
                    </div>

                    <!-- Reset Button -->
                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('job-openings.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (
                request()->hasAny(['status', 'department_id', 'employment_type', 'search']) &&
                    request('status') != 'all')
                <div class="active-filters">
                    <span class="active-filters-label">Active Filters:</span>

                    @if (request('status') && request('status') != 'all')
                        <span class="filter-tag">
                            <i class="feather-tag"></i>
                            Status: {{ ucfirst(str_replace('_', ' ', request('status'))) }}
                            <a href="{{ route('job-openings.index', array_merge(request()->except(['status', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('department_id'))
                        @php
                            $dept = isset($departments) ? $departments->firstWhere('id', request('department_id')) : null;
                        @endphp
                        <span class="filter-tag">
                            <i class="feather-folder"></i>
                            Department: {{ $dept->name ?? 'Unknown' }}
                            <a href="{{ route('job-openings.index', array_merge(request()->except(['department_id', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('employment_type'))
                        <span class="filter-tag">
                            <i class="feather-briefcase"></i>
                            Type: {{ ucfirst(str_replace('_', ' ', request('employment_type'))) }}
                            <a href="{{ route('job-openings.index', array_merge(request()->except(['employment_type', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('search'))
                        <span class="filter-tag">
                            <i class="feather-search"></i>
                            Search: "{{ request('search') }}"
                            <a href="{{ route('job-openings.index', array_merge(request()->except(['search', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('job-openings.index') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- Job Openings Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-body p-0">

                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                                <i class="feather-check-circle me-1"></i>
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                                <i class="feather-alert-circle me-1"></i>
                                {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                            </div>
                        @endif

                        @if (session('warning'))
                            <div class="alert alert-warning alert-dismissible fade show m-3" role="alert">
                                <i class="feather-alert-triangle me-1"></i>
                                {{ session('warning') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Job Code</th>
                                        <th>Job Title</th>
                                        <th>Department</th>
                                        <th>Location</th>
                                        <th>Type</th>
                                        <th class="text-center">Vacancies</th>
                                        <th class="text-center">Applications</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($jobOpenings as $index => $job)
                                        <tr class="clickable-row" data-href="{{ route('job-openings.show', $job->id) }}"
                                            style="cursor: pointer;">
                                            <td>{{ $jobOpenings->firstItem() + $index }}</td>
                                            <td>
                                                <strong class="text-primary">{{ $job->job_code }}</strong>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ Str::limit($job->title, 40) }}</div>
                                                @if ($job->experience_required)
                                                    <small class="text-muted d-block" style="font-size: 10px;">
                                                        Exp: {{ $job->experience_required }}
                                                    </small>
                                                @endif
                                            </td>
                                            <td>{{ $job->department->name ?? 'N/A' }}</td>
                                            <td>
                                                @if ($job->location)
                                                    <span title="{{ $job->location }}">{{ Str::limit($job->location, 20) }}</span>
                                                @else
                                                    <span class="text-muted">Not specified</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="employment-badge employment-{{ $job->employment_type }}">
                                                    {{ ucfirst(str_replace('_', ' ', $job->employment_type)) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="vacancy-badge" title="Number of vacancies">
                                                    <i class="feather-users"></i> {{ $job->no_of_vacancies }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('job-openings.applications', $job->id) }}"
                                                    class="app-count" onclick="event.stopPropagation();"
                                                    title="View all applications for {{ $job->title }}">
                                                    <i class="feather-file-text"></i>
                                                    {{ $job->applications_count ?? 0 }}
                                                </a>
                                            </td>
                                            <td>
                                                @if ($job->status == 'published')
                                                    <span class="job-status status-published">Published</span>
                                                @elseif($job->status == 'draft')
                                                    <span class="job-status status-draft">Draft</span>
                                                @elseif($job->status == 'closed')
                                                    <span class="job-status status-closed">Closed</span>
                                                @elseif($job->status == 'on_hold')
                                                    <span class="job-status status-on-hold">On Hold</span>
                                                @else
                                                    <span class="job-status">{{ ucfirst($job->status) }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small title="{{ $job->created_at->format('d M Y h:i A') }}">
                                                    {{ $job->created_at->format('d M, Y') }}
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <a href="{{ route('job-openings.show', $job->id) }}"
                                                        class="action-btn" title="View Details"
                                                        data-bs-toggle="tooltip" onclick="event.stopPropagation();">
                                                        <i class="feather-eye"></i>
                                                    </a>
                                                    <button type="button" class="action-btn" title="Edit Job"
                                                        data-bs-toggle="tooltip"
                                                        onclick="event.stopPropagation(); openEditJobDrawer({{ $job->id }})">
                                                        <i class="feather-edit"></i>
                                                    </button>
                                                    @if ($job->status == 'draft')
                                                        <button type="button" class="action-btn" title="Publish"
                                                            data-bs-toggle="tooltip"
                                                            onclick="event.stopPropagation(); publishJob({{ $job->id }})">
                                                            <i class="feather-send"></i>
                                                        </button>
                                                    @endif
                                                    @if ($job->status == 'published')
                                                        <button type="button" class="action-btn" title="Close Job"
                                                            data-bs-toggle="tooltip"
                                                            onclick="event.stopPropagation(); closeJob({{ $job->id }})">
                                                            <i class="feather-x-circle"></i>
                                                        </button>
                                                    @endif
                                                    <button type="button" class="action-btn" title="Duplicate"
                                                        data-bs-toggle="tooltip"
                                                        onclick="event.stopPropagation(); duplicateJob({{ $job->id }})">
                                                        <i class="feather-copy"></i>
                                                    </button>
                                                    @if ($job->status == 'draft' && ($job->applications_count ?? 0) == 0)
                                                        <button type="button" class="action-btn text-danger" title="Delete"
                                                            data-bs-toggle="tooltip"
                                                            onclick="event.stopPropagation(); deleteJob({{ $job->id }}, '{{ addslashes($job->title) }}')">
                                                            <i class="feather-trash-2"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center py-4">
                                                <div class="empty-state">
                                                    <i class="feather-inbox"></i>
                                                    <h4>No Job Openings Found</h4>
                                                    <p>No job openings match the selected filters.</p>
                                                    <button type="button" class="btn btn-primary btn-sm mt-2 add-job-btn"
                                                        onclick="openAddJobDrawer()">
                                                        <i class="feather-plus me-1"></i>Create New Job Opening
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if ($jobOpenings->hasPages())
                            <x-ui.pagination-footer :paginator="$jobOpenings" label="job openings" />
                        @endif
                    </div>
                </div>
            </div>
        </div>
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
                    <p>Are you sure you want to publish this job opening?</p>
                    <small class="text-muted">Once published, it will be visible on the career page.</small>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <form id="publishForm" method="POST" style="display: inline;">
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
                <form id="closeForm" method="POST">
                    @csrf
                    <div class="modal-body py-3">
                        <p>Are you sure you want to close this job opening?</p>
                        <div class="mb-2">
                            <label class="form-label small">Reason (Optional)</label>
                            <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Enter reason for closing..."></textarea>
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

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title">Delete Job Opening</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    <p>Are you sure you want to delete <strong id="deleteJobTitle"></strong>?</p>
                    <small class="text-danger">This action cannot be undone.</small>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Duplicate Form -->
    <form id="duplicateForm" method="POST" style="display: none;">
        @csrf
    </form>
@endsection

@section('create-modal')
    <!-- Add/Edit Job Opening Drawer (shared instance, mode toggled via JS) -->
    <x-ui.drawer id="jobDrawer" title="Add Job Opening" width="480px">
        @include('client.recruitment.job-openings.partials.form')
    </x-ui.drawer>
@endsection

@section('script-area')
    <script>
        // Row click handler
        $(document).ready(function() {
            $('.clickable-row').on('click', function(e) {
                if ($(e.target).closest('.dropdown, .action-btn, .dropdown-menu, .dropdown-item, .app-count, a, button').length === 0) {
                    window.location.href = $(this).data('href');
                }
            });

            // Auto-submit on filter change
            $('.filter-select').not('input[type="text"]').on('change', function() {
                $('#filterForm').submit();
            });

            // Search box: debounce instead of a submit-on-every-keystroke,
            // now that the Apply button is gone.
            let searchTimeout;
            $('input[name="search"]').on('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    $('#filterForm').submit();
                }, 500);
            });

            // Stats card click filter
            $('.stats-card').on('click', function() {
                const status = $(this).data('status');
                const applications = $(this).data('applications');
                
                if (applications) {
                    showToast('Total applications across all jobs: {{ $stats["total_applications"] ?? 0 }}', 'info');
                    return;
                }
                
                if (status && status !== 'all') {
                    window.location.href = '{{ route('job-openings.index') }}?status=' + status;
                } else if (status === 'all') {
                    window.location.href = '{{ route('job-openings.index') }}';
                }
            });

            // Highlight active stats card
            const currentStatus = '{{ request('status') }}';
            if (currentStatus && currentStatus !== 'all') {
                $(`.stats-card[data-status="${currentStatus}"]`).addClass('active');
            } else if (!currentStatus || currentStatus === 'all') {
                $('.stats-card[data-status="all"]').addClass('active');
            }

            // Initialize tooltips for the icon-only action buttons
            [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).forEach(function (el) {
                new bootstrap.Tooltip(el);
            });

            // Deep link support (e.g. a link from job-openings/show.blade.php):
            // ?edit={id} opens the Edit drawer pre-filled for that job.
            const deepLinkParams = new URLSearchParams(window.location.search);
            if (deepLinkParams.get('edit')) {
                openEditJobDrawer(deepLinkParams.get('edit'));
            }
        });

        // ─────────────────────────────────────────────
        //  ADD / EDIT JOB DRAWER
        // ─────────────────────────────────────────────

        const jobDrawerFields = [
            'title', 'department_id', 'designation_id', 'employment_type', 'location',
            'no_of_vacancies', 'status', 'description', 'experience_required',
            'qualification_required', 'skills_required', 'responsibilities', 'requirements',
            'salary_range_min', 'salary_range_max', 'hiring_lead',
        ];

        function resetJobDrawerForm() {
            const form = document.getElementById('jobDrawerForm');
            form.reset();
            jobDrawerFields.forEach(f => {
                const el = document.getElementById('jf_' + f);
                if (el) el.value = '';
            });
            document.getElementById('jf_status').value = 'draft';
            document.getElementById('jf_no_of_vacancies').value = 1;
        }

        function getJobDrawerInstance() {
            return bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('jobDrawer'));
        }

        function openAddJobDrawer() {
            resetJobDrawerForm();
            document.getElementById('jobDrawerLabel').textContent = 'Add Job Opening';
            document.getElementById('jobDrawerForm').action = "{{ route('job-openings.store') }}";
            document.getElementById('jobDrawerSubmitBtn').textContent = 'Create Job';
            getJobDrawerInstance().show();
        }

        function openEditJobDrawer(id) {
            fetch("{{ url('job-openings/edit') }}/" + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.json())
                .then(res => {
                    if (!res.success) {
                        showToast(res.message || 'Job opening not found', 'error');
                        return;
                    }
                    resetJobDrawerForm();
                    const job = res.data;
                    jobDrawerFields.forEach(f => {
                        const el = document.getElementById('jf_' + f);
                        if (el && job[f] !== null && job[f] !== undefined) el.value = job[f];
                    });
                    document.getElementById('jobDrawerLabel').textContent = 'Edit Job Opening';
                    document.getElementById('jobDrawerForm').action = "{{ url('job-openings/update') }}/" + id;
                    document.getElementById('jobDrawerSubmitBtn').textContent = 'Update Job';
                    getJobDrawerInstance().show();
                })
                .catch(() => showToast('Failed to load job opening', 'error'));
        }

        // CRUD Functions
        function publishJob(id) {
            const form = document.getElementById('publishForm');
            form.action = "{{ url('job-openings') }}/" + id + "/publish";
            new bootstrap.Modal(document.getElementById('publishModal')).show();
        }

        function closeJob(id) {
            const form = document.getElementById('closeForm');
            form.action = "{{ url('job-openings') }}/" + id + "/close";
            new bootstrap.Modal(document.getElementById('closeModal')).show();
        }

        function deleteJob(id, title) {
            document.getElementById('deleteJobTitle').textContent = title;
            const form = document.getElementById('deleteForm');
            form.action = "{{ url('job-openings/destroy') }}/" + id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function duplicateJob(id) {
            if (confirm('Are you sure you want to duplicate this job opening?')) {
                const form = document.getElementById('duplicateForm');
                form.action = "{{ url('job-openings') }}/" + id + "/duplicate";
                form.submit();
            }
        }

        // Show Toast Notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification alert alert-${type}`;
            toast.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
            toast.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="feather-${type === 'success' ? 'check-circle' : (type === 'error' ? 'alert-circle' : 'info')} me-2"></i>
                    <span>${message}</span>
                    <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert:not(.toast-notification)');
            alerts.forEach(function(alert) {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) {
                    setTimeout(function() {
                        closeBtn.click();
                    }, 5000);
                }
            });
        }, 100);
    </script>
@endsection