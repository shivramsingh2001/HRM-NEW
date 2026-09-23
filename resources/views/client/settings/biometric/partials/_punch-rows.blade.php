@foreach ($punches as $p)
    <tr>
        <td>{{ $loop->iteration }}</td>
        <td>{{ $p->punched_at?->format('d M H:i:s') }}</td>
        <td>{{ $p->device?->name ?? $p->serial_number }}</td>
        <td><code>{{ $p->enroll_no }}</code></td>
        <td>{{ $p->user?->name ?? '—' }}</td>
        <td>{{ $p->direction }}</td>
        <td>{{ $p->method ?? '—' }}</td>
        <td><x-ui.status-badge :status="$p->status" /></td>
        <td style="font-size:11px">{{ $p->error }}</td>
        <td class="text-center">
            @if (in_array($p->status, ['pending','error','skipped']))
                <form method="POST" action="{{ route('settings.biometric.punches.reprocess', $p) }}">
                    @csrf <button type="submit" class="action-btn" title="Reprocess" data-bs-toggle="tooltip"><i class="feather-refresh-cw"></i></button>
                </form>
            @endif
        </td>
    </tr>
@endforeach
