@php($status = app(\App\Services\SubscriptionStatusService::class)->forTenant(app('current_tenant')->id ?? 0))
@if($status)
<div style="position:sticky;top:0;z-index:1999;background:{{ $status['state']==='expired' ? '#991b1b' : '#b45309' }};color:#fff;padding:8px 16px;font-size:13px;text-align:center">
    @if($status['state'] === 'expired')
        Your subscription ended on {{ $status['end']->format('d M Y') }} ({{ $status['days'] }} day(s) ago).
        Access will be suspended soon — renew now to avoid interruption.
    @else
        Your subscription ends in {{ $status['days'] }} day(s) ({{ $status['end']->format('d M Y') }}). Renew to avoid interruption.
    @endif
</div>
@endif
