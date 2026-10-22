{{--
    Apply / Edit leave side drawer for the My Leaves page (leave.view).
    One form for both: openLeaveDrawer() (apply) posts to leave.apply-store,
    openLeaveDrawer(btn) (edit, btn = a row's .js-edit-leave with data-*) posts
    to leave.update-store. A failed submit comes back to leave.view with old
    input; the drawer then reopens in the same mode (hidden _leave_mode /
    _leave_action). Needs $leaveTypes, $leaveBalances (type id => balance) and
    $prefillLeaveDate from LeaveController::index().
--}}
<x-ui.drawer id="leaveDrawer" title="Apply Leave" subtitle="Request time off — your reporting manager will be notified to approve it." width="520px">
    <form id="leaveDrawerForm" action="{{ route('leave.apply-store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_leave_mode" id="ld_mode" value="apply">
        <input type="hidden" name="_leave_action" id="ld_action" value="">

        @if (session('error') && old('_leave_mode'))
            <div class="alert alert-danger py-2 px-3 mb-3 ld-alert"><i class="feather-alert-circle me-1"></i>{{ session('error') }}</div>
        @endif

        <div class="ld-balance mb-3 d-none" id="ld_balance">
            <i class="feather-pie-chart"></i>
            <span>Available balance: <strong id="ld_balance_value">0</strong> day(s)</span>
        </div>

        <div class="mb-3">
            <label for="ld_leave_type" class="form-label">Leave Type <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm @error('leave_type') is-invalid @enderror" name="leave_type" id="ld_leave_type" required>
                <option value="" selected disabled>Select leave type</option>
                @foreach ($leaveTypes as $leaveType)
                    <option value="{{ $leaveType->id }}" data-balance="{{ number_format((float) ($leaveBalances[$leaveType->id] ?? 0), 2) }}"
                        {{ old('leave_type') == $leaveType->id ? 'selected' : '' }}>
                        {{ $leaveType->name }} ({{ number_format((float) ($leaveBalances[$leaveType->id] ?? 0), 2) }})
                    </option>
                @endforeach
            </select>
            @error('leave_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label for="ld_start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                <input type="date" name="start_date" id="ld_start_date" class="form-control form-control-sm @error('start_date') is-invalid @enderror"
                    value="{{ old('start_date', $prefillLeaveDate) }}" required>
                @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-6">
                <label for="ld_start_session" class="form-label">Start Session <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm @error('start_session') is-invalid @enderror" name="start_session" id="ld_start_session" required>
                    @foreach (['fullday' => 'Full Day', 'session1' => 'Session 1 (First half)', 'session2' => 'Session 2 (Second half)'] as $value => $label)
                        <option value="{{ $value }}" {{ old('start_session', 'fullday') == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('start_session') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-6">
                <label for="ld_end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                <input type="date" name="end_date" id="ld_end_date" class="form-control form-control-sm @error('end_date') is-invalid @enderror"
                    value="{{ old('end_date', $prefillLeaveDate) }}" required>
                @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-6">
                <label for="ld_end_session" class="form-label">End Session <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm @error('end_session') is-invalid @enderror" name="end_session" id="ld_end_session" required>
                    @foreach (['fullday' => 'Full Day', 'session1' => 'Session 1 (First half)', 'session2' => 'Session 2 (Second half)'] as $value => $label)
                        <option value="{{ $value }}" {{ old('end_session', 'fullday') == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('end_session') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="ld-days mb-3 d-none" id="ld_days">
            <i class="feather-info"></i>
            <span><strong id="ld_days_value">0</strong> day(s) selected — weekends and holidays are not counted.</span>
        </div>

        <div class="mb-3">
            <label for="ld_reason" class="form-label">Reason <span class="text-danger">*</span></label>
            <textarea name="reason" id="ld_reason" rows="3" maxlength="500" class="form-control form-control-sm @error('reason') is-invalid @enderror"
                placeholder="Why do you need this leave?" required>{{ old('reason') }}</textarea>
            @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="ld_file" class="form-label">Attachment</label>
            <input type="file" name="file" id="ld_file" class="form-control form-control-sm @error('file') is-invalid @enderror">
            @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <small class="text-muted d-block mt-1">Max 2 MB · pdf, jpg, png, doc, docx</small>
            <small class="d-none mt-1" id="ld_current_file">Current file: <a href="#" target="_blank" rel="noopener">view</a> — upload a new one to replace it.</small>
        </div>

        <div class="d-flex gap-2 pt-2 border-top">
            <button type="submit" class="btn btn-primary btn-sm" id="ld_submit"><i class="feather-send me-1"></i><span>Apply Leave</span></button>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
        </div>
    </form>
</x-ui.drawer>

<style>
    #leaveDrawer .form-label { font-size: 11.5px; font-weight: 600; color: #475569; margin-bottom: 4px; }
    #leaveDrawer .form-control, #leaveDrawer .form-select { font-size: 12px; min-height: 34px; height: 34px; padding-top: 6px; padding-bottom: 6px; }
    #leaveDrawer textarea.form-control { height: auto; }
    #leaveDrawer .ld-balance, #leaveDrawer .ld-days { display: flex; align-items: center; gap: 8px; font-size: 12px; border-radius: 8px; padding: 8px 10px; }
    #leaveDrawer .ld-balance { background: #EFF6FF; color: #0D6EFD; }
    #leaveDrawer .ld-days { background: #f8fafc; color: #475569; border: 1px dashed #dbe3ef; }
    #leaveDrawer .ld-balance.d-none, #leaveDrawer .ld-days.d-none { display: none !important; }
    #leaveDrawer .ld-alert { font-size: 12px; }
</style>

<script>
    (function () {
        var form = document.getElementById('leaveDrawerForm');
        var applyUrl = @json(route('leave.apply-store'));
        var today = new Date().toISOString().split('T')[0];
        var $ = function (id) { return document.getElementById(id); };

        function setMode(mode, action) {
            var edit = mode === 'edit';
            $('ld_mode').value = mode;
            $('ld_action').value = edit ? action : '';
            form.action = edit ? action : applyUrl;
            $('leaveDrawerLabel').textContent = edit ? 'Edit Leave' : 'Apply Leave';
            $('leaveDrawerSubtitle').textContent = edit
                ? 'Change your pending leave request — it stays pending until your manager decides.'
                : 'Request time off — your reporting manager will be notified to approve it.';
            $('ld_submit').querySelector('span').textContent = edit ? 'Update Leave' : 'Apply Leave';
        }

        function showBalance() {
            var opt = $('ld_leave_type').selectedOptions[0];
            var has = opt && opt.value;
            $('ld_balance').classList.toggle('d-none', !has);
            if (has) $('ld_balance_value').textContent = opt.dataset.balance || '0';
        }

        // Calendar days in the range, less half-day sessions (the server excludes weekends / holidays).
        function showDays() {
            var s = $('ld_start_date').value, e = $('ld_end_date').value;
            var ss = $('ld_start_session').value, es = $('ld_end_session').value;
            if (!s || !e || e < s) { $('ld_days').classList.add('d-none'); return; }
            // Start Session 2 = from the second half of the first day; End Session 1 = up to the first half of the last day.
            var days = Math.round((new Date(e) - new Date(s)) / 86400000) + 1;
            if (days === 1) {
                days = (ss === 'session2' ? 0 : 0.5) + (es === 'session1' ? 0 : 0.5);
            } else {
                if (ss === 'session2') days -= 0.5;
                if (es === 'session1') days -= 0.5;
            }
            $('ld_days_value').textContent = days;
            $('ld_days').classList.remove('d-none');
        }

        function setMinDates(minStart) {
            var min = minStart && minStart < today ? minStart : today;
            $('ld_start_date').min = min;
            $('ld_end_date').min = $('ld_start_date').value || min;
        }

        function clearErrors() {
            form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
            form.querySelectorAll('.invalid-feedback, .ld-alert').forEach(function (el) { el.remove(); });
        }

        // btn = a row's edit button (data-*) for edit, nothing for apply; date = prefill (apply).
        window.openLeaveDrawer = function (btn, date) {
            clearErrors();
            form.reset();
            $('ld_current_file').classList.add('d-none');
            if (btn && btn.dataset) {
                var d = btn.dataset;
                setMode('edit', d.action);
                $('ld_leave_type').value = d.leaveType;
                $('ld_start_date').value = d.startDate;
                $('ld_start_session').value = d.startSession || 'fullday';
                $('ld_end_date').value = d.endDate || d.startDate;
                $('ld_end_session').value = d.endSession || 'fullday';
                $('ld_reason').value = d.reason || '';
                if (d.file) {
                    $('ld_current_file').querySelector('a').href = d.file;
                    $('ld_current_file').classList.remove('d-none');
                }
                setMinDates(d.startDate);
            } else {
                setMode('apply');
                $('ld_leave_type').value = '';
                $('ld_start_date').value = date || '';
                $('ld_end_date').value = date || '';
                setMinDates(date);
            }
            showBalance();
            showDays();
            bootstrap.Offcanvas.getOrCreateInstance($('leaveDrawer')).show();
        };

        $('ld_leave_type').addEventListener('change', showBalance);
        $('ld_start_date').addEventListener('change', function () {
            if (!$('ld_end_date').value || $('ld_end_date').value < this.value) $('ld_end_date').value = this.value;
            $('ld_end_date').min = this.value;
            showDays();
        });
        ['ld_end_date', 'ld_start_session', 'ld_end_session'].forEach(function (id) { $(id).addEventListener('change', showDays); });

        document.addEventListener('click', function (ev) {
            var trigger = ev.target.closest('.js-apply-leave, .js-edit-leave');
            if (!trigger) return;
            ev.preventDefault();
            window.openLeaveDrawer(trigger.classList.contains('js-edit-leave') ? trigger : null);
        });

        document.addEventListener('DOMContentLoaded', function () {
            var oldMode = @json(old('_leave_mode'));
            if (oldMode) {
                // A refused submit: reopen with what the employee typed (and the server's message).
                setMode(oldMode, @json(old('_leave_action')));
                setMinDates($('ld_start_date').value);
                showBalance();
                showDays();
                bootstrap.Offcanvas.getOrCreateInstance($('leaveDrawer')).show();
                return;
            }
            var params = new URLSearchParams(window.location.search);
            if (params.get('edit')) {
                var row = document.querySelector('.js-edit-leave[data-id="' + params.get('edit') + '"]');
                if (row) window.openLeaveDrawer(row);
            } else if (params.get('apply')) {
                window.openLeaveDrawer(null, @json($prefillLeaveDate));
            }
        });
    })();
</script>
