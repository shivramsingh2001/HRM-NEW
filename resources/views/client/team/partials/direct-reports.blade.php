{{--
    Direct-reports panel shown on a manager's /team/member-detail/{id} page:
    basic details + today's attendance status/time for everyone reporting to
    this employee. Clicking a row re-opens this same page for that employee.
    Expects: $directReports (Collection of stdClass, from
    TeamController::getTeamMembers($authUser, today, $managerId)).
--}}
<x-ui.card>
    @if ($directReports->isEmpty())
        <div class="text-center text-muted py-4 fs-12">
            <i class="feather-users d-block mb-2" style="font-size: 22px;"></i>
            No one currently reports to this employee.
        </div>
    @else
        <div class="row g-2">
            @foreach ($directReports as $report)
                @php
                    $statusLower = strtolower($report->status ?? 'absent');
                    $badgeClass = match (true) {
                        in_array($statusLower, ['present', 'halfday', 'checked in only']) => 'badge-present',
                        str_contains($statusLower, 'leave') => 'badge-leave',
                        $statusLower === 'holiday' => 'badge-holiday',
                        $statusLower === 'week off' => 'badge-week-off',
                        default => 'badge-absent',
                    };
                @endphp
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('team.member-detail', encrypt($report->id)) }}"
                        class="text-decoration-none d-block report-row-card">
                        <div class="d-flex align-items-center gap-2 p-2">
                            <img src="{{ $report->profile_image ? asset($report->profile_image) : asset('/profile2.jpg') }}"
                                alt="{{ $report->name }}" class="report-row-avatar">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold fs-12 text-truncate" style="color: #1a2236;">
                                    {{ $report->name }}
                                </div>
                                <div class="text-muted fs-11 text-truncate">
                                    {{ $report->designation ?? '-' }}
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <span class="badge-status {{ $badgeClass }}">{{ $report->status }}</span>
                                    <span class="text-muted fs-11">
                                        <i class="feather-log-in fs-10"></i>
                                        {{ $report->punch_in ? \Carbon\Carbon::parse($report->punch_in)->format('h:i A') : '--:--' }}
                                        <i class="feather-log-out fs-10 ms-1"></i>
                                        {{ $report->punch_out ? \Carbon\Carbon::parse($report->punch_out)->format('h:i A') : '--:--' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</x-ui.card>

<style>
    .report-row-card {
        border: 1px solid var(--border);
        border-radius: 8px;
        transition: all 0.15s ease;
    }

    .report-row-card:hover {
        border-color: var(--primary-mid);
        background: var(--primary-light);
    }

    .report-row-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
</style>
