{{-- Employee 360 — performance (EmployeeProfileController::tabPerformance) --}}
@php $month = fn ($m) => \Carbon\Carbon::parse(strlen((string) $m) === 7 ? $m . '-01' : $m)->format('M Y'); @endphp

<h5 class="section-title"><i class="feather-trending-up"></i> Monthly scores</h5>
@if ($scores->isEmpty())
    <p class="p360-note">No performance score has been calculated yet.</p>
@else
    <div class="table-responsive">
        <table class="p360-table">
            <thead><tr><th>Month</th><th>Overall</th><th>Grade</th><th>Attendance</th><th>Tasks</th><th>On time</th><th>Regularization</th><th>Manager</th></tr></thead>
            <tbody>
                @foreach ($scores as $s)
                    <tr>
                        <td>{{ $month($s->reporting_month) }}</td>
                        <td><strong>{{ round((float) $s->overall_score, 1) }}</strong></td>
                        <td>{{ $s->grade ?: '—' }}</td>
                        <td>{{ round((float) $s->attendance_score, 1) }}</td>
                        <td>{{ round((float) $s->task_completion_score, 1) }}</td>
                        <td>{{ round((float) $s->deadline_met_score, 1) }}</td>
                        <td>{{ round((float) $s->regularization_score, 1) }}</td>
                        <td>{{ $s->manager_rating_included ? round((float) $s->manager_rating_score, 1) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="p360-sub">Manager reviews</div>
@if ($reviews->isEmpty())
    <p class="p360-note">No reviews yet.</p>
@else
    @foreach ($reviews as $r)
        <div class="p-3 mb-2" style="border:1px solid #edf2f7;border-radius:10px;">
            <div class="d-flex justify-content-between">
                <strong style="font-size:13px">{{ $month($r->review_month) }} · rating {{ $r->overall_rating ?? '—' }}</strong>
                <x-ui.status-badge :status="$r->status" />
            </div>
            <div class="p360-note">By {{ $r->reviewer_name ?? '—' }}</div>
            @if ($r->strengths)<div style="font-size:12px" class="mt-1"><strong>Strengths:</strong> {{ $r->strengths }}</div>@endif
            @if ($r->areas_for_improvement)<div style="font-size:12px"><strong>To improve:</strong> {{ $r->areas_for_improvement }}</div>@endif
        </div>
    @endforeach
@endif
