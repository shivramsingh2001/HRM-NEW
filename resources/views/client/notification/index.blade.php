@extends('client.layout.master')

@section('title', 'Notifications')

@section('style')
<style>
    .notif-tabs { display: flex; gap: 6px; margin-bottom: 12px; }
    .notif-tabs a { font-size: 11px; font-weight: 600; padding: 6px 14px; border-radius: 20px; text-decoration: none; color: #475569; background: #f4f6fb; border: 1px solid #dfe5f0; }
    .notif-tabs a.active { background: #0D6EFD; color: #fff; border-color: #0D6EFD; }
    .notif-row { display: flex; gap: 12px; padding: 10px 16px; border-bottom: 1px solid #f1f5f9; cursor: default; }
    .notif-row.unread { background: #eff6ff; cursor: pointer; }
    .notif-row .dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 6px; flex: none; background: transparent; }
    .notif-row.unread .dot { background: #0D6EFD; }
    .notif-row .title { font-size: 12px; font-weight: 600; color: #1a2236; }
    .notif-row .message { font-size: 11.5px; color: #475569; margin-top: 2px; }
    .notif-row .meta { font-size: 10px; color: #94a3b8; margin-top: 3px; }
</style>
@endsection

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Notifications">
        <x-slot:actions>
            @if ($unreadCount > 0)
                <button type="button" class="btn btn-sm btn-primary" id="markAllReadBtn">
                    <i class="feather-check-circle me-1"></i> Mark all read ({{ $unreadCount }})
                </button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body" style="padding: 20px !important;">
        <div class="notif-tabs">
            <a href="{{ route('notifications.page') }}" class="{{ $status === null ? 'active' : '' }}">All</a>
            <a href="{{ route('notifications.page', ['status' => 'unread']) }}" class="{{ $status === 'unread' ? 'active' : '' }}">Unread ({{ $unreadCount }})</a>
            <a href="{{ route('notifications.page', ['status' => 'read']) }}" class="{{ $status === 'read' ? 'active' : '' }}">Read</a>
        </div>

        <div class="card stretch stretch-full">
            <div class="card-body p-0">
                @forelse ($notifications as $n)
                    <div class="notif-row {{ $n['is_read'] ? '' : 'unread' }}" data-id="{{ $n['id'] }}">
                        <span class="dot"></span>
                        <div>
                            <div class="title">{{ $n['title'] }}</div>
                            @if ($n['message'] !== '' && $n['message'] !== $n['title'])
                                <div class="message">{{ $n['message'] }}</div>
                            @endif
                            <div class="meta">{{ $n['created_at'] }} · {{ $n['created_at_human'] }}{{ $n['is_broadcast'] ? ' · Broadcast' : '' }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5" style="font-size:12px;">
                        <i class="feather-bell-off d-block mb-2" style="font-size:28px;"></i>
                        No notifications here.
                    </div>
                @endforelse
            </div>
            <x-ui.pagination-footer :paginator="$notifications" label="notifications" />
        </div>
    </div>
@endsection

@section('script-area')
<script>
    (function () {
        const csrf = "{{ csrf_token() }}";
        const base = "{{ url('notifications') }}";

        document.querySelectorAll('.notif-row.unread').forEach(el => {
            el.addEventListener('click', function () {
                fetch(base + '/' + encodeURIComponent(this.dataset.id) + '/read', {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                }).then(() => this.classList.remove('unread'));
            }, { once: true });
        });

        document.getElementById('markAllReadBtn')?.addEventListener('click', function () {
            fetch("{{ route('notifications.read-all') }}", {
                method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            }).then(() => window.location.reload());
        });
    })();
</script>
@endsection
