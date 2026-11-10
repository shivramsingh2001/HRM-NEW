{{-- Add / Edit report schedule drawer + its script. New: openScheduleForm(); edit: openScheduleForm(scheduleJson). --}}
<x-ui.drawer id="scheduleDrawer" title="New Schedule" subtitle="The report is emailed as an attachment at the time you choose (India time)." width="520px">
    <form id="scheduleForm" autocomplete="off">
        <input type="hidden" id="scheduleId">
        <div id="scheduleFormError" class="alert alert-danger d-none" style="font-size:11.5px;"></div>

        <div class="row">
            <div class="col-12 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Schedule Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" maxlength="150" required placeholder="e.g. Daily attendance to management">
                <small class="text-danger error-text name_error"></small>
            </div>

            <div class="col-12 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Report <span class="text-danger">*</span></label>
                <select class="form-control" name="report_key" id="scheduleReport" required>
                    <option value="">-- Select report --</option>
                    @foreach ($options['reports'] as $group => $reports)
                        <optgroup label="{{ $group }}">
                            @foreach ($reports as $key => $r)
                                <option value="{{ $key }}" data-periods="{{ json_encode($r['periods']) }}">{{ $r['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <small class="text-danger error-text report_key_error"></small>
            </div>

            <div class="col-12 mb-3" id="schedulePeriodRow">
                <label class="fw-semibold" style="font-size:11.5px;">Report Period</label>
                <select class="form-control" name="period" id="schedulePeriod"></select>
                <small class="text-muted d-none" id="schedulePeriodNone" style="font-size:11px;">This report has no date — it is sent with the data as it is at send time.</small>
                <small class="text-danger error-text period_error"></small>
            </div>

            @if ($options['branches']->isNotEmpty())
                <div class="col-md-6 mb-3">
                    <label class="fw-semibold" style="font-size:11.5px;">Branch</label>
                    <select class="form-control" name="branch_id">
                        <option value="">All branches</option>
                        @foreach ($options['branches'] as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-danger error-text branch_id_error"></small>
                </div>
            @endif
            <div class="{{ $options['branches']->isNotEmpty() ? 'col-md-6' : 'col-12' }} mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Department</label>
                <select class="form-control" name="department_id">
                    <option value="">All departments</option>
                    @foreach ($options['departments'] as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select>
                <small class="text-muted" style="font-size:10.5px;">Used by reports that have a branch / department filter.</small>
                <small class="text-danger error-text department_id_error"></small>
            </div>

            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Repeat <span class="text-danger">*</span></label>
                <select class="form-control" name="frequency" id="scheduleFrequency">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
                <small class="text-danger error-text frequency_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Send At <span class="text-danger">*</span></label>
                <input type="time" class="form-control" name="send_time" required value="09:00">
                <small class="text-danger error-text send_time_error"></small>
            </div>

            <div class="col-12 mb-3 freq-daily">
                <label class="fw-semibold d-block" style="font-size:11.5px;">On these days</label>
                <div class="d-flex flex-wrap gap-2" style="font-size:12px;">
                    @foreach (['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 0] as $label => $d)
                        <label class="d-inline-flex align-items-center gap-1">
                            <input type="checkbox" name="weekdays[]" value="{{ $d }}" checked> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <small class="text-danger error-text weekdays_error"></small>
            </div>
            <div class="col-12 mb-3 freq-weekly d-none">
                <label class="fw-semibold" style="font-size:11.5px;">Day of the week</label>
                <select class="form-control" name="weekly_day">
                    @foreach (['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 0] as $label => $d)
                        <option value="{{ $d }}">{{ $label }}</option>
                    @endforeach
                </select>
                <small class="text-danger error-text weekly_day_error"></small>
            </div>
            <div class="col-12 mb-3 freq-monthly d-none">
                <label class="fw-semibold" style="font-size:11.5px;">Day of the month</label>
                <select class="form-control" name="day_of_month">
                    @for ($d = 1; $d <= 28; $d++)
                        <option value="{{ $d }}">{{ $d }}</option>
                    @endfor
                    <option value="0">Last day of the month</option>
                </select>
                <small class="text-danger error-text day_of_month_error"></small>
            </div>

            <div class="col-12 mb-2">
                <label class="fw-semibold" style="font-size:11.5px;">Send To <span class="text-danger">*</span></label>
                <textarea class="form-control" name="recipients_text" rows="3" placeholder="name@company.com, accounts@example.com"></textarea>
                <small class="text-muted" style="font-size:10.5px;">Any email addresses, separated by commas or new lines (up to 20). Each person gets their own email.</small>
                <small class="text-danger error-text recipients_text_error d-block"></small>
            </div>
            <div class="col-12 mb-3">
                <label class="d-inline-flex align-items-center gap-1" style="font-size:12px;">
                    <input type="checkbox" name="send_to_me" value="1" id="scheduleSendToMe">
                    Also send to me ({{ auth()->user()->email }})
                </label>
            </div>

            <div class="col-12 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Email Subject</label>
                <input type="text" class="form-control" name="subject" maxlength="191" placeholder="Leave empty for: Report name – period | Company">
                <small class="text-danger error-text subject_error"></small>
            </div>
            <div class="col-12 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Message</label>
                <textarea class="form-control" name="message" rows="3" maxlength="2000" placeholder="Optional note shown in the email"></textarea>
                <small class="text-danger error-text message_error"></small>
            </div>
        </div>

        <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-primary btn-sm" id="scheduleSaveBtn"><i class="feather-save me-1"></i> <span id="scheduleSaveLabel">Save Schedule</span></button>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
        </div>
    </form>
</x-ui.drawer>

@push('schedule-scripts')
    <script>
        (function () {
            const form = document.getElementById('scheduleForm');
            const errorBox = document.getElementById('scheduleFormError');
            const reportSelect = document.getElementById('scheduleReport');
            const periodSelect = document.getElementById('schedulePeriod');
            const freqSelect = document.getElementById('scheduleFrequency');
            const myEmail = @json(strtolower((string) auth()->user()->email));
            const drawer = () => bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('scheduleDrawer'));

            function fillPeriods(selected) {
                const opt = reportSelect.selectedOptions[0];
                const periods = opt && opt.dataset.periods ? JSON.parse(opt.dataset.periods) : {};
                const keys = Object.keys(periods);
                periodSelect.innerHTML = '';
                keys.forEach(k => periodSelect.add(new Option(periods[k], k)));
                if (selected && keys.includes(selected)) periodSelect.value = selected;
                periodSelect.classList.toggle('d-none', keys.length === 0);
                document.getElementById('schedulePeriodNone').classList.toggle('d-none', keys.length > 0 || !reportSelect.value);
            }

            function showFrequency() {
                ['daily', 'weekly', 'monthly'].forEach(f =>
                    form.querySelectorAll('.freq-' + f).forEach(el => el.classList.toggle('d-none', freqSelect.value !== f)));
            }

            reportSelect.addEventListener('change', () => fillPeriods(''));
            freqSelect.addEventListener('change', showFrequency);

            function clearErrors() {
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
                form.querySelectorAll('.error-text').forEach(el => { el.textContent = ''; });
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            }

            window.openScheduleForm = function (s) {
                form.reset();
                clearErrors();
                document.getElementById('scheduleId').value = s ? s.id : '';
                document.getElementById('scheduleDrawerLabel').textContent = s ? 'Edit Schedule' : 'New Schedule';
                document.getElementById('scheduleSaveLabel').textContent = s ? 'Update Schedule' : 'Save Schedule';
                if (s) {
                    form.elements.name.value = s.name;
                    reportSelect.value = s.report_key;
                    if (form.elements.branch_id) form.elements.branch_id.value = s.branch_id || '';
                    form.elements.department_id.value = s.department_id || '';
                    freqSelect.value = s.frequency;
                    form.elements.send_time.value = s.send_time;
                    const days = (s.weekdays || []).map(Number);
                    form.querySelectorAll('input[name="weekdays[]"]').forEach(cb => {
                        cb.checked = s.frequency !== 'daily' || days.length === 0 || days.includes(Number(cb.value));
                    });
                    if (s.frequency === 'weekly' && days.length) form.elements.weekly_day.value = days[0];
                    if (s.frequency === 'monthly') form.elements.day_of_month.value = s.day_of_month ?? 1;
                    // "Send to me" is shown as the tick box, not as an address in the list.
                    const others = (s.recipients || []).filter(e => e !== myEmail);
                    document.getElementById('scheduleSendToMe').checked = others.length !== (s.recipients || []).length;
                    form.elements.recipients_text.value = others.join(', ');
                    form.elements.subject.value = s.subject || '';
                    form.elements.message.value = s.message || '';
                } else {
                    document.getElementById('scheduleSendToMe').checked = true;
                }
                fillPeriods(s ? s.period : '');
                showFrequency();
                drawer().show();
            };

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                clearErrors();
                const id = document.getElementById('scheduleId').value;
                const btn = document.getElementById('scheduleSaveBtn');
                btn.disabled = true;
                $.ajax({
                    url: id ? scheduleUrl + '/' + id : scheduleUrl,
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
                    data: $(form).serialize(),
                    success: r => { toastr.success(r.message); drawer().hide(); setTimeout(() => location.reload(), 900); },
                    error: x => {
                        const errors = x.responseJSON?.errors;
                        if (errors) {
                            Object.entries(errors).forEach(([field, messages]) => {
                                const slot = form.querySelector('.' + field.replace(/\..*$/, '') + '_error');
                                if (slot && !slot.textContent) slot.textContent = messages[0];
                                if (form.elements[field]) form.elements[field].classList.add('is-invalid');
                            });
                        } else {
                            errorBox.textContent = x.responseJSON?.message || 'Something went wrong. Please try again.';
                            errorBox.classList.remove('d-none');
                        }
                    },
                    complete: () => { btn.disabled = false; },
                });
            });
        })();
    </script>
@endpush
