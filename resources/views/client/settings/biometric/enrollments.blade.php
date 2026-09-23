@extends('client.layout.master')

@section('content-area')
    <x-ui.page-header title="Map Users — {{ $device->name }}"
        :parent="['label' => 'Biometric Terminals', 'route' => 'settings.biometric.index']">
        <x-slot:actions>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('settings.biometric.index') }}">
                <i class="feather-arrow-left"></i> Devices
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-warning">{{ session('error') }}</div> @endif

    <div class="s-card">
        <div class="s-head d-flex justify-content-between align-items-center">
            <h5>
                Roster
                <span class="muted" style="font-weight:400">
                    &nbsp;{{ $counts['synced'] }} on device · {{ $counts['pending'] }} to push ·
                    {{ $counts['removing'] }} to remove @if($counts['failed']) · <span style="color:#b42318">{{ $counts['failed'] }} failed</span> @endif
                </span>
            </h5>
            <form method="POST" action="{{ route('settings.biometric.roster.sync', $device) }}">
                @csrf
                <button class="btn btn-sm btn-primary"><i class="feather-refresh-cw"></i> Sync now</button>
            </form>
        </div>
        <div class="s-body">
            <form method="GET" action="{{ route('settings.biometric.enrollments', $device) }}" class="d-flex gap-2 mb-3" style="max-width:360px">
                <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm"
                       placeholder="Search employee, enroll no. or device user ID…">
                <button class="btn btn-sm btn-outline-primary text-nowrap"><i class="feather-search"></i></button>
                @if ($q !== '')
                    <a href="{{ route('settings.biometric.enrollments', $device) }}" class="btn btn-sm btn-outline-secondary" title="Clear search">×</a>
                @endif
            </form>

            @if ($device->auto_provision)
                <p class="muted" style="font-size:12px">
                    HRM is the source of truth. Every active employee
                    @if($device->provision_scope === 'branch')
                        in this device's branch
                    @endif
                    is created on the terminal automatically with their <strong>HRM user ID</strong> as the
                    device user ID — no mapping needed. Each person then just enrols their fingerprint on
                    the device against that ID. Removals happen when an employee is deactivated.
                </p>
            @else
                <div class="alert alert-warning" style="font-size:12px">
                    Auto-provision is <strong>off</strong> for this device — edit the device to enable it,
                    or use the manual mapping below.
                </div>
            @endif

            @if ($rows->isEmpty())
                <x-ui.empty-state icon="users" title="{{ $q !== '' ? 'No matches' : 'Nothing yet' }}"
                    subtitle="{{ $q !== '' ? 'No enrollments match your search.' : 'Click Sync now to build the roster from your employee list.' }}" />
            @else
                <div id="enrBulkBar" class="d-none align-items-center gap-2 px-3 py-2 mb-2"
                     style="background:var(--primary-light);border:1px solid #c7d2fe;border-radius:8px;font-size:13px;">
                    <span id="enrBulkCount" class="fw-semibold">0 selected</span>
                    <button type="button" class="btn btn-sm btn-primary" onclick="bulkEnrollmentAction('repush')">
                        <i class="feather-upload"></i> Re-push selected
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="bulkEnrollmentAction('remove')">
                        <i class="feather-x"></i> Remove selected
                    </button>
                    <button type="button" class="btn btn-sm btn-link" onclick="enrClearSelection()">Clear</button>
                </div>
                <x-ui.data-table>
                    <thead><tr>
                        <th style="width:28px"><input type="checkbox" id="enrSelectAll"></th>
                        <th width="40">#</th>
                        <th>Employee</th><th>Device user ID</th><th>State</th>
                        <th>Card no.</th><th>Name on device</th><th>Last synced</th><th class="text-center">Actions</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td><input type="checkbox" class="enr-row-sel" value="{{ $r->id }}"></td>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                @if ($r->user)
                                    {{ $r->user->name }} <span class="muted">({{ $r->user->employee_id }})</span>
                                @else
                                    <span class="muted">enroll {{ $r->enroll_no }} — unmapped</span>
                                @endif
                                @if ($r->source === 'manual') <span class="pill">manual</span> @endif
                            </td>
                            <td><code>{{ $r->device_user_id ?? $r->enroll_no }}</code></td>
                            <td>
                                <x-ui.status-badge :status="$r->sync_state" />
                                @if ($r->last_error) <div class="muted" style="max-width:220px">{{ $r->last_error }}</div> @endif
                            </td>
                            <td>
                                @if ($r->user)
                                    <form method="POST" action="{{ route('settings.biometric.enrollments.card', $r) }}" class="d-flex gap-1">
                                        @csrf
                                        <input class="form-control form-control-sm" name="card_number" value="{{ $r->user->card_number }}" placeholder="card #" style="width:110px">
                                        <button type="submit" class="action-btn" title="Save card number" data-bs-toggle="tooltip"><i class="feather-save"></i></button>
                                    </form>
                                    @if ($r->user->card_number && $r->card_pushed === $r->user->card_number)
                                        <span class="muted" style="font-size:11px">✓ on device</span>
                                    @elseif ($r->user->card_number)
                                        <span class="muted" style="font-size:11px">pending</span>
                                    @endif
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>{{ $r->name_on_device ?? $r->name_pushed ?? '—' }}</td>
                            <td class="muted">{{ optional($r->synced_at)->diffForHumans() ?? '—' }}</td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <form method="POST" action="{{ route('settings.biometric.enrollments.repush', $r) }}" class="d-inline">
                                        @csrf <button type="submit" class="action-btn" title="Re-push" data-bs-toggle="tooltip"><i class="feather-upload"></i></button>
                                    </form>
                                    <form method="POST" action="{{ route('settings.biometric.enrollments.remove', $r) }}" class="d-inline"
                                          onsubmit="return confirm('Remove this person from the device? They will need to re-enrol.')">
                                        @csrf <button type="submit" class="action-btn" title="Remove" data-bs-toggle="tooltip"><i class="feather-x"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </x-ui.data-table>
                <div class="mt-2">{{ $rows->links() }}</div>
            @endif
        </div>
    </div>

    <div class="s-card">
        <div class="s-head">
            <h5><a data-bs-toggle="collapse" href="#manualMap" role="button" class="text-decoration-none">Manual mapping (advanced) ▾</a></h5>
        </div>
        <div class="s-body collapse" id="manualMap">
            <p class="muted" style="font-size:12px">
                For odd enroll numbers that aren't an HRM user ID (e.g. a device that was already
                populated by hand). Mapped rows here are kept as <code>manual</code> and are never
                auto-removed. You can also <em>Auto-map by Employee ID</em>.
            </p>
            <form method="POST" action="{{ route('settings.biometric.enrollments.auto', $device) }}" class="mb-3">
                @csrf
                <button class="btn btn-sm btn-outline-primary"><i class="feather-zap"></i> Auto-map by Employee ID</button>
            </form>
            <form method="POST" action="{{ route('settings.biometric.enrollments.map', $device) }}" class="row g-2">
                @csrf
                <div class="col-md-3"><input class="form-control form-control-sm" name="enroll_no" placeholder="enroll no." required></div>
                <div class="col-md-4">
                    <select class="form-control form-control-sm" name="user_id" required>
                        <option value="">— employee —</option>
                        @foreach ($employees as $e) <option value="{{ $e->id }}">{{ $e->name }} ({{ $e->employee_id }})</option> @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="feather-plus"></i> Add mapping</button></div>
            </form>
        </div>
    </div>

    </div>
@endsection

@section('script-area')
    <script>
        const csrfToken = $('meta[name="csrf-token"]').attr('content');

        function enrSelectedIds() {
            return $('.enr-row-sel:checked').map(function() { return this.value; }).get();
        }
        function enrSyncBulkBar() {
            const n = enrSelectedIds().length;
            $('#enrBulkBar').toggleClass('d-none', n === 0).toggleClass('d-flex', n > 0);
            $('#enrBulkCount').text(n + ' selected');
        }
        function enrClearSelection() {
            $('.enr-row-sel').prop('checked', false);
            $('#enrSelectAll').prop('checked', false);
            enrSyncBulkBar();
        }
        function bulkEnrollmentAction(action) {
            const ids = enrSelectedIds();
            if (!ids.length) { toastr.info('Select employees first'); return; }
            if (action === 'remove' && !confirm('Remove ' + ids.length + ' selected enrollment(s) from the device? They will need to re-enrol.')) {
                return;
            }

            const url = action === 'repush'
                ? "{{ route('settings.biometric.enrollments.bulk-repush', $device) }}"
                : "{{ route('settings.biometric.enrollments.bulk-remove', $device) }}";

            $.ajax({
                url: url,
                type: 'POST',
                data: { enrollment_ids: ids, _token: csrfToken },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        setTimeout(() => location.reload(), 900);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Bulk action failed. Please try again.');
                }
            });
        }

        $(function() {
            [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).map(function(el) {
                return new bootstrap.Tooltip(el);
            });

            $('#enrSelectAll').on('change', function() {
                $('.enr-row-sel').prop('checked', this.checked);
                enrSyncBulkBar();
            });
            $(document).on('change', '.enr-row-sel', enrSyncBulkBar);
        });
    </script>
@endsection
