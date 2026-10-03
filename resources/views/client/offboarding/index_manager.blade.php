@extends('client.layout.master')

@section('style')
    <style>
        .ob-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; margin-bottom: 14px; }
        .ob-filters .field-input, .ob-filters .field-select { font-size: 12px; padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 7px; }
        .ob-btn { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color: #fff; border: none; font-size: 12.5px; font-weight: 500; padding: 7px 16px; border-radius: 8px; text-decoration: none; }
        .ob-btn:hover { filter: brightness(0.9); color: #fff; }
        .ob-table-card { background: #fff; border: 1px solid #edf2f7; border-radius: 12px; padding: 4px 14px; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Team Offboarding" />

    <div class="main-content" style="padding: 20px !important;">

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3"><x-ui.stat-card icon="users" label="Total" value="{{ $stats['total'] }}" /></div>
            <div class="col-6 col-md-3"><x-ui.stat-card icon="clock" label="Pending Approval" value="{{ $stats['pending_approval'] }}" /></div>
            <div class="col-6 col-md-3"><x-ui.stat-card icon="loader" label="In Progress" value="{{ $stats['in_progress'] }}" /></div>
            <div class="col-6 col-md-3"><x-ui.stat-card icon="x-circle" label="Rejected" value="{{ $stats['rejected'] }}" /></div>
        </div>

        <x-ui.filter-card title="Filter Requests">
<form method="GET" class="ob-filters">
            <div>
                <select name="employee_id" class="field-select" onchange="this.form.submit()">
                    <option value="">All team members</option>
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>{{ $emp->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <input type="text" name="search" class="field-input" placeholder="Search name / ID" value="{{ request('search') }}">
            </div>
            <div>
                <button type="submit" class="ob-btn"><i class="feather-search"></i> Filter</button>
            </div>
        </form>
</x-ui.filter-card>

        <div class="ob-table-card">
            @if ($offboardings->isEmpty())
                <x-ui.empty-state icon="user-minus" title="No offboarding requests" subtitle="Requests from your direct reports will show up here." />
            @else
                <x-ui.data-table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Reason</th>
                            <th>Last Working Date</th>
                            <th>Status</th>
                            <th>Stage</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($offboardings as $ob)
                            <tr>
                                <td>{{ $ob->employee?->name }}</td>
                                <td>{{ $ob->reason_label }}</td>
                                <td>{{ optional($ob->last_working_date)->format('d M Y') }}</td>
                                <td><x-ui.status-badge :status="$ob->badge_status" :label="$ob->status_label" /></td>
                                <td><x-ui.status-badge :status="$ob->badge_status" :label="$ob->stage_label" /></td>
                                <td><a href="{{ route('offboarding.show', $ob->id) }}" class="ob-btn">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.data-table>
                <div class="p-3">{{ $offboardings->links() }}</div>
            @endif
        </div>

    </div>
@endsection
