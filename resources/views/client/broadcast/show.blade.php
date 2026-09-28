@extends('client.layout.master')

@section('style')
    <style>
        .bcast-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 16px; }
        .bcast-detail-card { background: #fff; border: 1px solid #eaeef5; border-radius: 12px; padding: 20px; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Broadcast Details" :parent="['label' => 'Broadcast History', 'route' => 'broadcast.index']">
        <x-slot:actions>
            @if (in_array($broadcast->status, ['draft', 'scheduled']))
                <button type="button" class="btn btn-sm btn-danger" id="bcastCancelBtn" data-id="{{ $broadcast->id }}">
                    <i class="feather-x-circle me-1"></i> Cancel
                </button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 18px !important;">
        <div class="bcast-stat-grid">
            <div class="kpi5-card">
                <div class="kpi5-top"><span class="kpi5-icon"><i class="fas fa-users"></i></span></div>
                <div class="kpi5-value">{{ $stats['total'] }}</div>
                <div class="kpi5-label">Total Recipients</div>
            </div>
            <div class="kpi5-card">
                <div class="kpi5-top"><span class="kpi5-icon"><i class="fas fa-check-circle"></i></span></div>
                <div class="kpi5-value">{{ $stats['delivered'] }}</div>
                <div class="kpi5-label">Delivered</div>
            </div>
            <div class="kpi5-card">
                <div class="kpi5-top"><span class="kpi5-icon"><i class="fas fa-envelope-open"></i></span></div>
                <div class="kpi5-value">{{ $stats['read'] }}</div>
                <div class="kpi5-label">Read</div>
            </div>
            <div class="kpi5-card">
                <div class="kpi5-top"><span class="kpi5-icon"><i class="fas fa-mouse-pointer"></i></span></div>
                <div class="kpi5-value">{{ $stats['clicked'] }}</div>
                <div class="kpi5-label">Action Clicked</div>
            </div>
        </div>

        <div class="bcast-detail-card">
            <h5 class="mb-1">{{ $broadcast->title }}</h5>
            <p class="text-muted mb-3">{{ $broadcast->body }}</p>

            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted" style="font-size: 11px;">Status</div>
                    <div class="fw-semibold text-capitalize">{{ $broadcast->status }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted" style="font-size: 11px;">Priority</div>
                    <div class="fw-semibold text-capitalize">{{ $broadcast->priority }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted" style="font-size: 11px;">Scheduled</div>
                    <div class="fw-semibold">{{ optional($broadcast->scheduled_at)->format('d M Y, h:i A') ?? '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted" style="font-size: 11px;">Sent</div>
                    <div class="fw-semibold">{{ optional($broadcast->sent_at)->format('d M Y, h:i A') ?? '—' }}</div>
                </div>
                @if ($broadcast->action_url)
                    <div class="col-md-6">
                        <div class="text-muted" style="font-size: 11px;">Action link</div>
                        <div class="fw-semibold"><a href="{{ $broadcast->action_url }}" target="_blank">{{ $broadcast->action_label ?: $broadcast->action_url }}</a></div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $('#bcastCancelBtn').on('click', function () {
            if (! confirm('Cancel this broadcast?')) return;
            const id = $(this).data('id');
            $.post("{{ url('broadcast') }}/" + id + "/cancel", { _token: '{{ csrf_token() }}' })
                .done(function (res) {
                    if (res.success) {
                        toastr.success(res.message);
                        setTimeout(function () { location.reload(); }, 800);
                    } else {
                        toastr.error(res.message);
                    }
                });
        });
    </script>
@endsection
