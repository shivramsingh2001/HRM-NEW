@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== Stat cards — same anatomy as /team/members
           (view-team-member.blade.php's .stats-card): white card, colored
           top accent bar on hover, icon-wrapper chip, big value + uppercase
           label. Own bcast- prefixed names so this never collides with that
           page's identically-shaped but differently-scoped .stats-card.
           Unlike Team Members' per-card semantic colors (green/red/amber/
           purple), all 4 cards here use the single app-wide blue theme
           (--primary/--primary-mid gradient, --primary-light chip) per the
           user's explicit request — same blue look as Team's "Total Team"
           card, applied uniformly rather than color-coded per status. ==================== */
        .bcast-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 16px; }
        @media (max-width: 1200px) { .bcast-stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .bcast-stats-grid { grid-template-columns: 1fr; } }

        .bcast-stat-card {
            background: white;
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        .bcast-stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .bcast-stat-card:hover::before { opacity: 1; }
        .bcast-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.1);
            border-color: #d1d5db;
        }
        .bcast-stat-card::before { background: linear-gradient(90deg, var(--primary), var(--primary-mid)); }

        .bcast-stat-icon-wrapper {
            width: 34px; height: 34px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; transition: all 0.3s ease;
            background: var(--primary-light);
        }
        .bcast-stat-icon-wrapper i { color: var(--primary); font-size: 15px; }
        .bcast-stat-card:hover .bcast-stat-icon-wrapper { transform: scale(1.05); }

        .bcast-stat-content { flex: 1; min-width: 0; }
        .bcast-stat-value { font-size: 15px; font-weight: 700; color: #0f172a; line-height: 1.2; margin-bottom: 1px; letter-spacing: -0.5px; }
        .bcast-stat-label { font-size: 10.5px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0; }

        /* ==================== Filter bar ==================== */
        .filter-section { background: #fff; border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .form-control-sm-custom { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 10px; font-size: 11px; height: 32px; background-color: #fff; }
        .form-control-sm-custom:focus { border-color: #1e3a8a; box-shadow: 0 0 0 .15rem rgba(30, 58, 138, .12); outline: none; }
        .btn-sm-custom-outline { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; text-decoration: none; }
        .btn-sm-custom-outline:hover { background: #e3edfe; color: #1e3a8a; }

        /* ==================== Status badges ==================== */
        .bcast-status { padding: 3px 10px; border-radius: 20px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; }
        .bcast-status.draft { background: #f1f5f9; color: #475569; }
        .bcast-status.scheduled { background: #e0f2fe; color: #0369a1; }
        .bcast-status.sending { background: #fef3c7; color: #b45309; }
        .bcast-status.sent { background: #e3edfe; color: #1d4ed8; }
        .bcast-status.failed { background: #fee2e2; color: #b91c1c; }
        .bcast-status.cancelled { background: #f1f5f9; color: #94a3b8; }
        .bcast-status.expired { background: #f1f5f9; color: #94a3b8; }

        /* ==================== Composer drawer (moved from the old
           standalone create.blade.php page) ==================== */
        .bcast-card { background: #fff; border: 1px solid #eaeef5; border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; }
        .bcast-card h6 { font-size: 13px; font-weight: 700; color: #1a2236; margin-bottom: 14px; display: flex; align-items: center; gap: 6px; }
        .bcast-card h6 i { color: #1e3a8a; }
        .bcast-form-label { font-size: 11.5px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block; }
        .bcast-form-label .req { color: #dc2626; }

        .bcast-audience-mode { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 14px; }
        .bcast-dims { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }
        .bcast-dims.disabled { opacity: .45; pointer-events: none; }
        .bcast-role-check { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border: 1px solid #dfe5f0; border-radius: 20px; font-size: 11.5px; margin-right: 6px; margin-bottom: 6px; cursor: pointer; }
        .bcast-role-check input { margin: 0; }

        .bcast-count-chip { display: inline-flex; align-items: center; gap: 8px; background: #e3edfe; color: #1e3a8a; font-weight: 700; font-size: 13px; padding: 8px 16px; border-radius: 30px; }
        .bcast-count-chip .n { font-size: 18px; }
        .bcast-count-chip.loading { opacity: .6; }

        .bcast-submit-bar { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; background: #fff; border: 1px solid #eaeef5; border-radius: 12px; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Broadcast Notifications">
        <x-slot:actions>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="offcanvas" data-bs-target="#sendBroadcastDrawer">
                <i class="feather-send me-1"></i> Send Broadcast
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 18px !important;">
        <!-- Stats Cards (same anatomy as /team/members) -->
        <div class="bcast-stats-grid">
            <div class="bcast-stat-card total-card">
                <div class="bcast-stat-icon-wrapper"><i class="feather-radio"></i></div>
                <div class="bcast-stat-content">
                    <div class="bcast-stat-value">{{ $stats['total'] }}</div>
                    <div class="bcast-stat-label">Total Broadcasts</div>
                </div>
            </div>
            <div class="bcast-stat-card sent-card">
                <div class="bcast-stat-icon-wrapper"><i class="feather-check-circle"></i></div>
                <div class="bcast-stat-content">
                    <div class="bcast-stat-value">{{ $stats['sent'] }}</div>
                    <div class="bcast-stat-label">Sent</div>
                </div>
            </div>
            <div class="bcast-stat-card scheduled-card">
                <div class="bcast-stat-icon-wrapper"><i class="feather-clock"></i></div>
                <div class="bcast-stat-content">
                    <div class="bcast-stat-value">{{ $stats['scheduled'] }}</div>
                    <div class="bcast-stat-label">Scheduled</div>
                </div>
            </div>
            <div class="bcast-stat-card recipients-card">
                <div class="bcast-stat-icon-wrapper"><i class="feather-users"></i></div>
                <div class="bcast-stat-content">
                    <div class="bcast-stat-value">{{ number_format($stats['recipients']) }}</div>
                    <div class="bcast-stat-label">Recipients Reached</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form action="{{ route('broadcast.index') }}" method="GET" id="broadcastFilterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <select name="status" class="form-control-sm-custom auto-submit">
                            <option value="">-- All Status --</option>
                            @foreach ($statuses as $s)
                                <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" class="form-control-sm-custom" title="From date" aria-label="From date">
                    </div>
                    <div class="filter-item">
                        <input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" class="form-control-sm-custom" title="To date" aria-label="To date">
                    </div>
                    <div class="filter-item">
                        <input type="text" name="search" class="form-control-sm-custom" style="width: 200px;" placeholder="Search title…" value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="filter-item"><button type="submit" class="btn-sm-custom-outline"><i class="feather-eye"></i> View</button></div>
                    <div class="filter-item"><a href="{{ route('broadcast.index') }}" class="btn-sm-custom-outline"><i class="feather-refresh-cw"></i> Reset</a></div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Sr.No</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Recipients</th>
                                <th>Read</th>
                                <th>Sent / Scheduled</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($broadcasts as $b)
                                <tr>
                                    <td>{{ $broadcasts->firstItem() + $loop->index }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $b->title }}</div>
                                        <div class="text-muted" style="font-size: 11px;">{{ \Illuminate\Support\Str::limit($b->body, 60) }}</div>
                                    </td>
                                    <td><span class="bcast-status {{ $b->status }}">{{ $b->status }}</span></td>
                                    <td>{{ $b->total_recipients }}</td>
                                    <td>{{ $b->read_count }}</td>
                                    <td>
                                        @if ($b->sent_at)
                                            {{ $b->sent_at->format('d M Y, h:i A') }}
                                        @elseif ($b->scheduled_at)
                                            <span class="text-muted">Scheduled: {{ $b->scheduled_at->format('d M Y, h:i A') }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('broadcast.show', $b->id) }}" class="action-btn" title="View">
                                            <i class="feather-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">No broadcasts found for this selection.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $broadcasts->links() }}
        </div>
    </div>
@endsection

@section('create-modal')
    @include('client.broadcast.partials.send-drawer')
@endsection

@section('script-area')
    <script>
        $(function () {
            // ---------- filter bar ----------
            const filterForm = $('#broadcastFilterForm');
            filterForm.find('select.auto-submit').on('change', () => filterForm.submit());

            // ---------- send-broadcast drawer ----------
            const form = $('#broadcastForm');
            const countUrl = "{{ route('broadcast.preview-count') }}";
            const storeUrl = "{{ route('broadcast.store') }}";

            function audiencePayload() {
                const data = {};
                data.all = $('#bcastAll').is(':checked') ? 1 : 0;
                data.role = form.find('input[name="role[]"]:checked').map(function () { return this.value; }).get();
                data.department_ids = form.find('select[name="department_ids[]"]').val() || [];
                data.designation_ids = form.find('select[name="designation_ids[]"]').val() || [];
                data.branch_ids = form.find('select[name="branch_ids[]"]').val() || [];
                data.user_ids = form.find('select[name="user_ids[]"]').val() || [];
                return data;
            }

            function toggleAllMode() {
                const isAll = $('#bcastAll').is(':checked');
                $('#bcastDims').toggleClass('disabled', isAll);
                if (isAll) {
                    form.find('input[name="role[]"]').prop('checked', false);
                    form.find('select.bcast-audience-input').val(null).trigger('change.select2');
                }
            }

            let debounceTimer = null;
            function refreshCount() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    $('#bcastCountChip').addClass('loading');
                    $.post(countUrl, Object.assign({ _token: '{{ csrf_token() }}' }, audiencePayload()))
                        .done(function (res) {
                            $('#bcastCountValue').text(res.recipient_count ?? 0);
                        })
                        .always(function () {
                            $('#bcastCountChip').removeClass('loading');
                        });
                }, 350);
            }

            $('#bcastAll').on('change', function () { toggleAllMode(); refreshCount(); });
            form.on('change', '.bcast-audience-input', refreshCount);

            // select2 only needs to init once the drawer's markup exists —
          
            // this can run on page load same as before.
            $('#sendBroadcastDrawer .select2').select2({
                width: '100%',
                dropdownParent: $('#sendBroadcastDrawer'),
            });
            form.on('change', 'select.bcast-audience-input', refreshCount);

            $('#sendBroadcastDrawer').on('shown.bs.offcanvas', refreshCount);

            form.find('input[name="scheduled_at"]').on('change', function () {
                $('#bcastSubmitLabel').text($(this).val() ? 'Schedule Broadcast' : 'Send Broadcast');
            });

            form.on('submit', function (e) {
                e.preventDefault();

                const btn = $('#bcastSubmitBtn');
                const originalHtml = btn.html();
                btn.prop('disabled', true).html('<i class="feather-loader me-1"></i> Sending…');

                $.ajax({
                    url: storeUrl,
                    method: 'POST',
                    data: form.serialize(),
                    dataType: 'json',
                }).done(function (res) {
                    if (res.success) {
                        bootstrap.Offcanvas.getInstance(document.getElementById('sendBroadcastDrawer'))?.hide();
                        toastr.success(res.message);
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        toastr.error(res.message || 'Failed to send broadcast.');
                        btn.prop('disabled', false).html(originalHtml);
                    }
                }).fail(function (xhr) {
                    if (xhr.status === 422 && xhr.responseJSON?.errors) {
                        $.each(xhr.responseJSON.errors, function (key, messages) {
                            toastr.error(messages[0]);
                        });
                    } else {
                        toastr.error('Failed to send broadcast. Please try again.');
                    }
                    btn.prop('disabled', false).html(originalHtml);
                });
            });

            $('#sendBroadcastDrawer').on('hidden.bs.offcanvas', function () {
                form[0].reset();
                form.find('select.bcast-audience-input').val(null).trigger('change.select2');
                $('#bcastDims').removeClass('disabled');
                $('#bcastCountValue').text('0');
                $('#bcastSubmitLabel').text('Send Broadcast');
            });
        });
    </script>
@endsection
