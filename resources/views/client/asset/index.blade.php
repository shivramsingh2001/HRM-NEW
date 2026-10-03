@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== STAT CARDS (single blue theme) ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }

        .stats-card {
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

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-mid));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stats-card:hover::before {
            opacity: 1;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.1);
            border-color: #d1d5db;
        }

        .stats-icon-wrapper {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: var(--primary-light);
            transition: all 0.3s ease;
        }

        .stats-icon-wrapper i {
            color: var(--icon-color, #0D6EFD);
            font-size: 15px;
        }

        .stats-card:hover .stats-icon-wrapper {
            transform: scale(1.05);
        }

        .stats-content {
            flex: 1;
            min-width: 0;
        }

        .stats-amount-main {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
            margin-bottom: 1px;
            letter-spacing: -0.5px;
        }

        .stats-label {
            font-size: 10.5px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0;
        }



















        /* ==================== REGISTER ASSET BUTTON ==================== */
        .btn-register-asset {
            background: var(--primary-mid);
            border: none;
            padding: 8px 18px;
            border-radius: var(--radius-md, 10px);
            font-weight: 600;
            font-size: 12px;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(13, 110, 253, .25);
            transition: all .2s ease;
        }

        .btn-register-asset:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, .35);
        }




        .badge-info-custom {
            background: var(--primary-light) !important;
            color: var(--primary) !important;
            font-weight: 600 !important;
            padding: 4px 12px !important;
            border-radius: 16px !important;
            border: 1px solid var(--border-focus) !important;
            font-size: 11px !important;
        }

        .asset-table td,
        .asset-table th {
            font-size: 11.5px;
            vertical-align: middle;
        }



        .asset-info {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 160px;
        }

        .asset-avatar {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 14px;
            flex-shrink: 0;
        }

        .asset-details {
            line-height: 1.3;
            min-width: 0;
        }

        .asset-name-text {
            font-weight: 600;
            color: #0f172a;
            font-size: 12px;
        }

        .asset-name-text a {
            color: #0f172a;
            text-decoration: none;
        }

        .asset-name-text a:hover {
            color: var(--primary);
        }

        .asset-code {
            font-family: monospace;
            font-size: 10.5px;
            color: var(--primary);
            font-weight: 600;
        }



        .action-btn-group {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 4px;
        }

        .action-btn-sm {
            width: 26px;
            height: 26px;
            border-radius: 7px;
            border: 1px solid var(--border-focus);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            cursor: pointer;
            font-size: 11px;
            text-decoration: none;
            background: var(--primary-light);
            color: var(--primary);
        }

        .action-btn-sm:hover {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
        }

        .action-btn-sm.danger {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .action-btn-sm.danger:hover {
            background: #dc2626;
            color: white;
        }

        #bulkBar {
            font-size: 11.5px;
            border-radius: 8px;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }




        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Assets" subtitle="Register, assign, and track company assets end-to-end.">
        <x-slot:actions>
            @if (app(\App\Services\RbacService::class)->can(auth()->user(), 'assets', 'create'))
                <button class="btn-register-asset" data-bs-toggle="offcanvas" data-bs-target="#registerAssetDrawer">
                    <i class="feather-plus"></i> Register Asset
                </button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-hard-drive"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $stats['total'] }}</div>
                    <div class="stats-label">Total</div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-user-check"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $stats['assigned'] }}</div>
                    <div class="stats-label">Assigned</div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-check-circle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $stats['available'] }}</div>
                    <div class="stats-label">Available</div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-tool"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $stats['in_repair'] }}</div>
                    <div class="stats-label">In Repair</div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-trash-2"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $stats['disposed'] }}</div>
                    <div class="stats-label">Disposed</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Assets
                </div>
                @if (request()->hasAny(['search', 'category_id', 'status', 'branch_id', 'vendor_id']))
                    <a href="{{ route('assets.index') }}" class="reset-btn" style="height: auto; padding: 4px 12px; font-size: 12px;">
                        <i class="feather-x"></i> Clear Filters
                    </a>
                @endif
            </div>

            <form action="{{ route('assets.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item search">
                        <div class="search-wrapper">
                            <i class="feather-search"></i>
                            <input type="text" name="search" placeholder="Search name, code, serial..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="filter-item select-filter">
                        <select name="category_id" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}" {{ request('category_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item select-filter">
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            @foreach (\App\Models\Asset::STATUSES as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item select-filter">
                        <select name="branch_id" onchange="this.form.submit()">
                            <option value="">All Branches</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item select-filter">
                        <select name="vendor_id" onchange="this.form.submit()">
                            <option value="">All Vendors</option>
                            @foreach ($vendors as $v)
                                <option value="{{ $v->id }}" {{ request('vendor_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <a href="{{ route('assets.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div id="bulkBar" class="alert alert-secondary d-none align-items-center gap-2 py-2 px-2 flex-wrap">
            <span><strong id="bulkCount">0</strong> selected</span>
            <select class="form-control form-control-sm" id="bulkStatus" style="width:auto;font-size:11.5px;">
                <option value="">Set status…</option>
                <option value="available">Available</option>
                <option value="retired">Retired</option>
            </select>
            <button class="btn btn-sm btn-primary" id="applyBulk">Apply</button>
            <button class="btn btn-sm btn-light ms-auto" id="clearSel">Clear</button>
        </div>

        <!-- Assets Table -->
        <div class="card">
            <div class="card-body p-0">
                @if ($assets->isEmpty())
                    <x-ui.empty-state icon="hard-drive" title="No assets found" subtitle="Register your first asset or adjust the filters above." />
                @else
                    @php
                        $canManageAssets = app(\App\Services\RbacService::class)->can(auth()->user(), 'assets', 'manage');
                        $canEditAssets = app(\App\Services\RbacService::class)->can(auth()->user(), 'assets', 'edit');
                    @endphp
                    <x-ui.data-table>
                        <thead>
                            <tr class="asset-table">
                                <th style="width:28px"><input type="checkbox" id="selectAll"></th>
                                <th>Asset</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Branch</th>
                                <th>Assigned To</th>
                                <th style="width:220px">Action</th>
                            </tr>
                        </thead>
                        <tbody class="asset-table">
                            @foreach ($assets as $asset)
                                <tr>
                                    <td><input type="checkbox" class="row-sel" value="{{ $asset->id }}"></td>
                                    <td>
                                        <div class="asset-info">
                                            <div class="asset-avatar"><i class="feather-hard-drive"></i></div>
                                            <div class="asset-details">
                                                <div class="asset-name-text">
                                                    <a href="{{ route('assets.show', encrypt($asset->id)) }}">{{ $asset->name }}</a>
                                                </div>
                                                <div class="asset-code">{{ $asset->asset_code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $asset->category->name ?? '-' }}</td>
                                    <td><x-ui.status-badge :status="$asset->status" /></td>
                                    <td>{{ $asset->branch->name ?? '-' }}</td>
                                    <td>{{ $asset->currentAssigneeUser->name ?? '-' }}</td>
                                    <td>
                                        <div class="action-btn-group">
                                            <a href="{{ route('assets.show', encrypt($asset->id)) }}" class="action-btn-sm" title="View Asset">
                                                <i class="feather-eye"></i>
                                            </a>

                                            @if ($canManageAssets && $asset->status === 'available')
                                                <button type="button" class="action-btn-sm" title="Assign to Employee" onclick="openAssign('{{ encrypt($asset->id) }}')">
                                                    <i class="feather-user-plus"></i>
                                                </button>
                                            @endif

                                            @if ($canManageAssets && $asset->status === 'assigned')
                                                <button type="button" class="action-btn-sm" title="Transfer" onclick="openTransfer('{{ encrypt($asset->id) }}')">
                                                    <i class="feather-shuffle"></i>
                                                </button>
                                            @endif

                                            @if (in_array($asset->status, ['assigned', 'pending_acceptance']) && $asset->currentAssignment)
                                                <button type="button" class="action-btn-sm" title="Return" onclick="openReturn('{{ encrypt($asset->currentAssignment->id) }}')">
                                                    <i class="feather-corner-down-left"></i>
                                                </button>
                                            @endif

                                            @if ($canManageAssets && $asset->status === 'pending_acceptance' && $asset->currentAssignment)
                                                <button type="button" class="action-btn-sm" title="Force Accept" onclick="forceAccept('{{ encrypt($asset->currentAssignment->id) }}')">
                                                    <i class="feather-check-circle"></i>
                                                </button>
                                            @endif

                                            @if ($canManageAssets && in_array($asset->status, ['available', 'assigned']))
                                                <button type="button" class="action-btn-sm" title="Send for Repair" onclick="openRepair('{{ encrypt($asset->id) }}')">
                                                    <i class="feather-tool"></i>
                                                </button>
                                            @endif

                                            @if ($canManageAssets && $asset->status === 'in_repair' && $asset->activeRepair)
                                                <button type="button" class="action-btn-sm" title="Complete Repair" onclick="openCompleteRepair('{{ encrypt($asset->activeRepair->id) }}')">
                                                    <i class="feather-check-square"></i>
                                                </button>
                                            @endif

                                            @if ($canEditAssets && in_array($asset->status, ['available', 'assigned', 'in_repair']))
                                                <button type="button" class="action-btn-sm" title="Report Damage / Lost" onclick="openDamage('{{ encrypt($asset->id) }}')">
                                                    <i class="feather-alert-triangle"></i>
                                                </button>
                                            @endif

                                            @if ($canManageAssets && in_array($asset->status, ['damaged', 'lost']) && $asset->activeDamageReport)
                                                <button type="button" class="action-btn-sm" title="Resolve Damage / Loss" onclick="openResolveDamage('{{ encrypt($asset->activeDamageReport->id) }}')">
                                                    <i class="feather-check"></i>
                                                </button>
                                            @endif

                                            @if ($canManageAssets && in_array($asset->status, ['available', 'damaged', 'lost']))
                                                <button type="button" class="action-btn-sm" title="Retire" onclick="retireAsset('{{ encrypt($asset->id) }}')">
                                                    <i class="feather-archive"></i>
                                                </button>
                                            @endif

                                            @if ($canManageAssets && in_array($asset->status, ['available', 'retired', 'damaged', 'lost']))
                                                <button type="button" class="action-btn-sm danger" title="Dispose" onclick="openDispose('{{ encrypt($asset->id) }}')">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            @endif

                                            @if ($canEditAssets)
                                                <button type="button" class="action-btn-sm" title="Upload Attachment" onclick="openAttachment('{{ encrypt($asset->id) }}')">
                                                    <i class="feather-upload"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.data-table>
                    <div class="p-3">
                        {{ $assets->links('pagination::bootstrap-4') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    @include('client.asset.partials.registration-drawer', ['categories' => $categories, 'types' => $types, 'branches' => $branches, 'vendors' => $vendors])
    @include('client.asset.partials.action-modals', ['users' => $users, 'branches' => $branches, 'vendors' => $vendors])

    {{-- ==================== UPLOAD ATTACHMENT (list-page only; the detail page has its own inline uploader) ==================== --}}
    <div class="modal fade-scale" id="attachmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="mb-0"><span class="fs-14 fw-bold">Upload Attachment</span></h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal"><i class="feather-x text-danger"></i></a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0"><div class="card-body">
                        <form id="listAttachmentForm" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="fw-semibold">File *</label>
                                <input type="file" class="form-control" name="file" required>
                            </div>
                            <div class="mb-3">
                                <label class="fw-semibold">Type</label>
                                <select class="form-control" name="context">
                                    <option value="general">General</option>
                                    <option value="purchase_invoice">Purchase Invoice</option>
                                    <option value="warranty_card">Warranty Card</option>
                                    <option value="handover_form">Handover Form</option>
                                    <option value="damage_photo">Damage Photo</option>
                                    <option value="repair_invoice">Repair Invoice</option>
                                </select>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Upload</button>
                                <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        const csrfToken = $('meta[name="csrf-token"]').attr('content');

        // ==================== BULK ACTIONS ====================
        const selectedIds = () => $('.row-sel:checked').map((i, el) => +el.value).get();

        function syncBulk() {
            const n = selectedIds().length;
            $('#bulkBar').toggleClass('d-none', n === 0).toggleClass('d-flex', n > 0);
            $('#bulkCount').text(n);
        }

        $(document).on('change', '.row-sel', syncBulk);
        $('#selectAll').on('change', function() {
            $('.row-sel').prop('checked', this.checked);
            syncBulk();
        });
        $('#clearSel').on('click', function() {
            $('.row-sel, #selectAll').prop('checked', false);
            syncBulk();
        });

        $('#applyBulk').on('click', function() {
            const ids = selectedIds();
            const status = $('#bulkStatus').val();
            if (!ids.length || !status) {
                toastr.warning('Select assets and a status.');
                return;
            }

            $.ajax({
                url: "{{ route('assets.bulk-action') }}",
                type: 'POST',
                data: { ids: ids, field: 'status', value: status, _token: csrfToken },
                success: function(res) {
                    toastr.success(res.message);
                    setTimeout(() => location.reload(), 800);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Bulk update failed.');
                }
            });
        });

        // ==================== REGISTER ASSET ====================
        $('#registerAssetForm').on('submit', function(e) {
            e.preventDefault();
            $('.error-text').text('');
            $('#registerFormError').addClass('d-none').text('');

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                success: function(response) {
                    if (response.success) {
                        const drawerEl = document.getElementById('registerAssetDrawer');
                        bootstrap.Offcanvas.getInstance(drawerEl)?.hide();
                        toastr.success(response.message);
                        setTimeout(() => location.reload(), 1000);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) { $('.' + key + '_error').text(value[0]); });
                        toastr.error('Please fix the validation errors');
                    } else {
                        $('#registerFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                    }
                }
            });
        });

        $('#registerAssetDrawer').on('hidden.bs.offcanvas', function() {
            $('#registerAssetForm')[0].reset();
            $('.error-text').text('');
            $('#registerFormError').addClass('d-none').text('');
        });

        // ==================== EMPLOYEE PICKER (Select2 inside a Bootstrap modal
        // needs its own dropdownParent + explicit width — same fix as
        // shift/roster.blade.php's #user_ids — otherwise the popup is clipped
        // behind the modal and the box collapses to 0 width while hidden) ====
        function initEmployeeSelect2(selector, modalSelector, placeholder) {
            if (!$.fn.select2) return;
            const $el = $(selector);
            if (!$el.length) return;
            if ($el.hasClass('select2-hidden-accessible')) { $el.select2('destroy'); }
            $el.select2({ placeholder: placeholder, allowClear: true, width: '100%', dropdownParent: $(modalSelector) });
        }
        initEmployeeSelect2('#assignModal select[name="user_id"]', '#assignModal', 'Search employee by name or ID…');
        initEmployeeSelect2('#transferModal select[name="to_user_id"]', '#transferModal', 'Search employee by name or ID…');

        // ==================== ROW LIFECYCLE ACTIONS ====================
        // Base "…/company-assets" URL, derived from the named route rather than
        // hardcoded, since GET/POST /assets/* collides with the public/assets/
        // static folder and never reaches Laravel (see routes/web.php).
        const assetsBase = "{{ route('assets.index') }}";

        function postAction(url, data, successMsg) {
            $.ajax({
                url: url,
                type: 'POST',
                data: data,
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                success: function(res) {
                    $('.modal.show').each(function() { bootstrap.Modal.getInstance(this)?.hide(); });
                    toastr.success(res.message || successMsg);
                    setTimeout(() => location.reload(), 900);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Action failed.');
                }
            });
        }

        function showModal(id) {
            new bootstrap.Modal(document.getElementById(id)).show();
        }

        function openAssign(assetId) { $('#assignForm').data('asset-id', assetId); showModal('assignModal'); }
        function openTransfer(assetId) { $('#transferForm').data('asset-id', assetId); showModal('transferModal'); }
        function openReturn(assignmentId) { $('#returnForm').data('assignment-id', assignmentId); showModal('returnModal'); }
        function openRepair(assetId) { $('#repairForm').data('asset-id', assetId); showModal('repairModal'); }
        function openCompleteRepair(repairId) { $('#completeRepairForm').data('repair-id', repairId); showModal('completeRepairModal'); }
        function openDamage(assetId) { $('#damageForm').data('asset-id', assetId); showModal('damageModal'); }
        function openResolveDamage(reportId) { $('#resolveDamageForm').data('report-id', reportId); showModal('resolveDamageModal'); }
        function openDispose(assetId) { $('#disposeForm').data('asset-id', assetId); showModal('disposeModal'); }
        function openAttachment(assetId) { $('#listAttachmentForm').data('asset-id', assetId); showModal('attachmentModal'); }

        function forceAccept(assignmentId) {
            if (!confirm('Force-accept this asset on behalf of the assignee?')) return;
            postAction(assetsBase + '/assignments/' + assignmentId + '/accept', {}, 'Asset accepted.');
        }

        function retireAsset(assetId) {
            const reason = prompt('Reason for retiring this asset (optional):') || '';
            postAction(assetsBase + '/' + assetId + '/retire', { reason: reason }, 'Asset retired.');
        }

        $('#assignForm').on('submit', function(e) {
            e.preventDefault();
            postAction(assetsBase + '/' + $(this).data('asset-id') + '/assign', $(this).serialize(), 'Asset assigned.');
        });

        $('#transferForm').on('submit', function(e) {
            e.preventDefault();
            postAction(assetsBase + '/' + $(this).data('asset-id') + '/transfer', $(this).serialize(), 'Asset transferred.');
        });

        $('#returnForm').on('submit', function(e) {
            e.preventDefault();
            postAction(assetsBase + '/assignments/' + $(this).data('assignment-id') + '/return', $(this).serialize(), 'Asset returned.');
        });

        $('#repairForm').on('submit', function(e) {
            e.preventDefault();
            postAction(assetsBase + '/' + $(this).data('asset-id') + '/send-for-repair', $(this).serialize(), 'Sent for repair.');
        });

        $('#completeRepairForm').on('submit', function(e) {
            e.preventDefault();
            postAction(assetsBase + '/repairs/' + $(this).data('repair-id') + '/complete', $(this).serialize(), 'Repair completed.');
        });

        $('#damageForm').on('submit', function(e) {
            e.preventDefault();
            postAction(assetsBase + '/' + $(this).data('asset-id') + '/report-damage', $(this).serialize(), 'Report recorded.');
        });

        $('#resolveDamageForm').on('submit', function(e) {
            e.preventDefault();
            postAction(assetsBase + '/damage-reports/' + $(this).data('report-id') + '/resolve', $(this).serialize(), 'Report resolved.');
        });

        $('#disposeForm').on('submit', function(e) {
            e.preventDefault();
            postAction(assetsBase + '/' + $(this).data('asset-id') + '/dispose', $(this).serialize(), 'Asset disposed.');
        });

        $('#listAttachmentForm').on('submit', function(e) {
            e.preventDefault();
            const assetId = $(this).data('asset-id');
            const formData = new FormData(this);
            $.ajax({
                url: assetsBase + '/' + assetId + '/attachments',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                success: function(res) {
                    bootstrap.Modal.getInstance(document.getElementById('attachmentModal'))?.hide();
                    toastr.success(res.message || 'File uploaded.');
                    setTimeout(() => location.reload(), 900);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Upload failed.');
                }
            });
        });

        $('#assignModal, #transferModal, #returnModal, #repairModal, #completeRepairModal, #damageModal, #resolveDamageModal, #disposeModal, #attachmentModal')
            .on('hidden.bs.modal', function() {
                $(this).find('form')[0]?.reset();
            });
    </script>
@endsection
