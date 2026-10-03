@extends('client.layout.master')

@section('style')
    <style>
        .bcast-notif-item { display: flex; gap: 12px; padding: 14px 16px; border-bottom: 1px solid #f1f5f9; }
        .bcast-notif-item.unread { background: #f8fafc; }
        .bcast-notif-icon { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color: #fff; display: flex; align-items: center; justify-content: center; flex: none; }
        .bcast-notif-title { font-weight: 600; font-size: 13px; color: #1a2236; }
        .bcast-notif-message { font-size: 12px; color: #6b7385; margin-top: 2px; }
        .bcast-notif-time { font-size: 10.5px; color: #9aa1b1; margin-top: 4px; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="My Notifications">
        <x-slot:actions>
            <button type="button" class="btn btn-sm btn-light-brand" id="bcastMarkAllReadPage">
                <i class="feather-check me-1"></i> Mark all read
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 18px !important;">
        <div class="card">
            <div class="card-body p-0">
                @forelse ($recipients as $r)
                    <div class="bcast-notif-item {{ $r->read_at ? '' : 'unread' }}">
                        <div class="bcast-notif-icon"><i class="feather-bell"></i></div>
                        <div class="flex-grow-1">
                            <div class="bcast-notif-title">{{ $r->broadcast->title ?? 'Notification' }}</div>
                            <div class="bcast-notif-message">{{ $r->broadcast->body ?? '' }}</div>
                            @if (! empty($r->broadcast?->action_url))
                                <a href="{{ $r->broadcast->action_url }}" target="_blank" class="fs-11">{{ $r->broadcast->action_label ?: 'View' }}</a>
                            @endif
                            <div class="bcast-notif-time">{{ $r->created_at->diffForHumans() }}</div>
                        </div>
                        @unless ($r->read_at)
                            <button type="button" class="btn btn-sm btn-light-brand bcast-mark-read" data-id="{{ $r->id }}" style="height: 28px;">Mark read</button>
                        @endunless
                    </div>
                @empty
                    <div class="text-center py-5 text-muted">No notifications yet.</div>
                @endforelse
            </div>
        </div>

        <div class="mt-3">
            {{ $recipients->links() }}
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(document).on('click', '.bcast-mark-read', function () {
            const btn = $(this);
            const id = btn.data('id');
            $.post("{{ url('broadcast/notifications') }}/" + id + "/read", { _token: '{{ csrf_token() }}' })
                .done(function () {
                    btn.closest('.bcast-notif-item').removeClass('unread');
                    btn.remove();
                });
        });

        $('#bcastMarkAllReadPage').on('click', function () {
            $.post("{{ route('broadcast.notifications.read-all') }}", { _token: '{{ csrf_token() }}' })
                .done(function () { location.reload(); });
        });
    </script>
@endsection
