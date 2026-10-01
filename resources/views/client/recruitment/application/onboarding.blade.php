{{-- resources/views/client/recruitment/application/onboarding.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        .onb-header {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #fff;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 16px;
        }

        .onb-header h4 { font-size: 16px; font-weight: 600; margin-bottom: 4px; }
        .onb-header .sub { font-size: 12px; opacity: .9; }

        .onb-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(255,255,255,.15);
        }

        .card-compact { border-radius: 10px; border: 1px solid #eef2f6; margin-bottom: 16px; }
        .card-compact .card-header { padding: 10px 16px; font-size: 13px; font-weight: 600; background: #f8fafc; }
        .card-compact .card-body { padding: 14px 16px; }

        .task-category-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
            color: var(--primary);
            margin: 12px 0 6px;
        }

        .task-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 10px;
            border: 1px solid #f1f5f9;
            border-radius: 8px;
            margin-bottom: 6px;
            font-size: 12.5px;
        }

        .task-row .task-name { font-weight: 500; }
        .task-row .task-meta { font-size: 10.5px; color: #64748b; }

        .task-status-select {
            font-size: 11px;
            padding: 3px 6px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .doc-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            border: 1px solid #f1f5f9;
            border-radius: 8px;
            margin-bottom: 6px;
            font-size: 12.5px;
        }

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
        }

        .action-btn:hover { background: #fff; color: var(--primary); border-color: var(--primary); }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title"><h5 class="m-b-10">Onboarding Checklist</h5></div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('job-openings.index') }}">Job Openings</a></li>
                <li class="breadcrumb-item active">Onboarding</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <div class="onb-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>{{ $application->candidate->full_name }}</h4>
                <div class="sub">{{ $application->jobOpening->title }} &middot; Assignment {{ $assignment->assignment_code }}</div>
            </div>
            <span class="onb-status-pill">
                <i class="feather-loader"></i> {{ $assignment->status_label }}
            </span>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-7">
                <div class="card card-compact">
                    <div class="card-header">
                        <i class="feather-check-square me-1"></i> Checklist Tasks
                    </div>
                    <div class="card-body">
                        @php $grouped = $assignment->taskItems->groupBy(fn($i) => $i->task->task_category ?? 'other'); @endphp
                        @forelse ($grouped as $category => $items)
                            <div class="task-category-title">{{ ucfirst(str_replace('_', ' ', $category)) }}</div>
                            @foreach ($items as $item)
                                <div class="task-row" id="task-item-{{ $item->id }}">
                                    <div>
                                        <div class="task-name">{{ $item->task->task_name ?? 'Task' }}
                                            @if ($item->task && !$item->task->is_mandatory)
                                                <span class="badge bg-light text-dark" style="font-size:9px;">optional</span>
                                            @endif
                                        </div>
                                        <div class="task-meta">
                                            {{ $item->task->assigned_to_role ?? '' }}
                                            @if ($item->due_date) &middot; Due {{ $item->due_date->format('d M Y') }} @endif
                                            @if ($item->completedBy) &middot; by {{ $item->completedBy->name }} @endif
                                        </div>
                                    </div>
                                    <select class="task-status-select" onchange="updateTaskItem({{ $item->id }}, this.value)">
                                        @foreach (\App\Models\OnboardingTaskItem::$statuses as $key => $label)
                                            <option value="{{ $key }}" @selected($item->status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        @empty
                            <p class="text-muted" style="font-size:12px;">No checklist tasks configured.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card card-compact">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="feather-file-text me-1"></i> Documents</span>
                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#docUploadForm">
                            <i class="feather-upload"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <form id="docUploadForm" class="collapse mb-3" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-2">
                                <div class="col-6">
                                    <select name="document_type" class="form-select form-select-sm" required>
                                        @foreach (\App\Models\CandidateDocument::$documentTypes as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <input type="file" name="file" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-12">
                                    <button type="button" class="btn btn-primary btn-sm w-100" onclick="uploadDocument()">Upload</button>
                                </div>
                            </div>
                        </form>

                        @forelse ($documents as $doc)
                            <div class="doc-row" id="doc-row-{{ $doc->id }}">
                                <div>
                                    <div class="fw-semibold">{{ $doc->document_type_label }}</div>
                                    <div class="task-meta">
                                        {{ $doc->document_name }}
                                        @if ($doc->is_verified)
                                            <span class="badge bg-success" style="font-size:9px;">Verified</span>
                                        @else
                                            <span class="badge bg-secondary" style="font-size:9px;">Unverified</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex gap-1">
                                    <a href="{{ file_url($doc->file_url, 'candidate_document') }}" target="_blank" class="action-btn" title="View" data-bs-toggle="tooltip">
                                        <i class="feather-eye"></i>
                                    </a>
                                    @unless ($doc->is_verified)
                                        <button class="action-btn text-success" title="Verify" data-bs-toggle="tooltip"
                                            onclick="verifyDocument({{ $doc->id }})">
                                            <i class="feather-check"></i>
                                        </button>
                                    @endunless
                                </div>
                            </div>
                        @empty
                            <p class="text-muted" style="font-size:12px;">No documents uploaded yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card card-compact">
                    <div class="card-header"><i class="feather-flag me-1"></i> Finish Onboarding</div>
                    <div class="card-body">
                        @if ($assignment->onboarding_status !== 'completed')
                            <p style="font-size:12px;" class="text-muted">
                                All mandatory tasks must be Completed or Skipped before onboarding can be marked complete.
                            </p>
                            <button type="button" class="btn btn-primary btn-sm w-100" onclick="completeOnboarding()">
                                <i class="feather-check-circle me-1"></i> Mark Onboarding Complete
                            </button>
                        @elseif ($assignment->user_id)
                            <div class="alert alert-success py-2" style="font-size:12px;">
                                <i class="feather-check-circle me-1"></i> Employee record already created.
                            </div>
                        @else
                            <form id="hireForm">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label small">Employee Type</label>
                                    <select name="type" class="form-select form-select-sm">
                                        <option value="office">Office</option>
                                        <option value="field">Field</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Attendance Location</label>
                                    <select name="branch" class="form-select form-select-sm">
                                        <option value="">{{ $branches->count() > 1 ? 'All Locations' : '-- None --' }}</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Company Branch</label>
                                    <select name="company_branch" class="form-select form-select-sm">
                                        <option value="">Select</option>
                                        @foreach ($companyBranches as $cb)
                                            <option value="{{ $cb->id }}">{{ $cb->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Role</label>
                                    <select name="role" class="form-select form-select-sm">
                                        <option value="employee">Employee</option>
                                        <option value="manager">Manager</option>
                                        <option value="hr">HR</option>
                                    </select>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm w-100" onclick="hireEmployee()">
                                    <i class="feather-user-plus me-1"></i> Create Employee
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        const assignmentId = {{ $assignment->id }};

        function postJson(url, data) {
            let body, contentType;
            if (data instanceof FormData) { body = data; contentType = undefined; }
            else { body = new URLSearchParams(data); contentType = 'application/x-www-form-urlencoded'; }
            const headers = {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            };
            if (contentType) headers['Content-Type'] = contentType;
            return fetch(url, { method: 'POST', headers, body }).then(r => r.json());
        }

        function showToast(message, type) {
            document.querySelector('.toast-container')?.remove();
            const wrap = document.createElement('div');
            wrap.className = 'toast-container position-fixed top-0 end-0 p-3';
            wrap.style.zIndex = '9999';
            wrap.innerHTML = `<div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 show">
                <div class="d-flex"><div class="toast-body">${message}</div>
                <button class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast-container').remove()"></button></div></div>`;
            document.body.appendChild(wrap);
            setTimeout(() => wrap.remove(), 4000);
        }

        function updateTaskItem(itemId, status) {
            postJson(`/recruitment/onboarding/task-items/${itemId}`, { status }).then(res => {
                if (!res.success) showToast(res.message || 'Failed to update task', 'error');
                else showToast('Task updated', 'success');
            });
        }

        function uploadDocument() {
            const form = document.getElementById('docUploadForm');
            const fd = new FormData(form);
            postJson(`/recruitment/onboarding/${assignmentId}/documents`, fd).then(res => {
                if (res.success) { showToast('Document uploaded', 'success'); setTimeout(() => location.reload(), 1000); }
                else showToast(res.message || (res.errors ? Object.values(res.errors).flat().join(' ') : 'Upload failed'), 'error');
            });
        }

        function verifyDocument(docId) {
            postJson(`/recruitment/onboarding/documents/${docId}/verify`, {}).then(res => {
                if (res.success) { showToast('Document verified', 'success'); setTimeout(() => location.reload(), 800); }
                else showToast(res.message || 'Failed', 'error');
            });
        }

        function completeOnboarding() {
            if (!confirm('Mark onboarding as complete? This unlocks Create Employee.')) return;
            postJson(`/recruitment/onboarding/${assignmentId}/complete`, {}).then(res => {
                showToast(res.message, res.success ? 'success' : 'error');
                if (res.success) setTimeout(() => location.reload(), 1200);
            });
        }

        function hireEmployee() {
            const form = document.getElementById('hireForm');
            const fd = new FormData(form);
            postJson(`/recruitment/onboarding/${assignmentId}/hire`, fd).then(res => {
                showToast(res.message || (res.errors ? Object.values(res.errors).flat().join(' ') : 'Failed'), res.success ? 'success' : 'error');
                if (res.success) setTimeout(() => location.reload(), 1500);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).forEach(el => new bootstrap.Tooltip(el));
        });
    </script>
@endsection
