{{-- Header notification bell — every notification the signed-in user has
     (leave, attendance, expense, asset, task, project, approvals, broadcasts…),
     read from Laravel's `notifications` table via NotificationBellController.
     Not tied to the broadcast feature. --}}
<div class="dropdown nxl-h-item" id="notifBellWrapper">
    <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" data-bs-auto-close="outside"
        id="notifBellToggle" aria-label="Notifications">
        <i class="feather-bell"></i>
        <span class="badge bg-danger rounded-pill nxl-h-badge" id="notifUnreadBadge" style="display: none;">0</span>
    </a>
    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown notif-dropdown">
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <h6 class="mb-0 fs-13 fw-bold">Notifications <span class="text-muted fw-normal fs-11" id="notifUnreadText"></span></h6>
            <a href="javascript:void(0);" class="fs-11 text-primary" id="notifMarkAllRead">Mark all read</a>
        </div>
        <div id="notifBellList">
            <div class="text-center text-muted py-4 fs-12">Loading…</div>
        </div>
        <div class="text-center border-top">
            <a href="{{ route('notifications.page') }}" class="d-block py-2 fs-12 text-primary">View all</a>
        </div>
    </div>
</div>

<style>
    .nxl-h-badge { position: absolute; top: 2px; right: 2px; font-size: 9px; min-width: 16px; line-height: 1.3; padding: 1px 4px; }
    #notifBellToggle { position: relative; }
    .notif-dropdown { width: 340px; padding: 0; }
    #notifBellList { max-height: 380px; overflow-y: auto; }
    .notif-item { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; display: block; text-decoration: none; color: inherit; cursor: pointer; }
    .notif-item:hover { background: #f8fafc; }
    .notif-item.unread { background: #eff6ff; }
    .notif-item .title { font-size: 12px; font-weight: 600; color: #1a2236; margin-bottom: 2px; }
    .notif-item .message { font-size: 11px; color: #6b7385; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .notif-item .time { font-size: 10px; color: #9aa1b1; margin-top: 2px; }
</style>

<script>
    (function () {
        const latestUrl = "{{ route('notifications.latest') }}";
        const countUrl = "{{ route('notifications.unread-count') }}";
        const readUrlBase = "{{ url('notifications') }}";
        const markAllUrl = "{{ route('notifications.read-all') }}";
        const csrf = "{{ csrf_token() }}";

        function setCount(n) {
            const badge = document.getElementById('notifUnreadBadge');
            const text = document.getElementById('notifUnreadText');
            if (n > 0) {
                badge.style.display = 'inline-block';
                badge.textContent = n > 99 ? '99+' : n;
                text.textContent = '(' + n + ' unread)';
            } else {
                badge.style.display = 'none';
                text.textContent = '';
            }
        }

        function refreshUnreadCount() {
            fetch(countUrl, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(d => setCount(d.unread_count || 0))
                .catch(() => {});
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        function loadList() {
            const list = document.getElementById('notifBellList');
            fetch(latestUrl + '?limit=10', { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(res => {
                    setCount(res.unread_count || 0);
                    const items = res.data || [];
                    if (!items.length) {
                        list.innerHTML = '<div class="text-center text-muted py-4 fs-12">No notifications yet</div>';
                        return;
                    }
                    list.innerHTML = items.map(n => `
                        <div class="notif-item ${n.is_read ? '' : 'unread'}" data-id="${escapeHtml(n.id)}">
                            <div class="title">${escapeHtml(n.title)}</div>
                            <div class="message">${escapeHtml(n.message)}</div>
                            <div class="time">${escapeHtml(n.created_at_human)}</div>
                        </div>
                    `).join('');

                    list.querySelectorAll('.notif-item.unread').forEach(el => {
                        el.addEventListener('click', function () {
                            fetch(readUrlBase + '/' + encodeURIComponent(this.dataset.id) + '/read', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            }).then(() => {
                                this.classList.remove('unread');
                                refreshUnreadCount();
                            });
                        }, { once: true });
                    });
                })
                .catch(() => {
                    list.innerHTML = '<div class="text-center text-muted py-4 fs-12">Failed to load</div>';
                });
        }

        document.getElementById('notifBellToggle')?.addEventListener('click', loadList);

        document.getElementById('notifMarkAllRead')?.addEventListener('click', function () {
            fetch(markAllUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
                .then(() => loadList());
        });

        refreshUnreadCount();
        setInterval(refreshUnreadCount, 60000);
    })();
</script>
