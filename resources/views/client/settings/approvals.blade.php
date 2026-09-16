@extends('client.layout.master')

@section('style')
    <style>
        .wf-card { background:#fff; border:1px solid #edf2f7; border-radius:14px; margin-bottom:20px; }
        .wf-head { padding:16px 20px; border-bottom:1px solid #edf2f7; background:#fafbfc; }
        .wf-head h5 { margin:0; font-size:15px; font-weight:600; }
        .wf-body { padding:20px; }
        .step-row { display:grid; grid-template-columns:1.2fr 1.4fr .8fr .9fr 1fr auto; gap:8px; margin-bottom:8px; align-items:end; }
        .step-row .form-label { font-size:11px; margin-bottom:2px; }
        table.tbl { width:100%; font-size:12px; }
        table.tbl th, table.tbl td { padding:6px 8px; border-bottom:1px solid #eef2f7; text-align:left; }
    </style>
@endsection

@section('content-area')
<div class="page-content"><div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Approval Workflows</h4>
    </div>

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="wf-card">
        <div class="wf-head"><h5>Existing workflows</h5></div>
        <div class="wf-body">
            @forelse ($workflows as $wf)
                <div class="d-flex justify-content-between align-items-start mb-2 pb-2" style="border-bottom:1px solid #eef2f7">
                    <div>
                        <strong>{{ $wf->name }}</strong>
                        <span class="badge bg-light text-dark">{{ $wf->request_type }}</span>
                        <span class="badge {{ $wf->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $wf->is_active ? 'active' : 'inactive' }}</span>
                        <div class="text-muted" style="font-size:12px">
                            @foreach ($wf->steps as $s)
                                L{{ $s->level }}: {{ $s->approver_type }}{{ $s->approver_ref ? ' ('.$s->approver_ref.')' : '' }}
                                / {{ $s->quorum }}{{ $s->sla_hours ? ' / SLA '.$s->sla_hours.'h → '.$s->on_breach : '' }}
                                @if(!$loop->last) &nbsp;·&nbsp; @endif
                            @endforeach
                        </div>
                    </div>
                    <form method="POST" action="{{ route('settings.approvals.workflows.destroy', $wf->id) }}"
                          onsubmit="return confirm('Remove this workflow?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                </div>
            @empty
                <p class="text-muted mb-0">No workflows. Requests use the default single reporting-head approval.</p>
            @endforelse
        </div>
    </div>

    <div class="wf-card">
        <div class="wf-head"><h5>Add / replace a workflow</h5></div>
        <div class="wf-body">
            <form method="POST" action="{{ route('settings.approvals.workflows.store') }}">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label">Name</label>
                        <input class="form-control" name="name" required placeholder="e.g. Regularization — 2 level">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Request type</label>
                        <select class="form-control" name="request_type">
                            @foreach ($types as $t) <option value="{{ $t }}">{{ $t }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="wfActive">
                            <label class="form-check-label" for="wfActive">Active</label>
                        </div>
                    </div>
                </div>

                <label class="form-label">Steps (in order)</label>
                <div id="steps"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="addStep()">+ Add step</button>
                <div><button class="btn btn-primary">Save workflow</button></div>
            </form>
        </div>
    </div>

    <div class="wf-card">
        <div class="wf-head"><h5>Delegations (out-of-office)</h5></div>
        <div class="wf-body">
            <table class="tbl mb-3">
                <thead><tr><th>Delegator</th><th>Delegate</th><th>Types</th><th>Window</th><th></th></tr></thead>
                <tbody>
                @forelse ($delegations as $d)
                    <tr>
                        <td>{{ optional($users->firstWhere('id', $d->delegator_id))->name ?? $d->delegator_id }}</td>
                        <td>{{ optional($users->firstWhere('id', $d->delegate_id))->name ?? $d->delegate_id }}</td>
                        <td>{{ $d->request_types ? implode(', ', $d->request_types) : 'all' }}</td>
                        <td>{{ $d->starts_on?->format('d M') }} – {{ $d->ends_on?->format('d M Y') }}</td>
                        <td>
                            <form method="POST" action="{{ route('settings.approvals.delegations.destroy', $d->id) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">×</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">None.</td></tr>
                @endforelse
                </tbody>
            </table>

            <form method="POST" action="{{ route('settings.approvals.delegations.store') }}" class="row g-2">
                @csrf
                <div class="col-md-3">
                    <select class="form-control" name="delegator_id" required>
                        <option value="">Delegator…</option>
                        @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-control" name="delegate_id" required>
                        <option value="">Delegate…</option>
                        @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-2"><input type="date" class="form-control" name="starts_on" required></div>
                <div class="col-md-2"><input type="date" class="form-control" name="ends_on" required></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">Add</button></div>
            </form>
        </div>
    </div>
</div></div>
@endsection

@section('script-area')
<script>
    let stepIdx = 0;
    function addStep() {
        const i = stepIdx++;
        const html = `
        <div class="step-row">
            <div>
                <label class="form-label">Approver</label>
                <select class="form-control form-control-sm" name="steps[${i}][approver_type]">
                    <option value="reporting_head">Reporting head</option>
                    <option value="role">Role</option>
                    <option value="user">Specific user</option>
                    <option value="department_head">Department head</option>
                </select>
            </div>
            <div>
                <label class="form-label">Ref (role name / user id)</label>
                <input class="form-control form-control-sm" name="steps[${i}][approver_ref]" placeholder="hr / 42">
            </div>
            <div>
                <label class="form-label">Quorum</label>
                <select class="form-control form-control-sm" name="steps[${i}][quorum]">
                    <option value="any">any</option><option value="all">all</option>
                </select>
            </div>
            <div>
                <label class="form-label">SLA hrs</label>
                <input type="number" class="form-control form-control-sm" name="steps[${i}][sla_hours]" min="1">
            </div>
            <div>
                <label class="form-label">On breach</label>
                <select class="form-control form-control-sm" name="steps[${i}][on_breach]">
                    <option value="notify">notify</option>
                    <option value="escalate">escalate</option>
                    <option value="auto_approve">auto-approve</option>
                </select>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentElement.remove()">×</button>
        </div>`;
        document.getElementById('steps').insertAdjacentHTML('beforeend', html);
    }
    addStep();
</script>
@endsection
