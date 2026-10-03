{{-- Employee 360 — tasks & projects (EmployeeProfileController::tabTasks) --}}
<h5 class="section-title"><i class="feather-check-square"></i> Tasks</h5>
@if ($tasks->isEmpty())
    <p class="p360-note">No tasks assigned.</p>
@else
    <div class="table-responsive">
        <table class="p360-table">
            <thead><tr><th>Task</th><th>Project</th><th>Priority</th><th>Deadline</th><th>Progress</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($tasks as $t)
                    @php $overdue = $t->deadline_date && \Carbon\Carbon::parse($t->deadline_date)->lt(today()) && !in_array($t->status, ['completed', 'approved', 'cancelled'], true); @endphp
                    <tr>
                        <td><a href="{{ route('task.view-detail', $t->id) }}">{{ $t->task_code ? $t->task_code . ' · ' : '' }}{{ $t->title }}</a></td>
                        <td>{{ $t->project_name ?? '—' }}</td>
                        <td>{{ ucfirst((string) $t->priority) ?: '—' }}</td>
                        <td>
                            {{ $t->deadline_date ? \Carbon\Carbon::parse($t->deadline_date)->format('d M Y') : '—' }}
                            @if ($overdue)<span class="p360-chip extra">Overdue</span>@endif
                        </td>
                        <td>{{ (int) $t->progress_percentage }}%</td>
                        <td><x-ui.status-badge :status="$t->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($canProjects)
    <div class="p360-sub">Projects</div>
    @if ($projects->isEmpty())
        <p class="p360-note">Not part of any project.</p>
    @else
        <table class="p360-table">
            <thead><tr><th>Project</th><th>Role</th><th>Deadline</th><th>Progress</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($projects as $p)
                    <tr>
                        <td><a href="{{ route('project.view-details', ['id' => encrypt($p->id)]) }}">{{ $p->project_code ? $p->project_code . ' · ' : '' }}{{ $p->name }}</a></td>
                        <td>{{ $p->is_head ? 'Head' : 'Member' }}</td>
                        <td>{{ $p->deadline_date ? \Carbon\Carbon::parse($p->deadline_date)->format('d M Y') : '—' }}</td>
                        <td>{{ (int) $p->progress_percentage }}%</td>
                        <td><x-ui.status-badge :status="$p->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif
