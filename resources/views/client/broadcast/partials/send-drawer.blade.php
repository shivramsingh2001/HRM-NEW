{{--
    "Send Broadcast" composer, now a slide-over drawer on the history page
    itself (resources/views/client/broadcast/index.blade.php) instead of a
    separate /broadcast/create page. Triggered from that page's page-header
    action button via data-bs-toggle="offcanvas" data-bs-target="#sendBroadcastDrawer".
    Submit/preview-count JS lives in index.blade.php's own @section('script-area'),
    matching the biometric terminal drawer's convention (script stays in the
    parent page, not the drawer partial).
--}}
<x-ui.drawer id="sendBroadcastDrawer" title="Send Broadcast" width="480px">
    <div id="broadcastAlert"></div>

    <form id="broadcastForm">
        @csrf

        <div class="bcast-card">
            <h6><i class="feather-edit-3"></i> Message</h6>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="bcast-form-label">Title <span class="req">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="255" required placeholder="e.g. Office closed for Diwali on Nov 1">
                </div>
                <div class="col-md-4">
                    <label class="bcast-form-label">Priority</label>
                    <select name="priority" class="form-control">
                        <option value="normal" selected>Normal</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="bcast-form-label">Message <span class="req">*</span></label>
                    <textarea name="body" class="form-control" rows="3" required placeholder="Write the notification message…"></textarea>
                </div>
                <div class="col-md-8">
                    <label class="bcast-form-label">Action link (optional)</label>
                    <input type="url" name="action_url" class="form-control" placeholder="https://…">
                </div>
                <div class="col-md-4">
                    <label class="bcast-form-label">Button label</label>
                    <input type="text" name="action_label" class="form-control" maxlength="100" placeholder="e.g. View Policy">
                </div>
                <div class="col-md-6">
                    <label class="bcast-form-label">Schedule for later (optional)</label>
                    <input type="datetime-local" name="scheduled_at" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="bcast-form-label">Expires at (optional)</label>
                    <input type="datetime-local" name="expires_at" class="form-control">
                </div>
                <div class="col-12">
                    <label class="bcast-form-label">Also deliver via</label>
                    <div>
                        <label class="bcast-role-check"><input type="checkbox" checked disabled> In-app (always on)</label>
                        <label class="bcast-role-check"><input type="checkbox" name="channels[]" value="fcm"> Push notification</label>
                        <label class="bcast-role-check"><input type="checkbox" name="channels[]" value="email"> Email</label>
                        <label class="bcast-role-check {{ $smsAvailable ? '' : 'text-muted' }}" title="{{ $smsAvailable ? '' : 'SMS requires a DLT-approved broadcast template — not configured yet for this environment.' }}">
                            <input type="checkbox" name="channels[]" value="sms" @disabled(! $smsAvailable)> SMS @unless($smsAvailable)<i class="feather-info ms-1" style="font-size:10px;"></i>@endunless
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="bcast-card">
            <h6><i class="feather-users"></i> Audience</h6>

            <div class="bcast-audience-mode">
                <input type="checkbox" id="bcastAll" name="all" value="1" style="width: 16px; height: 16px;">
                <label for="bcastAll" class="mb-0 fw-semibold" style="font-size: 12.5px;">Send to all active employees</label>
            </div>

            <div id="bcastDims" class="bcast-dims">
                <div>
                    <label class="bcast-form-label">Role</label>
                    <div>
                        @foreach (['admin' => 'Admin', 'hr' => 'HR', 'manager' => 'Manager', 'employee' => 'Employee'] as $val => $label)
                            <label class="bcast-role-check">
                                <input type="checkbox" name="role[]" value="{{ $val }}" class="bcast-audience-input"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="bcast-form-label">Department</label>
                    <select name="department_ids[]" class="form-control select2 bcast-audience-input" multiple>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="bcast-form-label">Designation</label>
                    <select name="designation_ids[]" class="form-control select2 bcast-audience-input" multiple>
                        @foreach ($designations as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($branchesEnabled)
                    <div>
                        <label class="bcast-form-label">Branch</label>
                        <select name="branch_ids[]" class="form-control select2 bcast-audience-input" multiple>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="mt-3">
                <label class="bcast-form-label">Also include these specific employees (added on top of the filters above)</label>
                <select name="user_ids[]" class="form-control select2 bcast-audience-input" multiple>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->employee_id ?? 'N/A' }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="bcast-submit-bar">
            <div class="bcast-count-chip" id="bcastCountChip">
                <i class="feather-users"></i>
                <span class="n" id="bcastCountValue">0</span>
                <span>recipient(s)</span>
            </div>
            <button type="submit" class="btn btn-primary" id="bcastSubmitBtn">
                <i class="feather-send me-1"></i> <span id="bcastSubmitLabel">Send Broadcast</span>
            </button>
        </div>
    </form>
</x-ui.drawer>
