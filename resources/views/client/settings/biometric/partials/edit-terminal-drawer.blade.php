<x-ui.drawer id="editTerminalDrawer" title="Edit Terminal" width="480px">
    <form id="editTerminalForm">
        @csrf
        <input type="hidden" id="edit_id" name="id">
        <div id="editTerminalFormError" class="alert alert-danger d-none" style="font-size:11.5px;"></div>

        <h6 class="fw-semibold mb-2" style="font-size:12px;color:var(--primary);">Identity</h6>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Serial number</label>
                <input type="text" class="form-control" id="edit_serial_number" readonly>
                <small class="text-muted">Serial number can't be changed after registration.</small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Name *</label>
                <input type="text" class="form-control" name="name" id="edit_name" required>
                <small class="text-danger error-text edit_name_error"></small>
            </div>
        </div>

        <h6 class="fw-semibold mb-2 mt-2" style="font-size:12px;color:var(--primary);">Connection</h6>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">LAN IP <span class="text-muted">(or blank)</span></label>
                <input type="text" class="form-control" name="ip_address" id="edit_ip_address">
                <small class="text-danger error-text edit_ip_address_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">P2P UID <span class="text-muted">(or blank)</span></label>
                <input type="text" class="form-control" name="p2p_uid" id="edit_p2p_uid">
                <small class="text-danger error-text edit_p2p_uid_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Site timezone</label>
                <select class="form-control" name="site_timezone" id="edit_site_timezone">
                    <option value="">—</option>
                    @foreach ($timezones as $tz)
                        <option value="{{ $tz }}">{{ $tz }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Attendance location</label>
                <select class="form-control" name="branch_id" id="edit_branch_id">
                    <option value="">—</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <h6 class="fw-semibold mb-2 mt-2" style="font-size:12px;color:var(--primary);">Behavior</h6>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Direction mode</label>
                <select class="form-control" name="direction_mode" id="edit_direction_mode">
                    <option value="auto">auto (first punch = in)</option>
                    <option value="by_verify_mode">from device verify-mode</option>
                    <option value="in">this reader is IN only</option>
                    <option value="out">this reader is OUT only</option>
                </select>
                <small class="text-danger error-text edit_direction_mode_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Default privilege</label>
                <select class="form-control" name="default_privilege" id="edit_default_privilege">
                    <option value="0">User</option>
                    <option value="1">Enroller</option>
                    <option value="2">Manager</option>
                    <option value="3">Super Admin</option>
                </select>
                <small class="text-danger error-text edit_default_privilege_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Auto-provision employees</label>
                <select class="form-control" name="auto_provision" id="edit_auto_provision">
                    <option value="1">on — push HRM employees to the device</option>
                    <option value="0">off — map enroll numbers by hand</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Provision scope</label>
                <select class="form-control" name="provision_scope" id="edit_provision_scope">
                    <option value="tenant">all active employees</option>
                    <option value="branch">only this device's attendance location</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Allow direct onboarding</label>
                <select class="form-control" name="allow_direct_onboarding" id="edit_allow_direct_onboarding">
                    <option value="0">off — unknown device enrollments stay unmapped</option>
                    <option value="1">on — auto-create an HRM employee for unknown enrollments</option>
                </select>
                <small class="text-muted" style="font-size:10.5px;">Blocked once your plan's employee limit is reached.</small>
            </div>
            <div class="col-12 mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active" value="1">
                    <label class="form-check-label fw-semibold" style="font-size:11.5px;" for="edit_is_active">Active</label>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="feather-check me-1"></i> Save Changes
            </button>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
        </div>
    </form>
</x-ui.drawer>
