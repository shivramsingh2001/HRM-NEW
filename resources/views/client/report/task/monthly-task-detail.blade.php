@extends('client.layout.master')

@section('title', 'Monthly Task Detail Report')

@section('style')
    <style>








        .filter-input,
        .filter-select {
            width: 100%;
            height: 34px;
            padding: 4px 10px;
            font-size: 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            transition: all 0.2s;
            color: #0f172a;
            font-weight: 500;
        }

        .filter-select {
            padding-right: 28px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 8px center;
            background-size: 12px;
            appearance: none;
            cursor: pointer;
        }

        .filter-input:hover,
        .filter-select:hover { background-color: white; border-color: #cbd5e1; }

        .filter-input:focus,
        .filter-select:focus {
            border-color: var(--primary-mid);
            outline: none;
            background-color: white;
            box-shadow: var(--shadow-focus);
        }

        .btn-sm-custom-outline,
        .reset-button {
            height: 34px;
            padding: 0 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            text-decoration: none;
            background: white;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
        }

        .btn-sm-custom-outline:hover,
        .reset-button:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .btn-sm-custom-outline i,
        .reset-button i { font-size: 12px; }

        /* ==================== TASK CARDS ==================== */
        .task-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .task-card {
            background: white;
            border: 1px solid #edf2f7;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.2s;
        }

        .task-card:hover {
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
            border-color: #d1d5db;
        }

        .task-card-header {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            background: #fbfcfe;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            flex-wrap: wrap;
        }

        .task-code {
            font-family: monospace;
            font-size: 10px;
            font-weight: 700;
            color: var(--primary-mid);
            background: var(--primary-light);
            padding: 2px 8px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 4px;
        }

        .task-title {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 3px 0;
            line-height: 1.3;
        }

        .task-meta {
            font-size: 11px;
            color: #64748b;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .task-meta span { display: inline-flex; align-items: center; gap: 3px; }
        .task-meta i { font-size: 11px; }

        .task-badges {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            align-items: center;
        }

        /* Badges */
        .badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            display: inline-block;
            text-align: center;
            line-height: 1.4;
        }

        .badge-success   { background: #d1fae5; color: #065f46; }
        .badge-warning   { background: #fef3c7; color: #92400e; }
        .badge-danger    { background: #fee2e2; color: #991b1b; }
        .badge-info      { background: #dbeafe; color: #0D6EFD; }
        .badge-purple    { background: #ede9fe; color: #5b21b6; }
        .badge-secondary { background: #f1f5f9; color: #475569; }
        .badge-dark      { background: #e2e8f0; color: #0f172a; }
        .badge-primary   { background: var(--primary-light); color: var(--primary-mid); }

        .badge-priority-critical { background: #fee2e2; color: #991b1b; }
        .badge-priority-high     { background: #ffedd5; color: #9a3412; }
        .badge-priority-medium   { background: #fef3c7; color: #92400e; }
        .badge-priority-low      { background: #f1f5f9; color: #475569; }

        /* Card body */
        .task-card-body {
            padding: 10px 14px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
        }

        .task-section-title {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .task-section-title i { color: var(--icon-color, #0D6EFD); font-size: 11px; }

        /* People chips */
        .person-chip {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            background: #f8fafc;
            border-radius: 6px;
            margin-bottom: 4px;
            font-size: 11px;
        }

        .person-avatar {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary-mid);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            font-weight: 700;
            flex-shrink: 0;
            overflow: hidden;
        }

        .person-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .person-name {
            font-weight: 600;
            color: #1e293b;
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Updates timeline */
        .update-item {
            padding: 5px 8px;
            background: #f8fafc;
            border-left: 2px solid var(--primary-mid);
            border-radius: 3px;
            margin-bottom: 5px;
            font-size: 11px;
        }

        .update-item:last-child { margin-bottom: 0; }

        .update-meta {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #64748b;
            margin-bottom: 2px;
        }

        .update-status {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
        }

        .update-remarks {
            color: #334155;
            line-height: 1.3;
        }

        .empty-mini {
            font-size: 11px;
            color: #94a3b8;
            font-style: italic;
            padding: 4px 0;
        }

        /* ==================== MEDIA CHIPS ==================== */
        .media-row {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 6px;
        }

        .media-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            border: 1.5px solid transparent;
        }

        .media-chip i { font-size: 12px; }

        .media-chip.file {
            background: var(--primary-light);
            color: var(--primary-mid);
            border-color: #e0e7ff;
        }

        .media-chip.file:hover {
            background: var(--primary-mid);
            color: white;
            border-color: var(--primary-mid);
        }

        .media-chip.voice {
            background: #ede9fe;
            color: #7c3aed;
            border-color: #ddd6fe;
        }

        .media-chip.voice:hover {
            background: #7c3aed;
            color: white;
            border-color: #7c3aed;
        }

        .media-chip img {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            object-fit: cover;
        }

        /* View Detail Button */
        .btn-view-detail {
            background: linear-gradient(135deg, var(--primary-mid), #3b82f6);
            color: white;
            border: none;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: all 0.2s;
            line-height: 1.4;
            white-space: nowrap;
        }

        .btn-view-detail:hover {
            background: linear-gradient(135deg, var(--primary-mid), var(--primary-mid));
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(13, 110, 253, 0.3);
            color: white;
        }

        .btn-view-detail i { font-size: 11px; }

        /* Empty state */
        .empty-state { padding: 50px 20px; text-align: center; }
        .empty-state i { font-size: 42px; color: #cbd5e1; }
        .empty-state h4 { color: #0f172a; font-size: 16px; margin-top: 14px; }
        .empty-state p  { color: #94a3b8; font-size: 13px; }




        .pagination-wrapper nav { margin-left: auto; }
        .pagination-wrapper .pagination { margin: 0; gap: 3px; }




        /* ==================== MODAL ==================== */
        .task-modal .modal-content {
            border-radius: 14px;
            border: none;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.15);
        }

        .task-modal .modal-header {
            background: linear-gradient(135deg, var(--primary-mid), #3b82f6);
            color: white;
            padding: 14px 20px;
            border-bottom: none;
        }

        .task-modal .modal-header .modal-title {
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .task-modal .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.85;
        }

        .task-modal .modal-body {
            padding: 18px 20px;
            max-height: 78vh;
            overflow-y: auto;
            font-size: 12px;
        }

        .task-modal .modal-footer {
            border-top: 1px solid #f1f5f9;
            padding: 10px 20px;
            background: #fbfcfe;
        }

        .detail-section { margin-bottom: 16px; }

        .detail-section-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--primary-mid);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e0e7ff;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .detail-item {
            padding: 8px 10px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .detail-label {
            font-size: 10px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 3px;
        }

        .detail-value {
            font-size: 12px;
            color: #0f172a;
            font-weight: 600;
            word-break: break-word;
        }

        .detail-description {
            padding: 10px 12px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid var(--primary-mid);
            font-size: 12px;
            color: #334155;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .detail-media-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .detail-media-card {
            padding: 10px;
            border: 1.5px dashed #cbd5e1;
            border-radius: 8px;
            background: #fbfcfe;
            text-align: center;
        }

        .detail-media-card img {
            max-width: 100%;
            max-height: 130px;
            border-radius: 6px;
            margin-bottom: 6px;
            object-fit: cover;
        }

        .detail-media-card .media-icon {
            font-size: 26px;
            color: var(--icon-color, #0D6EFD);
            margin-bottom: 4px;
        }

        .detail-media-card .media-label {
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
        }

        .detail-media-card audio {
            width: 100%;
            height: 32px;
        }

        .detail-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
            background: var(--primary-mid);
            color: white;
            transition: all 0.2s;
        }

        .detail-btn:hover {
            background: #0B5ED7;
            color: white;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .task-card-body { grid-template-columns: 1fr; gap: 12px; }
            .detail-grid, .detail-media-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {


            .btn-sm-custom-outline, .reset-button {
                width: 100%;
                justify-content: center;
            }

            .pagination-wrapper nav { margin-left: 0; }
        }

        @media print {

            .btn-view-detail { display: none; }
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Monthly Task Detail Report" current="Task Detail" :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')]]">
        <x-slot:actions>
            <span class="badge badge-info-custom">
                <i class="feather-calendar me-1"></i>
                {{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}
            </span>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 16px !important;">
        @include('client.report.partials.report-subnav', ['group' => 'task'])

        {{-- ==================== FILTERS ==================== --}}
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Report
                    @php
                        $activeFilters = 0;
                        if(request('date_from')) $activeFilters++;
                        if(request('date_to')) $activeFilters++;
                        if(request('status') && request('status') != 'all') $activeFilters++;
                        if(request('priority') && request('priority') != 'all') $activeFilters++;
                        if(request('project_id') && request('project_id') != 'all') $activeFilters++;
                        if(request('assigned_to') && request('assigned_to') != 'all') $activeFilters++;
                        if(request('assigned_by') && request('assigned_by') != 'all') $activeFilters++;
                        if(request('branch_id') && request('branch_id') != 'all') $activeFilters++;
                        if(request('search')) $activeFilters++;
                    @endphp
                    @if($activeFilters > 0)
                        <span class="badge-count">{{ $activeFilters }} active</span>
                    @endif
                </div>
            </div>

            <form action="{{ route('report.task.monthly-task-detail') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item grow">
                        <input type="date" name="date_from" class="filter-input auto-submit"
                               value="{{ request('date_from', $from->format('Y-m-d')) }}">
                    </div>

                    <div class="filter-item grow">
                        <input type="date" name="date_to" class="filter-input auto-submit"
                               value="{{ request('date_to', $to->format('Y-m-d')) }}">
                    </div>

                    <div class="filter-item grow">
                        <select name="status" class="filter-select auto-submit">
                            <option value="all">All Statuses</option>
                            @foreach(['pending','in_progress','hold','completed','approved','rejected','cancelled'] as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_',' ',$s)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-item grow">
                        <select name="priority" class="filter-select auto-submit">
                            <option value="all">All Priorities</option>
                            @foreach(['low','medium','high','critical'] as $p)
                                <option value="{{ $p }}" {{ request('priority') == $p ? 'selected' : '' }}>
                                    {{ ucfirst($p) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-item grow">
                        <select name="project_id" class="filter-select auto-submit">
                            <option value="all">All Projects</option>
                            @foreach($projectList as $p)
                                <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-item grow">
                        <select name="assigned_to" class="filter-select auto-submit">
                            <option value="all">All Assignees</option>
                            @foreach($allUsers as $u)
                                <option value="{{ $u->id }}" {{ request('assigned_to') == $u->id ? 'selected' : '' }}>
                                    {{ $u->name }} ({{ $u->employee_id }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-item grow">
                        <select name="assigned_by" class="filter-select auto-submit">
                            <option value="all">All Assigners</option>
                            @foreach($allUsers as $u)
                                <option value="{{ $u->id }}" {{ request('assigned_by') == $u->id ? 'selected' : '' }}>
                                    {{ $u->name }} ({{ $u->employee_id }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-item grow">
                        <select name="branch_id" class="filter-select auto-submit">
                            <option value="all">All Branches</option>
                            @foreach($branchList as $b)
                                <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>
                                    {{ $b->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-item grow">
                        <input type="text" name="search" onchange="this.form.submit()" onkeydown="if (event.key === 'Enter') { event.preventDefault(); this.form.submit(); }" class="filter-input"
                               placeholder="Search task code, title or assignee…"
                               value="{{ request('search') }}">
                    </div>


                    <div class="filter-item">
                        <a href="{{ route('report.task.monthly-task-detail') }}" class="reset-button" title="Reset filters" aria-label="Reset filters">
                            <i class="feather-refresh-cw"></i></a>
                    </div>

                    <div class="filter-item">
                        <a href="{{ route('report.task.monthly-task-detail.export', request()->query()) }}"
                           class="btn-sm-custom-outline" target="_blank">
                            <i class="feather-download"></i> Export CSV
                        </a>
                    </div>
                </div>
            </form>
        </div>

        {{-- ==================== TASK LIST ==================== --}}
        <div class="task-list">
            @forelse($tasks as $task)
                <div class="task-card">
                    {{-- HEADER --}}
                    <div class="task-card-header">
                        <div style="flex: 1; min-width: 0;">
                            <span class="task-code">{{ $task->task_code }}</span>
                            <h6 class="task-title">{{ $task->title }}</h6>
                            <div class="task-meta">
                                <span><i class="feather-briefcase"></i> {{ $task->project_name ?? 'No Project' }}</span>
                                <span><i class="feather-calendar"></i> {{ \Carbon\Carbon::parse($task->task_date)->format('d M, Y') }}</span>
                                <span><i class="feather-clock"></i> Due: {{ \Carbon\Carbon::parse($task->deadline_date)->format('d M, Y') }}</span>
                                <span><i class="feather-message-square"></i> {{ $task->update_count }} update(s)</span>
                            </div>

                            {{-- Attachments --}}
                            @if(!empty($task->file) || !empty($task->voice_file))
                                <div class="media-row">
                                    @if(!empty($task->file))
                                        @php
                                            $ext = strtolower(pathinfo($task->file, PATHINFO_EXTENSION));
                                            $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                                        @endphp
                                        <a href="{{ file_url($task->file, 'task_document') }}" target="_blank" class="media-chip file" title="Open document">
                                            @if($isImage)
                                                <img src="{{ file_url($task->file, 'task_document') }}" alt="file">
                                            @else
                                                <i class="feather-paperclip"></i>
                                            @endif
                                            Document
                                        </a>
                                    @endif

                                    @if(!empty($task->voice_file))
                                        <a href="{{ file_url($task->voice_file, 'task_voice') }}" target="_blank" class="media-chip voice" title="Play voice note">
                                            <i class="feather-mic"></i>
                                            Voice Note
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="task-badges">
                            <span class="badge badge-priority-{{ $task->priority }}">
                                {{ ucfirst($task->priority) }}
                            </span>
                            @php
                                $statusClass = [
                                    'pending' => 'warning',
                                    'in_progress' => 'info',
                                    'hold' => 'secondary',
                                    'completed' => 'success',
                                    'approved' => 'purple',
                                    'rejected' => 'danger',
                                    'cancelled' => 'dark',
                                ][$task->status] ?? 'secondary';
                            @endphp
                            <span class="badge badge-{{ $statusClass }}">
                                {{ ucfirst(str_replace('_',' ',$task->status)) }}
                            </span>
                            <span class="badge badge-secondary">
                                {{ ucfirst($task->task_mode ?? 'individual') }}
                            </span>

                            <button type="button"
                                    class="btn-view-detail"
                                    data-bs-toggle="modal"
                                    data-bs-target="#taskDetailModal"
                                    data-task='@json($task)'>
                                <i class="feather-eye"></i> View
                            </button>
                        </div>
                    </div>

                    {{-- BODY: 3 columns --}}
                    <div class="task-card-body">

                        {{-- COLUMN 1 : ASSIGNED BY --}}
                        <div>
                            <div class="task-section-title">
                                <i class="feather-user-check"></i> Assigned By
                            </div>
                            @forelse($task->assigners as $a)
                                <div class="person-chip">
                                    <div class="person-avatar">
                                        @if(!empty($a->profile_image))
                                            <img src="{{ file_url($a->profile_image, 'profile_photo') }}" alt="{{ $a->name }}">
                                        @else
                                            {{ strtoupper(substr($a->name, 0, 2)) }}
                                        @endif
                                    </div>
                                    <span class="person-name">{{ $a->name }}</span>
                                </div>
                            @empty
                                <div class="empty-mini">No assigner info</div>
                            @endforelse
                        </div>

                        {{-- COLUMN 2 : ASSIGNED TO --}}
                        <div>
                            <div class="task-section-title">
                                <i class="feather-users"></i> Assigned To ({{ $task->assignees->count() }})
                            </div>
                            @forelse($task->assignees as $a)
                                <div class="person-chip">
                                    <div class="person-avatar">
                                        @if(!empty($a->profile_image))
                                            <img src="{{ file_url($a->profile_image, 'profile_photo') }}" alt="{{ $a->name }}">
                                        @else
                                            {{ strtoupper(substr($a->name, 0, 2)) }}
                                        @endif
                                    </div>
                                    <span class="person-name">{{ $a->name }}</span>
                                    @if(!empty($a->branch_name))
                                        <span class="badge badge-secondary" style="font-size: 9px; padding: 1px 6px;">{{ $a->branch_name }}</span>
                                    @endif
                                    @if($a->member_role === 'lead')
                                        <span class="badge badge-primary" style="font-size: 9px; padding: 1px 6px;">Lead</span>
                                    @endif
                                    @if(!empty($a->individual_status))
                                        <span class="badge badge-secondary" style="font-size: 9px; padding: 1px 6px;">
                                            {{ ucfirst(str_replace('_',' ',$a->individual_status)) }}
                                        </span>
                                    @endif
                                </div>
                            @empty
                                <div class="empty-mini">No assignees</div>
                            @endforelse
                        </div>

                        {{-- COLUMN 3 : UPDATES TIMELINE --}}
                        <div>
                            <div class="task-section-title">
                                <i class="feather-activity"></i> Updates ({{ $task->updates->count() }})
                            </div>
                            @forelse($task->updates as $u)
                                <div class="update-item">
                                    <div class="update-meta">
                                        <span class="update-status">
                                            {{ ucfirst(str_replace('_',' ',$u->status)) }}
                                        </span>
                                        <span>{{ \Carbon\Carbon::parse($u->created_at)->format('d M, h:i A') }}</span>
                                    </div>
                                    <div class="update-remarks">
                                        <strong>{{ $u->updated_by_name ?? '-' }}:</strong>
                                        {{ $u->remarks ?: 'No remarks' }}
                                    </div>
                                </div>
                            @empty
                                <div class="empty-mini">No updates yet</div>
                            @endforelse
                        </div>

                    </div>
                </div>
            @empty
                <div class="table-wrapper">
                    <div class="empty-state">
                        <i class="feather-file-text"></i>
                        <h4>No Tasks Found</h4>
                        <p>No tasks match the selected filters.</p>
                    </div>
                </div>
            @endforelse
        </div>

        {{-- ==================== PAGINATION ==================== --}}
        @if($tasks->hasPages() || $tasks->total() > 0)
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Showing <strong>{{ $tasks->firstItem() ?? 0 }}</strong>
                    to <strong>{{ $tasks->lastItem() ?? 0 }}</strong>
                    of <strong>{{ $tasks->total() }}</strong> tasks
                </div>
                <nav>
                    {{ $tasks->links('pagination::bootstrap-4') }}
                </nav>
            </div>
        @endif
    </div>

    {{-- ==================== TASK DETAIL MODAL ==================== --}}
    <div class="modal fade task-modal" id="taskDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">
                        <i class="feather-file-text"></i>
                        <span id="modalTaskCode">Task Detail</span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" id="modalTaskBody">
                    {{-- Filled by JS --}}
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 text-muted">Loading...</p>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000"
        };

        /* ==================== AUTO-SUBMIT FILTERS ==================== */
        document.querySelectorAll('.auto-submit').forEach(el => {
            el.addEventListener('change', () => el.closest('form').submit());
        });

        /* ==================== TASK DETAIL MODAL ==================== */
        document.addEventListener('DOMContentLoaded', function () {

            const modalEl    = document.getElementById('taskDetailModal');
            const modalTitle = document.getElementById('modalTaskCode');
            const modalBody  = document.getElementById('modalTaskBody');

            modalEl.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;
                if (!trigger) return;

                let task;
                try {
                    task = JSON.parse(trigger.getAttribute('data-task'));
                } catch (e) {
                    console.error('Invalid task JSON', e);
                    return;
                }

                modalTitle.textContent = task.task_code + ' — ' + task.title;
                modalBody.innerHTML = renderTaskDetail(task);
            });

            /* ---------- Helpers ---------- */
            function esc(str) {
                if (str === null || str === undefined) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function badgeFor(status) {
                const map = {
                    pending:     'badge-warning',
                    in_progress: 'badge-info',
                    hold:        'badge-secondary',
                    completed:   'badge-success',
                    approved:    'badge-purple',
                    rejected:    'badge-danger',
                    cancelled:   'badge-dark',
                };
                const cls = map[status] || 'badge-secondary';
                return '<span class="badge ' + cls + '">' +
                    esc(status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())) +
                    '</span>';
            }

            function priorityBadge(p) {
                return '<span class="badge badge-priority-' + esc(p) + '">' +
                    esc(p.charAt(0).toUpperCase() + p.slice(1)) +
                    '</span>';
            }

            function personChip(person) {
                const name   = esc(person.name || '-');
                const img    = person.profile_image_url || null;
                const initials = (person.name || '??').substring(0, 2).toUpperCase();

                const avatar = img
                    ? '<div class="person-avatar"><img src="' + esc(img) + '" alt=""></div>'
                    : '<div class="person-avatar">' + esc(initials) + '</div>';

                let extra = '';
                if (person.member_role === 'lead') {
                    extra += ' <span class="badge badge-primary" style="font-size:9px;padding:1px 6px;">Lead</span>';
                }
                if (person.individual_status) {
                    extra += ' <span class="badge badge-secondary" style="font-size:9px;padding:1px 6px;">' +
                        esc(person.individual_status.replace(/_/g,' ')) + '</span>';
                }

                return '<div class="person-chip">' + avatar +
                       '<span class="person-name">' + name + '</span>' + extra + '</div>';
            }

            function renderTaskDetail(task) {
                let html = '';

                /* ---------- Task meta grid ---------- */
                html += '<div class="detail-section">';
                html += '  <div class="detail-section-title"><i class="feather-info"></i> Task Info</div>';
                html += '  <div class="detail-grid">';
                html += detailItem('Project', task.project_name || '-');
                html += detailItem('Task Date', task.task_date);
                html += detailItem('Deadline', task.deadline_date);
                html += detailItem('Task Mode', (task.task_mode || 'individual'));
                html += '    <div class="detail-item"><div class="detail-label">Priority</div><div class="detail-value">' + priorityBadge(task.priority) + '</div></div>';
                html += '    <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value">' + badgeFor(task.status) + '</div></div>';
                html += '  </div>';
                html += '</div>';

                /* ---------- Description ---------- */
                if (task.description) {
                    html += '<div class="detail-section">';
                    html += '  <div class="detail-section-title"><i class="feather-align-left"></i> Description</div>';
                    html += '  <div class="detail-description">' + esc(task.description) + '</div>';
                    html += '</div>';
                }

                /* ---------- Attachments ---------- */
                if (task.file || task.voice_file) {
                    html += '<div class="detail-section">';
                    html += '  <div class="detail-section-title"><i class="feather-paperclip"></i> Attachments</div>';
                    html += '  <div class="detail-media-grid">';

                    if (task.file) {
                        const ext = (task.file.split('.').pop() || '').toLowerCase();
                        const isImg = ['jpg','jpeg','png','gif','webp'].includes(ext);
                        const url = task.file_url;

                        html += '<div class="detail-media-card">';
                        if (isImg) {
                            html += '<img src="' + esc(url) + '" alt="file">';
                        } else {
                            html += '<div class="media-icon"><i class="feather-file"></i></div>';
                        }
                        html += '<div class="media-label">Document (' + esc(ext.toUpperCase()) + ')</div>';
                        html += '<a class="detail-btn" href="' + esc(url) + '" target="_blank"><i class="feather-external-link"></i> Open</a>';
                        html += '</div>';
                    }

                    if (task.voice_file) {
                        const vurl = task.voice_file_url;
                        html += '<div class="detail-media-card">';
                        html += '<div class="media-icon"><i class="feather-mic"></i></div>';
                        html += '<div class="media-label">Voice Note</div>';
                        html += '<audio controls preload="none" src="' + esc(vurl) + '"></audio>';
                        html += '<a class="detail-btn" style="margin-top:6px;" href="' + esc(vurl) + '" target="_blank"><i class="feather-download"></i> Download</a>';
                        html += '</div>';
                    }

                    html += '  </div>';
                    html += '</div>';
                }

                /* ---------- Assigners ---------- */
                html += '<div class="detail-section">';
                html += '  <div class="detail-section-title"><i class="feather-user-check"></i> Assigned By</div>';
                if (task.assigners && task.assigners.length) {
                    task.assigners.forEach(a => html += personChip(a));
                } else {
                    html += '<div class="empty-mini">No assigner info</div>';
                }
                html += '</div>';

                /* ---------- Assignees ---------- */
                html += '<div class="detail-section">';
                html += '  <div class="detail-section-title"><i class="feather-users"></i> Assigned To (' + (task.assignees ? task.assignees.length : 0) + ')</div>';
                if (task.assignees && task.assignees.length) {
                    task.assignees.forEach(a => html += personChip(a));
                } else {
                    html += '<div class="empty-mini">No assignees</div>';
                }
                html += '</div>';

                /* ---------- Updates ---------- */
                html += '<div class="detail-section">';
                html += '  <div class="detail-section-title"><i class="feather-activity"></i> Updates Timeline (' + (task.updates ? task.updates.length : 0) + ')</div>';
                if (task.updates && task.updates.length) {
                    task.updates.forEach(u => {
                        const when = u.created_at ? new Date(u.created_at).toLocaleString() : '-';
                        html += '<div class="update-item">';
                        html += '  <div class="update-meta">';
                        html += '    <span class="update-status">' + esc((u.status || '').replace(/_/g,' ')) + '</span>';
                        html += '    <span>' + esc(when) + '</span>';
                        html += '  </div>';
                        html += '  <div class="update-remarks"><strong>' + esc(u.updated_by_name || '-') + ':</strong> ' + esc(u.remarks || 'No remarks') + '</div>';
                        html += '</div>';
                    });
                } else {
                    html += '<div class="empty-mini">No updates yet</div>';
                }
                html += '</div>';

                /* ---------- Approvals ---------- */
                if (task.approvals && task.approvals.length) {
                    html += '<div class="detail-section">';
                    html += '  <div class="detail-section-title"><i class="feather-award"></i> Approvals</div>';
                    task.approvals.forEach(a => {
                        const when = a.created_at ? new Date(a.created_at).toLocaleString() : '-';
                        html += '<div class="update-item" style="border-left-color:#8b5cf6;">';
                        html += '  <div class="update-meta">';
                        html += '    <span class="update-status">' + esc((a.approval_status || '').toUpperCase()) + '</span>';
                        html += '    <span>' + esc(when) + '</span>';
                        html += '  </div>';
                        html += '  <div class="update-remarks"><strong>' + esc(a.approved_by_name || '-') + ':</strong> ' + esc(a.remarks || 'No remarks') + '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                }

                return html;
            }

            function detailItem(label, value) {
                return '<div class="detail-item">' +
                    '<div class="detail-label">' + esc(label) + '</div>' +
                    '<div class="detail-value">' + esc(value) + '</div>' +
                    '</div>';
            }
        });
    </script>
@endsection