<x-ui.drawer id="registerAssetDrawer" title="Register Asset" width="480px">
    <form action="{{ route('assets.store') }}" id="registerAssetForm">
        @csrf
        <div id="registerFormError" class="alert alert-danger d-none" style="font-size:11.5px;"></div>

        <h6 class="fw-semibold mb-2" style="font-size:12px;color:var(--primary);">Identity</h6>
        <div class="row">
            <div class="col-12 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Asset Name *</label>
                <input type="text" class="form-control" name="name" required placeholder="e.g. Dell Latitude 5420">
                <small class="text-danger error-text name_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Category</label>
                <select class="form-control" name="asset_category_id">
                    <option value="">-- Uncategorized --</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <small class="text-danger error-text asset_category_id_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Type</label>
                <select class="form-control" name="asset_type_id">
                    <option value="">-- Select type --</option>
                    @foreach ($types as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                <small class="text-danger error-text asset_type_id_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Brand</label>
                <input type="text" class="form-control" name="brand" placeholder="e.g. Dell">
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Model Number</label>
                <input type="text" class="form-control" name="model_number" placeholder="e.g. Latitude 5420">
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Serial Number</label>
                <input type="text" class="form-control" name="serial_number" placeholder="e.g. SN-2026-00123">
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Condition</label>
                <select class="form-control" name="condition">
                    <option value="">-- Select condition --</option>
                    <option value="new">New</option>
                    <option value="good">Good</option>
                    <option value="fair">Fair</option>
                    <option value="poor">Poor</option>
                </select>
                <small class="text-danger error-text condition_error"></small>
            </div>
            <div class="col-12 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Description</label>
                <textarea class="form-control" name="description" rows="2" placeholder="Additional details about this asset..."></textarea>
            </div>
        </div>

        <h6 class="fw-semibold mb-2 mt-2" style="font-size:12px;color:var(--primary);">Purchase &amp; Warranty</h6>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Vendor</label>
                <select class="form-control" name="vendor_id">
                    <option value="">-- Select vendor --</option>
                    @foreach ($vendors as $v)
                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Purchase Date</label>
                <input type="date" class="form-control" name="purchase_date" max="{{ date('Y-m-d') }}" placeholder="Select purchase date">
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Purchase Cost</label>
                <input type="number" step="0.01" min="0" class="form-control" name="purchase_cost" placeholder="0.00">
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Invoice Number</label>
                <input type="text" class="form-control" name="invoice_number" placeholder="e.g. INV-2026-001">
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Warranty Start</label>
                <input type="date" class="form-control" name="warranty_start_date" placeholder="Select start date">
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Warranty End</label>
                <input type="date" class="form-control" name="warranty_end_date" placeholder="Select end date">
                <small class="text-danger error-text warranty_end_date_error"></small>
            </div>
        </div>

        <h6 class="fw-semibold mb-2 mt-2" style="font-size:12px;color:var(--primary);">Branch &amp; Location</h6>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Branch</label>
                <select class="form-control" name="branch_id">
                    <option value="">-- Select branch --</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Location Notes</label>
                <input type="text" class="form-control" name="location_notes" placeholder="e.g. 3rd floor, Room 302">
            </div>
        </div>

        <div class="mb-3">
            <label class="fw-semibold" style="font-size:11.5px;">Notes</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="Any additional notes about this asset..."></textarea>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="feather-check me-1"></i> Register Asset
            </button>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
        </div>
    </form>
</x-ui.drawer>
