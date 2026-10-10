@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .rot-page .content-area-body { padding: 10px 14px !important; }
        .rot-card { border: 1px solid #edf2f7; border-radius: 10px; padding: 12px; height: 100%; background: #fff; }
        .rot-card .t { font-weight: 700; font-size: 13px; }
        .rot-card .s { font-size: 11px; color: #64748b; }
        .rot-cycle { display: flex; flex-wrap: wrap; gap: 3px; margin: 8px 0; }
        .rot-day { width: 26px; height: 26px; border-radius: 6px; font-size: 9px; font-weight: 700; display: flex; align-items: center; justify-content: center; color: #fff; }
        .rot-day.off { background: #f1f5f9; color: #94a3b8; }
        .rot-builder { display: grid; grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); gap: 6px; max-height: 300px; overflow: auto; }
        .rot-builder .cell { border: 1px solid #edf2f7; border-radius: 8px; padding: 4px 6px; }
        .rot-builder .cell label { font-size: 10px; color: #64748b; margin: 0; }
        .rot-builder select { font-size: 11px; padding: 2px 4px; height: auto; }
        .rot-preview { display: flex; flex-wrap: wrap; gap: 3px; }
        .rot-preview .d { text-align: center; font-size: 9px; color: #64748b; }
    </style>
@endsection

@section('content-area')
    @php
        $initials = fn ($name) => strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', $name) ?: $name, 0, 2));
        $shiftMap = $shifts->keyBy('id');
    @endphp
    <div class="rot-page">
        <x-ui.page-header class="content-area-header sticky-top" title="Rotation Patterns" :crumbs="[['label' => 'Shift']]">
            <x-slot:actions>
                <div class="hstack gap-2">
                    <a href="{{ route('shift.roster') }}" class="btn-reset"><i class="feather-grid"></i>Roster</a>
                    <a href="#" class="btn-reset" id="assignBtn"><i class="feather-user-check"></i>Assign Pattern</a>
                    <a href="#" class="btn-grad" id="newBtn"><i class="feather-plus"></i>New Pattern</a>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="content-area-body">
            <p class="text-muted mb-2" style="font-size:12px">
                A rotation repeats a cycle of shifts automatically — e.g. a week of mornings, a week of evenings, a week of nights; or 4 days on, 3 days off.
                The roster is planned {{ \App\Services\Shift\ShiftMaterializer::PERMANENT_HORIZON_DAYS }} days ahead and extended every night. Pattern days off count as week-offs.
            </p>

            <div class="row g-2 mb-3">
                @forelse ($patterns as $p)
                    @php $map = $p->stepMap(); @endphp
                    <div class="col-md-6 col-xl-4">
                        <div class="rot-card">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="t">{{ $p->name }} @unless ($p->status)<span class="badge bg-soft-secondary text-secondary">Off</span>@endunless</div>
                                    <div class="s">{{ $p->cycle_days }}-day cycle · {{ $activeCounts[$p->id] ?? 0 }} employee(s) on it{{ $p->description ? ' · ' . $p->description : '' }}</div>
                                </div>
                                <div class="d-flex gap-1">
                                    <a href="#" class="action-btn edit-pattern" title="Edit" data-id="{{ $p->id }}" data-name="{{ $p->name }}" data-description="{{ $p->description }}" data-steps='@json($map)'><i class="feather-edit-2"></i></a>
                                    <a href="#" class="action-btn delete del-pattern" title="Delete" data-id="{{ $p->id }}" data-name="{{ $p->name }}"><i class="feather-trash-2"></i></a>
                                </div>
                            </div>
                            <div class="rot-cycle">
                                @foreach ($map as $i => $sid)
                                    @php $s = $sid ? $shiftMap->get($sid) : null; @endphp
                                    <span class="rot-day {{ $s ? '' : 'off' }}" style="{{ $s ? 'background:' . ($s->color_code ?: '#4f46e5') : '' }}"
                                          title="Day {{ $i + 1 }}: {{ $s ? $s->name : 'Day off' }}">{{ $s ? $initials($s->name) : 'OFF' }}</span>
                                @endforeach
                            </div>
                            @if ($p->status)
                                <a href="#" class="btn btn-sm btn-outline-primary assign-pattern" data-id="{{ $p->id }}"><i class="feather-user-check me-1"></i>Assign</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-12"><x-ui.empty-state icon="refresh-cw" title="No rotation patterns yet" subtitle="Create one with “New Pattern”, then assign it to employees." /></div>
                @endforelse
            </div>

            <x-ui.card title="Employees on a rotation" :bodyClass="'p-0'">
                <x-ui.data-table>
                    <thead><tr><th>Employee</th><th>Pattern</th><th>Since</th><th>Cycle day 1</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($assignments as $a)
                            <tr>
                                <td>{{ $a->user->name ?? '—' }} <span class="text-muted small">{{ $a->user->employee_id ?? '' }}</span></td>
                                <td>{{ $a->rotationPattern->name ?? '—' }}</td>
                                <td>{{ $a->start_date->format('d M Y') }}</td>
                                <td>{{ optional($a->rotation_anchor_date)->format('d M Y') }}</td>
                                <td class="text-end"><a href="#" class="action-btn warning end-rotation" data-id="{{ $a->id }}" data-name="{{ $a->user->name ?? '' }}" title="End rotation"><i class="feather-square"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Nobody is on a rotation.</td></tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </x-ui.card>
        </div>
    </div>
@endsection

@section('create-modal')
    {{-- Pattern builder --}}
    <div class="modal fade modal-custom" id="patternModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2"><h5 class="modal-title" style="font-size:15px" id="pm_title">New Rotation Pattern</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="patternForm" autocomplete="off">
                        <input type="hidden" id="pm_id">
                        <div class="row g-2">
                            <div class="col-md-5"><label class="form-label">Name</label><input type="text" class="form-control" name="name" id="pm_name" maxlength="100" required placeholder="e.g. 3-shift weekly rotation"></div>
                            <div class="col-md-5"><label class="form-label">Description <span class="text-muted">(optional)</span></label><input type="text" class="form-control" name="description" id="pm_desc" maxlength="255"></div>
                            <div class="col-md-2"><label class="form-label">Cycle (days)</label><input type="number" class="form-control" id="pm_days" min="1" max="56" value="7"></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-end my-2" style="font-size:12px">
                            <span class="text-muted">Quick fill:</span>
                            <select class="form-control form-control-sm" id="qf_shift" style="width:auto">
                                <option value="">Day off</option>
                                @foreach ($shifts as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                            </select>
                            <span>for days</span>
                            <input type="number" class="form-control form-control-sm" id="qf_from" min="1" value="1" style="width:64px">
                            <span>to</span>
                            <input type="number" class="form-control form-control-sm" id="qf_to" min="1" value="7" style="width:64px">
                            <button type="button" class="btn btn-sm btn-light" id="qf_apply">Apply</button>
                        </div>
                        <div class="rot-builder" id="pm_builder"></div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="patternForm" class="btn btn-primary btn-sm">Save Pattern</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Assign --}}
    <div class="modal fade modal-custom" id="assignModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2"><h5 class="modal-title" style="font-size:15px">Assign Rotation Pattern</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="assignForm" autocomplete="off">
                        <div class="row g-2">
                            <div class="col-md-6"><label class="form-label">Pattern</label>
                                <select class="form-control" name="pattern_id" id="as_pattern" required>
                                    <option value="">Select pattern</option>
                                    @foreach ($patterns->where('status', true) as $p)<option value="{{ $p->id }}" data-steps='@json($p->stepMap())'>{{ $p->name }} ({{ $p->cycle_days }} days)</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Start date</label><input type="date" class="form-control" name="start_date" id="as_start" value="{{ now()->toDateString() }}" required></div>
                            <div class="col-md-3"><label class="form-label">Stagger (days)</label><input type="number" class="form-control" name="stagger_days" id="as_stagger" min="0" max="365" value="0" title="Each next employee starts this many days further into the cycle"></div>
                            <div class="col-12"><label class="form-label">Employees</label>
                                <select class="form-control" name="user_ids[]" id="as_users" multiple required>
                                    @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->employee_id }})</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <input type="hidden" name="replace_weekly_offs" value="0">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="replace_weekly_offs" id="as_replace" value="1" checked>
                            <label class="form-check-label" for="as_replace" style="font-size:12px">Replace their weekly offs (e.g. every Sunday) with the pattern's days off from the start date</label>
                        </div>
                        <p class="text-muted mt-2 mb-1" style="font-size:11px">Replaces each employee's current permanent or rotating shift from the start date (kept in history). One-day changes and swaps already made stay as they are.</p>
                        <div class="mt-2"><label class="form-label">First 14 days (first employee)</label><div class="rot-preview" id="as_preview"></div></div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="assignForm" class="btn btn-primary btn-sm">Assign</button>
                </div>
            </div>
        </div>
    </div>

    {{-- End rotation --}}
    <div class="modal fade modal-custom" id="endModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2"><h5 class="modal-title" style="font-size:15px">End Rotation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="mb-2" style="font-size:12px" id="em_text"></p>
                    <label class="form-label">Last day on the rotation</label>
                    <input type="date" class="form-control" id="em_date" value="{{ now()->toDateString() }}">
                    <label class="form-label mt-2">Reason</label>
                    <textarea class="form-control" id="em_reason" rows="2" maxlength="500"></textarea>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning btn-sm" id="em_go">End Rotation</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        $(function () {
            toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 4000 };
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const modal = id => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
            const shifts = @json($shifts->mapWithKeys(fn ($s) => [$s->id => ['name' => $s->name, 'color' => $s->color_code ?: '#4f46e5']]));
            const options = '<option value="">Day off</option>' + Object.entries(shifts).map(([id, s]) => '<option value="' + id + '">' + $('<div>').text(s.name).html() + '</option>').join('');
            const fail = x => toastr.error(x.responseJSON?.message || (x.responseJSON?.errors ? Object.values(x.responseJSON.errors)[0][0] : 'Failed'));

            /* builder */
            function build(steps) {
                const n = steps.length;
                $('#pm_builder').html(steps.map((s, i) => '<div class="cell"><label>Day ' + (i + 1) + '</label><select class="form-control step">' + options + '</select></div>').join(''));
                $('#pm_builder .step').each((i, el) => $(el).val(steps[i] ? String(steps[i]) : ''));
                $('#qf_to').val(n);
            }
            function current() { return $('#pm_builder .step').map((i, el) => el.value || null).get(); }
            $('#pm_days').on('change', function () {
                const n = Math.max(1, Math.min(56, +this.value || 1)), cur = current();
                build(Array.from({ length: n }, (_, i) => cur[i] ?? null));
            });
            $('#qf_apply').on('click', function () {
                const cur = current(), from = Math.max(1, +$('#qf_from').val()), to = Math.min(cur.length, +$('#qf_to').val());
                for (let i = from - 1; i < to; i++) cur[i] = $('#qf_shift').val() || null;
                build(cur);
            });
            $('#newBtn').on('click', e => {
                e.preventDefault();
                $('#pm_id').val(''); $('#pm_title').text('New Rotation Pattern'); $('#patternForm')[0].reset(); $('#pm_days').val(7);
                build(Array(7).fill(null)); modal('patternModal').show();
            });
            $(document).on('click', '.edit-pattern', function (e) {
                e.preventDefault();
                const d = $(this).data();
                $('#pm_id').val(d.id); $('#pm_title').text('Edit ' + d.name); $('#pm_name').val(d.name); $('#pm_desc').val(d.description || '');
                $('#pm_days').val(d.steps.length); build(d.steps); modal('patternModal').show();
            });
            $('#patternForm').on('submit', function (e) {
                e.preventDefault();
                const id = $('#pm_id').val();
                $.ajax({
                    url: id ? "{{ url('shift/rotations') }}/" + id : "{{ route('shift.rotations.store') }}",
                    type: id ? 'PUT' : 'POST', headers: { 'X-CSRF-TOKEN': csrf },
                    data: { name: $('#pm_name').val(), description: $('#pm_desc').val(), steps: current().map(v => v || 0) },
                    success: r => { modal('patternModal').hide(); toastr.success(r.message); setTimeout(() => location.reload(), 800); },
                    error: fail
                });
            });
            $(document).on('click', '.del-pattern', function (e) {
                e.preventDefault();
                if (!confirm('Delete pattern “' + $(this).data('name') + '”?')) return;
                $.ajax({ url: "{{ url('shift/rotations') }}/" + $(this).data('id'), type: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { toastr.success(r.message); setTimeout(() => location.reload(), 900); }, error: fail });
            });

            /* assign */
            $('#as_users').select2({ width: '100%', dropdownParent: $('#assignModal'), placeholder: 'Select employees', closeOnSelect: false });
            function preview() {
                const steps = $('#as_pattern option:selected').data('steps');
                const start = $('#as_start').val();
                if (!steps || !start) { $('#as_preview').empty(); return; }
                let html = '';
                for (let i = 0; i < 14; i++) {
                    const d = new Date(start + 'T00:00:00'); d.setDate(d.getDate() + i);
                    const s = shifts[steps[i % steps.length]];
                    html += '<div class="d"><span class="rot-day ' + (s ? '' : 'off') + '" style="' + (s ? 'background:' + s.color : '') + '" title="' + (s ? s.name : 'Day off') + '">' +
                        (s ? s.name.replace(/[^A-Za-z]/g, '').slice(0, 2).toUpperCase() : 'OFF') + '</span>' + d.getDate() + '/' + (d.getMonth() + 1) + '</div>';
                }
                $('#as_preview').html(html);
            }
            $('#as_pattern,#as_start').on('change', preview);
            function openAssign(id) { $('#assignForm')[0].reset(); $('#as_users').val(null).trigger('change'); if (id) $('#as_pattern').val(id); preview(); modal('assignModal').show(); }
            $('#assignBtn').on('click', e => { e.preventDefault(); openAssign(); });
            $(document).on('click', '.assign-pattern', function (e) { e.preventDefault(); openAssign($(this).data('id')); });
            $('#assignForm').on('submit', function (e) {
                e.preventDefault();
                $.ajax({ url: "{{ route('shift.rotations.assign') }}", type: 'POST', data: $(this).serialize(), headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { modal('assignModal').hide(); toastr.success(r.message); setTimeout(() => location.reload(), 1200); }, error: fail });
            });

            /* end */
            let endId = null;
            $(document).on('click', '.end-rotation', function (e) {
                e.preventDefault(); endId = $(this).data('id');
                $('#em_text').text('From the day after, ' + $(this).data('name') + ' has no rotation (any other assignment takes over). History is kept.');
                modal('endModal').show();
            });
            $('#em_go').on('click', function () {
                $.ajax({ url: "{{ url('shift/assignments') }}/" + endId + '/end-permanent', type: 'POST', headers: { 'X-CSRF-TOKEN': csrf },
                    data: { end_date: $('#em_date').val(), reason: $('#em_reason').val() },
                    success: r => { modal('endModal').hide(); toastr.success('Rotation ended.'); setTimeout(() => location.reload(), 800); }, error: fail });
            });
        });
    </script>
@endsection
