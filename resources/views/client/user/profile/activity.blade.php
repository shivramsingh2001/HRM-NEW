{{-- Employee 360 — recent activity (EmployeeProfileController::tabActivity) --}}
<h5 class="section-title"><i class="feather-activity"></i> Recent activity</h5>
@if ($events->isEmpty())
    <p class="p360-note">No activity recorded yet.</p>
@else
    <table class="p360-table">
        <thead><tr><th>When</th><th>What</th><th>By</th></tr></thead>
        <tbody>
            @foreach ($events as $e)
                <tr>
                    <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($e['at'])->format('d M Y, h:i A') }}</td>
                    <td><span class="p360-chip {{ $e['kind'] === 'attendance' ? 'muted' : '' }}">{{ ucfirst($e['kind']) }}</span> {{ ucfirst($e['what']) }}</td>
                    <td>{{ $e['by'] ?? 'System' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
