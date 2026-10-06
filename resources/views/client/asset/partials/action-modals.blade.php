{{-- Small, single-purpose lifecycle action modals for the Asset detail page.
     All use the app's .compact-modal chrome (380px, matching every other
     small modal in the app). --}}

@once
    {{-- Employee picker options: avatar + name (ID) + email — see docs/ui-guidelines.md § Employee picker options --}}
    <style>
        .emp-opt { display: flex; align-items: center; gap: 8px; }
        .emp-opt-avatar {
            width: 26px; height: 26px; border-radius: 50%; flex: none;
            background: #EFF6FF; color: #0D6EFD; font-size: 10px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }
        .emp-opt-text { display: flex; flex-direction: column; min-width: 0; flex: 1; line-height: 1.25; }
        .emp-opt-name { font-size: 11.5px; font-weight: 600; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .emp-opt-email { font-size: 10px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .emp-opt-dropdown .select2-results__option { padding: 6px 10px; }
        .emp-opt-dropdown .select2-results__option--highlighted,
        .emp-opt-dropdown .select2-results__option--highlighted[aria-selected] { background: #EFF6FF !important; color: #0f172a !important; }
        .emp-opt-dropdown .select2-search__field { font-size: 11.5px; padding: 5px 8px; border-radius: 6px; }
        /* The closed box: the picked name stays inside it, on one line. */
        .emp-opt-select .select2-selection--single { height: 34px !important; padding: 0 28px 0 10px !important; display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 8px; background-image: none; }
        .emp-opt-select .select2-selection--single .select2-selection__rendered { padding: 0 !important; line-height: 32px !important; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
        .emp-opt-select .select2-selection--single .select2-selection__arrow { height: 32px !important; top: 1px !important; right: 6px !important; }
    </style>
@endonce

{{-- ==================== ASSIGN ==================== --}}
<div class="modal fade-scale" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm compact-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="mb-0"><span class="fs-14 fw-bold">Assign Asset</span></h2>
                <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal"><i class="feather-x text-danger"></i></a>
            </div>
            <div class="modal-body p-0">
                <div class="card m-0"><div class="card-body">
                    <form id="assignForm">
                        <div class="mb-3">
                            <label class="fw-semibold">Employee *</label>
                            <select class="form-control" name="user_id" required>
                                <option value="">Select employee</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" data-name="{{ $u->name }}" data-empid="{{ $u->employee_id }}" data-email="{{ $u->email ?? '' }}">{{ $u->name }} ({{ $u->employee_id }}) {{ $u->email ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Expected Return Date</label>
                            <input type="date" class="form-control" name="expected_return_date">
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Assign</button>
                            <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
</div>

{{-- ==================== TRANSFER ==================== --}}
<div class="modal fade-scale" id="transferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm compact-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="mb-0"><span class="fs-14 fw-bold">Transfer Asset</span></h2>
                <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal"><i class="feather-x text-danger"></i></a>
            </div>
            <div class="modal-body p-0">
                <div class="card m-0"><div class="card-body">
                    <form id="transferForm">
                        <div class="mb-3">
                            <label class="fw-semibold">New Employee *</label>
                            <select class="form-control" name="to_user_id" required>
                                <option value="">Select employee</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" data-name="{{ $u->name }}" data-empid="{{ $u->employee_id }}" data-email="{{ $u->email ?? '' }}">{{ $u->name }} ({{ $u->employee_id }}) {{ $u->email ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">New Branch</label>
                            <select class="form-control" name="to_branch_id">
                                <option value="">-- Keep Current --</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Reason</label>
                            <textarea class="form-control" name="reason" rows="2"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Transfer</button>
                            <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
</div>

{{-- ==================== RETURN ==================== --}}
<div class="modal fade-scale" id="returnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm compact-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="mb-0"><span class="fs-14 fw-bold">Return Asset</span></h2>
                <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal"><i class="feather-x text-danger"></i></a>
            </div>
            <div class="modal-body p-0">
                <div class="card m-0"><div class="card-body">
                    <form id="returnForm">
                        <div class="mb-3">
                            <label class="fw-semibold">Condition on Return</label>
                            <select class="form-control" name="return_condition">
                                <option value="">-- Select --</option>
                                <option value="new">New</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                                <option value="damaged">Damaged</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Return</button>
                            <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
</div>

{{-- ==================== SEND FOR REPAIR ==================== --}}
<div class="modal fade-scale" id="repairModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm compact-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="mb-0"><span class="fs-14 fw-bold">Send for Repair</span></h2>
                <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal"><i class="feather-x text-danger"></i></a>
            </div>
            <div class="modal-body p-0">
                <div class="card m-0"><div class="card-body">
                    <form id="repairForm">
                        <div class="mb-3">
                            <label class="fw-semibold">Vendor</label>
                            <select class="form-control" name="sent_to_vendor_id">
                                <option value="">-- Select --</option>
                                @foreach ($vendors as $v)
                                    <option value="{{ $v->id }}">{{ $v->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Issue Description</label>
                            <textarea class="form-control" name="issue_description" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Expected Return Date</label>
                            <input type="date" class="form-control" name="expected_return_date">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Send</button>
                            <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
</div>

{{-- ==================== COMPLETE REPAIR ==================== --}}
<div class="modal fade-scale" id="completeRepairModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm compact-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="mb-0"><span class="fs-14 fw-bold">Complete Repair</span></h2>
                <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal"><i class="feather-x text-danger"></i></a>
            </div>
            <div class="modal-body p-0">
                <div class="card m-0"><div class="card-body">
                    <form id="completeRepairForm">
                        <div class="mb-3">
                            <label class="fw-semibold">Repair Cost</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="repair_cost">
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Resolution Notes</label>
                            <textarea class="form-control" name="resolution_notes" rows="2"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Mark Complete</button>
                            <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
</div>

{{-- ==================== REPORT DAMAGE / LOST ==================== --}}
<div class="modal fade-scale" id="damageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm compact-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="mb-0"><span class="fs-14 fw-bold">Report Damage / Lost</span></h2>
                <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal"><i class="feather-x text-danger"></i></a>
            </div>
            <div class="modal-body p-0">
                <div class="card m-0"><div class="card-body">
                    <form id="damageForm">
                        <div class="mb-3">
                            <label class="fw-semibold">Type *</label>
                            <select class="form-control" name="type" required>
                                <option value="damaged">Damaged</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Estimated Loss Value</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="estimated_loss_value">
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" name="is_chargeable" value="1" id="isChargeable">
                            <label class="form-check-label" for="isChargeable">Chargeable to employee</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Report</button>
                            <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
</div>

{{-- ==================== RESOLVE DAMAGE ==================== --}}
<div class="modal fade-scale" id="resolveDamageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm compact-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="mb-0"><span class="fs-14 fw-bold">Resolve Report</span></h2>
                <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal"><i class="feather-x text-danger"></i></a>
            </div>
            <div class="modal-body p-0">
                <div class="card m-0"><div class="card-body">
                    <form id="resolveDamageForm">
                        <div class="mb-3">
                            <label class="fw-semibold">Resolution *</label>
                            <select class="form-control" name="resolution" required>
                                <option value="repaired">Repaired</option>
                                <option value="replaced">Replaced</option>
                                <option value="recovered">Recovered</option>
                                <option value="written_off">Written Off</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Charged Amount</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="charged_amount">
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Resolve</button>
                            <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
</div>

{{-- ==================== DISPOSE ==================== --}}
<div class="modal fade-scale" id="disposeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm compact-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="mb-0"><span class="fs-14 fw-bold">Dispose Asset</span></h2>
                <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal"><i class="feather-x text-danger"></i></a>
            </div>
            <div class="modal-body p-0">
                <div class="card m-0"><div class="card-body">
                    <form id="disposeForm">
                        <div class="mb-3">
                            <label class="fw-semibold">Method</label>
                            <select class="form-control" name="method">
                                <option value="">-- Select --</option>
                                <option value="sold">Sold</option>
                                <option value="scrapped">Scrapped</option>
                                <option value="donated">Donated</option>
                                <option value="write_off">Write Off</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Sale Value</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="sale_value">
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Buyer / Recipient</label>
                            <input type="text" class="form-control" name="buyer_or_recipient">
                        </div>
                        <div class="mb-3">
                            <label class="fw-semibold">Reason</label>
                            <textarea class="form-control" name="reason" rows="2"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Dispose</button>
                            <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
</div>
