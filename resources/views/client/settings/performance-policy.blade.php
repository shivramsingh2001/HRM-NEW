@extends('client.layout.master')

@section('style')
    <style>
        .policy-container { max-width: 820px; margin: 0 auto; }
        .policy-card { background: #fff; border: 1px solid #edf2f7; border-radius: 16px; overflow: hidden; margin-bottom: 20px; }
        .policy-header { padding: 18px 22px; border-bottom: 1px solid #edf2f7; background: #fafbfc; }
        .policy-header h5 { margin: 0; font-size: 14px; font-weight: 700; color: var(--text-primary); }
        .policy-header p { margin: 4px 0 0; font-size: 11.5px; color: #64748b; }
        .policy-body { padding: 20px; }
        .policy-body .form-label { font-size: 12px; font-weight: 600; }
        .policy-body .form-text { font-size: 11px; color: #64748b; }
        .weight-total-badge { font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Performance Policy" />

    <div class="page-content">
        <div class="container-fluid">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="policy-container">
                <form action="{{ route('performance-policy.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="policy-card">
                        <div class="policy-header">
                            <h5>Effective date</h5>
                            <p>Saving creates a new policy version. It applies to every day calculated on or
                               after this date; already-calculated days keep the version they were scored under.</p>
                        </div>
                        <div class="policy-body">
                            <input type="date" class="form-control" name="effective_from" style="max-width: 220px"
                                   value="{{ old('effective_from', now()->format('Y-m-d')) }}">
                        </div>
                    </div>

                    <div class="policy-card">
                        <div class="policy-header">
                            <h5>Scoring weights</h5>
                            <p>How much each criterion contributes to a day's overall score. Manager rating
                               only applies to the monthly blend, not the daily score. Must sum to 100.</p>
                        </div>
                        <div class="policy-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Attendance</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control weight-input"
                                           name="weight_attendance" value="{{ old('weight_attendance', $policy->weightAttendance) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Task Completion</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control weight-input"
                                           name="weight_task_completion" value="{{ old('weight_task_completion', $policy->weightTaskCompletion) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Task On-Time</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control weight-input"
                                           name="weight_task_ontime" value="{{ old('weight_task_ontime', $policy->weightTaskOntime) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Project Participation</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control weight-input"
                                           name="weight_project_participation" value="{{ old('weight_project_participation', $policy->weightProjectParticipation) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Regularization</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control weight-input"
                                           name="weight_regularization" value="{{ old('weight_regularization', $policy->weightRegularization) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Manager Rating</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control weight-input"
                                           name="weight_manager_rating" value="{{ old('weight_manager_rating', $policy->weightManagerRating) }}">
                                </div>
                            </div>
                            <div class="mt-3">
                                Total: <span id="weightTotal" class="weight-total-badge">100</span>
                            </div>
                            <div class="form-text mt-1">
                                Missing data (e.g. no tasks assigned that day) is excluded, not scored zero &mdash;
                                the remaining weights are automatically renormalized to 100% for that day.
                            </div>
                        </div>
                    </div>

                    <div class="policy-card">
                        <div class="policy-header">
                            <h5>Late arrival</h5>
                        </div>
                        <div class="policy-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Grace period (minutes)</label>
                                    <input type="number" min="0" max="240" class="form-control"
                                           name="late_grace_minutes" value="{{ old('late_grace_minutes', $policy->lateGraceMinutes) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Penalty per incident (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                           name="late_penalty_per_incident" value="{{ old('late_penalty_per_incident', $policy->latePenaltyPerIncident) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Penalty cap (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                           name="late_penalty_cap" value="{{ old('late_penalty_cap', $policy->latePenaltyCap) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="policy-card">
                        <div class="policy-header">
                            <h5>Early departure</h5>
                        </div>
                        <div class="policy-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Grace period (minutes)</label>
                                    <input type="number" min="0" max="240" class="form-control"
                                           name="early_departure_grace_minutes" value="{{ old('early_departure_grace_minutes', $policy->earlyDepartureGraceMinutes) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Penalty per incident (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                           name="early_departure_penalty_per_incident" value="{{ old('early_departure_penalty_per_incident', $policy->earlyDeparturePenaltyPerIncident) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Penalty cap (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                           name="early_departure_penalty_cap" value="{{ old('early_departure_penalty_cap', $policy->earlyDeparturePenaltyCap) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="policy-card">
                        <div class="policy-header">
                            <h5>Attendance regularization</h5>
                            <p>Penalty applied to a day's regularization score, differing by the request's outcome.</p>
                        </div>
                        <div class="policy-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Approved (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                           name="regularization_penalty_approved" value="{{ old('regularization_penalty_approved', $policy->regularizationPenaltyApproved) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Rejected (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                           name="regularization_penalty_rejected" value="{{ old('regularization_penalty_rejected', $policy->regularizationPenaltyRejected) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Pending (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                           name="regularization_penalty_pending" value="{{ old('regularization_penalty_pending', $policy->regularizationPenaltyPending) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Cap (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                           name="regularization_penalty_cap" value="{{ old('regularization_penalty_cap', $policy->regularizationPenaltyCap) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="policy-card">
                        <div class="policy-header">
                            <h5>Tasks</h5>
                        </div>
                        <div class="policy-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Overdue penalty per task (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                           name="task_overdue_penalty_per_task" value="{{ old('task_overdue_penalty_per_task', $policy->taskOverduePenaltyPerTask) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Overdue penalty cap (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                           name="task_overdue_penalty_cap" value="{{ old('task_overdue_penalty_cap', $policy->taskOverduePenaltyCap) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Min tasks required for task score</label>
                                    <input type="number" min="0" max="50" class="form-control"
                                           name="min_tasks_for_task_score" value="{{ old('min_tasks_for_task_score', $policy->minTasksForTaskScore) }}">
                                    <div class="form-text">Below this, the day's task score is excluded rather than scored.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Policy Version</button>
                </form>

                @if (($history ?? collect())->isNotEmpty())
                    <div class="policy-card mt-4">
                        <div class="policy-header"><h5>Previous versions</h5></div>
                        <div class="policy-body">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0" style="font-size: 11.5px;">
                                    <thead>
                                        <tr>
                                            <th>Effective from</th><th>Attendance</th><th>Task</th><th>On-Time</th>
                                            <th>Project</th><th>Regularization</th><th>Manager</th><th>Saved</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($history as $h)
                                            <tr>
                                                <td>{{ \Carbon\Carbon::parse($h->effective_from)->format('d M Y') }}</td>
                                                <td>{{ $h->weight_attendance }}%</td>
                                                <td>{{ $h->weight_task_completion }}%</td>
                                                <td>{{ $h->weight_task_ontime }}%</td>
                                                <td>{{ $h->weight_project_participation }}%</td>
                                                <td>{{ $h->weight_regularization }}%</td>
                                                <td>{{ $h->weight_manager_rating }}%</td>
                                                <td>{{ $h->created_at?->format('d M Y') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                <p class="text-muted" style="font-size: 12px">
                    Changes apply from the next nightly <code>performance:calculate-daily</code> run
                    (02:30) and are rolled up into the monthly score by <code>performance:rollup-monthly</code> (03:00).
                </p>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(function () {
            function recalcTotal() {
                let total = 0;
                $('.weight-input').each(function () { total += parseFloat($(this).val()) || 0; });
                total = Math.round(total * 100) / 100;
                const $badge = $('#weightTotal').text(total);
                const ok = Math.abs(total - 100) < 0.5;
                $badge.css({ background: ok ? '#d1fae5' : '#fee2e2', color: ok ? '#065f46' : '#991b1b' });
            }
            $('.weight-input').on('input', recalcTotal);
            recalcTotal();
        });
    </script>
@endsection
