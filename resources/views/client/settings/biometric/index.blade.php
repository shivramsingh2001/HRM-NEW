@extends('client.layout.master')

@section('content-area')
    <x-ui.page-header title="Biometric Terminals" subtitle="Register terminals, generate bridge keys, and map enroll numbers to employees.">
        <x-slot:actions>
            <button class="btn btn-primary btn-sm" data-bs-toggle="offcanvas" data-bs-target="#addTerminalDrawer">
                <i class="feather-plus"></i> Add Terminal
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-warning">{{ session('error') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @if (session('new_api_secret'))
        <div class="alert alert-warning">
            <strong>Bridge key — copy now, shown only once:</strong><br>
            <code class="key">{{ session('new_api_secret') }}</code>
            <div class="mt-1" style="font-size:12px">Put this in the bridge's <code>appsettings.json</code> as <code>Hrm.ApiKey</code>.</div>
        </div>
    @endif

    @if (session('push_url'))
        @php($push = session('push_url'))
        <div class="alert alert-info">
            <strong>Push URL for {{ $push['name'] }}:</strong><br>
            <code class="key">{{ $push['url'] }}</code>
            <div class="mt-1" style="font-size:12px">
                On the terminal: Comm → server-client mode <code>FkWeb</code> → Webserver URL = this value.
                Plain <code>http://</code> only — the terminal cannot do HTTPS. Treat it like a password.
            </div>
            <form method="POST" action="{{ route('settings.biometric.devices.push-url', $push['device']) }}" class="mt-2">
                @csrf
                <input type="hidden" name="rotate" value="1">
                <button type="submit" class="btn btn-sm btn-outline-danger">Rotate URL</button>
            </form>
        </div>
    @endif

    <div class="stats-grid">
        <div class="stats-card">
            <div class="stats-icon"><i class="feather-cpu"></i></div>
            <div class="stats-info"><h3>{{ $totalDevices }}</h3><p>Total Terminals</p></div>
        </div>
        <div class="stats-card">
            <div class="stats-icon"><i class="feather-check-circle"></i></div>
            <div class="stats-info"><h3>{{ $activeDevices }}</h3><p>Active</p></div>
        </div>
        <div class="stats-card">
            <div class="stats-icon"><i class="feather-x-circle"></i></div>
            <div class="stats-info"><h3>{{ $inactiveDevices }}</h3><p>Inactive</p></div>
        </div>
        <div class="stats-card">
            <div class="stats-icon"><i class="feather-user-check"></i></div>
            <div class="stats-info"><h3>{{ $mappedTotal }}</h3><p>Mapped Employees</p></div>
        </div>
        <div class="stats-card">
            <div class="stats-icon"><i class="feather-user-x"></i></div>
            <div class="stats-info"><h3>{{ $unmappedTotal }}</h3><p>Unmapped</p></div>
        </div>
    </div>

    <div class="s-card">
        <div class="s-head"><h5>Devices</h5></div>
        <div class="s-body">
            <p class="text-muted" style="font-size:12px">
                Each terminal is polled by a Windows "bridge" service that runs the vendor SDK and
                posts punches to <code>/api/v1/biometric/punches</code>. Register the device here,
                generate its bridge key, then map its enroll numbers to employees.
            </p>

            @if ($devices->isEmpty())
                <x-ui.empty-state icon="cpu" title="No terminals yet" subtitle="Click Add Terminal to register your first biometric device." />
            @else
                <x-ui.data-table>
                    <thead><tr>
                        <th width="40">#</th>
                        <th>Name</th><th>Serial</th><th>Mode</th><th>Direction</th>
                        <th>Mapped / Unmapped</th><th>Last seen</th><th>Last punch</th><th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($devices as $d)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $d->name }}</td>
                            <td><code>{{ $d->serial_number }}</code></td>
                            <td>{{ $d->p2p_uid ? 'P2P' : 'LAN' }}{{ $d->ip_address ? ' · '.$d->ip_address : '' }}</td>
                            <td>{{ $d->direction_mode }}</td>
                            <td>{{ $d->mapped_count }} / {{ $d->unmapped_count }}</td>
                            <td>{{ $d->last_seen_at?->diffForHumans() ?? '—' }}</td>
                            <td>{{ $d->last_punch_at?->diffForHumans() ?? '—' }}</td>
                            <td><x-ui.status-badge :status="$d->is_active ? 'active' : 'inactive'" /></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('settings.biometric.enrollments', $d) }}"
                                       class="action-btn" title="Map users" data-bs-toggle="tooltip">
                                        <i class="feather-users"></i>
                                    </a>
                                    <a href="#" class="action-btn edit-terminal" title="Edit" data-bs-toggle="tooltip"
                                       data-bs-target="#editTerminalDrawer"
                                       data-id="{{ $d->id }}"
                                       data-serial_number="{{ $d->serial_number }}"
                                       data-name="{{ $d->name }}"
                                       data-ip_address="{{ $d->ip_address }}"
                                       data-p2p_uid="{{ $d->p2p_uid }}"
                                       data-site_timezone="{{ $d->site_timezone }}"
                                       data-branch_id="{{ $d->branch_id }}"
                                       data-direction_mode="{{ $d->direction_mode }}"
                                       data-is_active="{{ $d->is_active ? 1 : 0 }}"
                                       data-auto_provision="{{ $d->auto_provision ? 1 : 0 }}"
                                       data-provision_scope="{{ $d->provision_scope }}"
                                       data-default_privilege="{{ $d->default_privilege }}"
                                       data-allow_direct_onboarding="{{ $d->allow_direct_onboarding ? 1 : 0 }}">
                                        <i class="feather-edit-3"></i>
                                    </a>
                                    <form method="POST" action="{{ route('settings.biometric.devices.bridge-key', $d) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="action-btn" title="Bridge key" data-bs-toggle="tooltip">
                                            <i class="feather-key"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('settings.biometric.devices.push-url', $d) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="action-btn" title="Push URL (FkWeb direct)" data-bs-toggle="tooltip">
                                            <i class="feather-link"></i>
                                        </button>
                                    </form>
                                    <a href="{{ route('settings.biometric.devices.config', $d) }}"
                                       class="action-btn" title="Download config" data-bs-toggle="tooltip">
                                        <i class="feather-download"></i>
                                    </a>
                                    <form method="POST" action="{{ route('settings.biometric.devices.destroy', $d) }}" class="d-inline"
                                          onsubmit="return confirm('Remove this device and its mappings?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="action-btn" title="Delete" data-bs-toggle="tooltip">
                                            <i class="feather-trash-2"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </x-ui.data-table>
            @endif
            <a class="btn btn-sm btn-link mt-2" href="{{ route('settings.biometric.punches') }}">View recent punches →</a>
        </div>
    </div>

    </div>
@endsection

@section('create-modal')
    @include('client.settings.biometric.partials.add-terminal-drawer', ['branches' => $branches, 'timezones' => $timezones])
    @include('client.settings.biometric.partials.edit-terminal-drawer', ['branches' => $branches, 'timezones' => $timezones])
@endsection

@section('script-area')
    <script>
        $(function() {
            const csrfToken = $('meta[name="csrf-token"]').attr('content');

            // ==================== ADD TERMINAL ====================
            $('#addTerminalForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#addTerminalFormError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    success: function(response) {
                        if (response.success) {
                            bootstrap.Offcanvas.getInstance(document.getElementById('addTerminalDrawer'))?.hide();
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            $.each(xhr.responseJSON.errors, function(key, value) { $('.' + key + '_error').text(value[0]); });
                            toastr.error('Please fix the validation errors');
                        } else {
                            $('#addTerminalFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });
            });

            $('#addTerminalDrawer').on('hidden.bs.offcanvas', function() {
                $('#addTerminalForm')[0].reset();
                $('.error-text').text('');
                $('#addTerminalFormError').addClass('d-none').text('');
            });

            // ==================== EDIT TERMINAL ====================
            $(document).on('click', '.edit-terminal', function(e) {
                e.preventDefault();
                const d = $(this).data();

                $('#edit_id').val(d.id);
                $('#edit_serial_number').val(d.serial_number);
                $('#edit_name').val(d.name);
                $('#edit_ip_address').val(d.ip_address);
                $('#edit_p2p_uid').val(d.p2p_uid);
                $('#edit_site_timezone').val(d.site_timezone);
                $('#edit_branch_id').val(d.branch_id);
                $('#edit_direction_mode').val(d.direction_mode);
                $('#edit_is_active').prop('checked', String(d.is_active) === '1');
                $('#edit_auto_provision').val(String(d.auto_provision));
                $('#edit_provision_scope').val(d.provision_scope);
                $('#edit_default_privilege').val(d.default_privilege);
                $('#edit_allow_direct_onboarding').val(String(d.allow_direct_onboarding));

                $('.error-text').text('');
                $('#editTerminalFormError').addClass('d-none').text('');

                bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('editTerminalDrawer')).show();
            });

            $('#editTerminalForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#editTerminalFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();

                $.ajax({
                    url: '{{ url("settings/biometric/devices") }}/' + id,
                    type: 'PUT',
                    data: $(this).serialize(),
                    dataType: 'json',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    success: function(response) {
                        if (response.success) {
                            bootstrap.Offcanvas.getInstance(document.getElementById('editTerminalDrawer'))?.hide();
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            $.each(xhr.responseJSON.errors, function(key, value) { $('.edit_' + key + '_error').text(value[0]); });
                            toastr.error('Please fix the validation errors');
                        } else {
                            $('#editTerminalFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });
            });

            $('#editTerminalDrawer').on('hidden.bs.offcanvas', function() {
                $('.error-text').text('');
                $('#editTerminalFormError').addClass('d-none').text('');
            });

            if (typeof toastr !== 'undefined') {
                toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: '3000' };
            }

            // Initialize tooltips
            [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).map(function(el) {
                return new bootstrap.Tooltip(el);
            });
        });
    </script>
@endsection
