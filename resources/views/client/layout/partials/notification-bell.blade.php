{{-- Broadcast notification bell — every authenticated user, regardless of
     the `broadcasts` composer permission (that gate is only on sending). --}}
<div class="dropdown nxl-h-item" id="broadcastBellWrapper">
    <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" data-bs-auto-close="outside"
        id="broadcastBellToggle">
        <i class="feather-bell"></i>
        <span class="badge bg-danger rounded-pill nxl-h-badge" id="broadcastUnreadBadge" style="display: none;">0</span>
    </a>
    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown" style="width: 340px; max-height: 420px; overflow-y: auto;">
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <h6 class="mb-0 fs-13 fw-bold">Notifications</h6>
            <a href="javascript:void(0);" class="fs-11 text-primary" id="broadcastMarkAllRead">Mark all read</a>
        </div>
        <div id="broadcastBellList">
            <div class="text-center text-muted py-4 fs-12">Loading…</div>
        </div>
        <div class="text-center border-top">
            <a href="{{ route('broadcast.notifications.index') }}" class="d-block py-2 fs-12 text-primary">View all</a>
        </div>
    </div>
</div>

<style>
    .nxl-h-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        font-size: 9px;
        min-width: 16px;
        line-height: 1.3;
        padding: 1px 4px;
    }
    #broadcastBellToggle { position: relative; }
    .bcast-bell-item { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; display: block; text-decoration: none; color: inherit; }
    .bcast-bell-item:hover { background: #f8fafc; }
    .bcast-bell-item.unread { background: #eff6ff; }
    .bcast-bell-item .title { font-size: 12px; font-weight: 600; color: #1a2236; margin-bottom: 2px; }
    .bcast-bell-item .message { font-size: 11px; color: #6b7385; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .bcast-bell-item .time { font-size: 10px; color: #9aa1b1; margin-top: 2px; }
</style>

<script>
    (function () {
        const unreadUrl = "{{ route('broadcast.notifications.unread-count') }}";
        const listUrl = "{{ route('broadcast.notifications.index') }}";
        const readUrlBase = "{{ url('broadcast/notifications') }}";
        const markAllUrl = "{{ route('broadcast.notifications.read-all') }}";
        const csrf = "{{ csrf_token() }}";

        function refreshUnreadCount() {
            fetch(unreadUrl, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    const badge = document.getElementById('broadcastUnreadBadge');
                    if (data.unread_count > 0) {
                        badge.style.display = 'inline-block';
                        badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                    } else {
                        badge.style.display = 'none';
                    }
                })
                .catch(() => {});
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        function loadList() {
            const list = document.getElementById('broadcastBellList');
            fetch(listUrl + '?per_page=8', { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(res => {
                    const items = (res.data && res.data.data) ? res.data.data : [];
                    if (!items.length) {
                        list.innerHTML = '<div class="text-center text-muted py-4 fs-12">No notifications yet</div>';
                        return;
                    }
                    list.innerHTML = items.map(n => `
                        <a href="javascript:void(0);" class="bcast-bell-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}">
                            <div class="title">${escapeHtml(n.title)}</div>
                            <div class="message">${escapeHtml(n.message)}</div>
                            <div class="time">${escapeHtml(n.created_at_human)}</div>
                        </a>
                    `).join('');

                    list.querySelectorAll('.bcast-bell-item').forEach(el => {
                        el.addEventListener('click', function () {
                            const id = this.getAttribute('data-id');
                            fetch(readUrlBase + '/' + id + '/read', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            }).then(() => {
                                this.classList.remove('unread');
                                refreshUnreadCount();
                            });
                        });
                    });
                })
                .catch(() => {
                    list.innerHTML = '<div class="text-center text-muted py-4 fs-12">Failed to load</div>';
                });
        }

        document.getElementById('broadcastBellToggle')?.addEventListener('click', loadList);

        document.getElementById('broadcastMarkAllRead')?.addEventListener('click', function () {
            fetch(markAllUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
                .then(() => { loadList(); refreshUnreadCount(); });
        });

        refreshUnreadCount();
        setInterval(refreshUnreadCount, 60000);
    })();
</script>
