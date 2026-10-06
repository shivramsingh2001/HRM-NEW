@extends('client.layout.master')

@section('style')
    <style>
        .asset-header {
            background: linear-gradient(135deg, var(--primary-mid, #0D6EFD), var(--primary-mid, #0D6EFD));
            color: white;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 14px;
        }

        .asset-header .asset-code-pill {
            background: rgba(255, 255, 255, .18);
            border-radius: 6px;
            padding: 2px 8px;
            font-family: monospace;
            font-size: 11px;
            display: inline-block;
        }

        .nav-tabs .nav-link { font-size: 12px; padding: 7px 14px; }
        .tab-content { padding: 14px; border: 1px solid var(--border, #e5e7eb); border-top: none; border-radius: 0 0 10px 10px; }

        .info-item { padding: 6px 0; border-bottom: 1px solid #f1f5f9; display: flex; font-size: 11.5px; }
        .info-item:last-child { border-bottom: none; }
        .info-label { min-width: 150px; color: #64748b; }

        .fs-card { border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
        .fs-meta { font-size: 9px; color: #6b7385; }
        .fs-body { font-size: 11px; color: #1a2236; margin-top: 4px; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Asset Detail" :parent="['label' => 'Assets', 'route' => 'assets.index']" />

    <div class="main-content" style="padding: 20px !important;">
        <div class="asset-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="mb-1" style="color:white;">{{ $asset->name }}</h5>
                <span class="asset-code-pill">{{ $asset->asset_code }}</span>
                <span class="ms-2"><x-ui.status-badge :status="$asset->status" /></span>
            </div>
            <div class="text-end" style="font-size:11.5px;">
                <div>{{ $asset->category->name ?? 'Uncategorized' }} @if($asset->type) &middot; {{ $asset->type->name }} @endif</div>
                <div>{{ $asset->branch->name ?? 'No branch' }}</div>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-auto">
                @if ($asset->status === 'assigned')
                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#transferModal">
                        <i class="feather-shuffle me-1"></i> Transfer
                    </button>
                @endif
                @if (in_array($asset->status, ['assigned', 'pending_acceptance']))
                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#returnModal">
                        <i class="feather-corner-down-left me-1"></i> Return
                    </button>
                @endif
                @if ($asset->status === 'pending_acceptance' && $asset->currentAssignment)
                    <button class="btn btn-sm btn-light" onclick="forceAccept()">
                        <i class="feather-check-circle me-1"></i> Force Accept
                    </button>
                @endif
            </div>
        </div>

        <ul class="nav nav-tabs" id="assetTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overviewTab" type="button">Overview</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#assignmentTab" type="button">Assignment</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#historyTab" type="button">History</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#repairsTab" type="button">Repairs</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#damageTab" type="button">Damage / Loss</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#attachmentsTab" type="button">Attachments</button></li>
        </ul>

        <div class="tab-content">
            {{-- ==================== OVERVIEW ==================== --}}
            <div class="tab-pane fade show active" id="overviewTab">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="fw-semibold mb-2" style="font-size:12px;">Identity</h6>
                        <div class="info-item"><span class="info-label">Serial Number</span><span>{{ $asset->serial_number ?? '-' }}</span></div>
                        <div class="info-item"><span class="info-label">Brand / Model</span><span>{{ $asset->brand ?? '-' }} {{ $asset->model_number ?? '' }}</span></div>
                        <div class="info-item"><span class="info-label">Condition</span><span>{{ $asset->condition ? ucfirst($asset->condition) : '-' }}</span></div>
                        <div class="info-item"><span class="info-label">Barcode Value</span><span>{{ $asset->barcode_value ?? '-' }}</span></div>
                        <div class="info-item"><span class="info-label">Description</span><span>{{ $asset->description ?? '-' }}</span></div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-semibold mb-2" style="font-size:12px;">Purchase &amp; Warranty</h6>
                        <div class="info-item"><span class="info-label">Vendor</span><span>{{ $asset->vendor->name ?? '-' }}</span></div>
                        <div class="info-item"><span class="info-label">Purchase Date</span><span>{{ $asset->purchase_date?->format('d M Y') ?? '-' }}</span></div>
                        <div class="info-item"><span class="info-label">Purchase Cost</span><span>{{ $asset->purchase_cost ?? '-' }}</span></div>
                        <div class="info-item"><span class="info-label">Invoice Number</span><span>{{ $asset->invoice_number ?? '-' }}</span></div>
                        <div class="info-item"><span class="info-label">Warranty</span>
                            <span>
                                @if ($asset->warranty_start_date || $asset->warranty_end_date)
                                    {{ $asset->warranty_start_date?->format('d M Y') ?? '-' }} &rarr; {{ $asset->warranty_end_date?->format('d M Y') ?? '-' }}
                                @else - @endif
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-semibold mb-2" style="font-size:12px;">Location</h6>
                        <div class="info-item"><span class="info-label">Branch</span><span>{{ $asset->branch->name ?? '-' }}</span></div>
                        <div class="info-item"><span class="info-label">Location Notes</span><span>{{ $asset->location_notes ?? '-' }}</span></div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-semibold mb-2" style="font-size:12px;">Other</h6>
                        <div class="info-item"><span class="info-label">Registered By</span><span>{{ $asset->createdBy->name ?? '-' }}</span></div>
                        <div class="info-item"><span class="info-label">Registered On</span><span>{{ $asset->created_at?->format('d M Y') }}</span></div>
                        <div class="info-item"><span class="info-label">Notes</span><span>{{ $asset->notes ?? '-' }}</span></div>
                    </div>
                </div>
            </div>

            {{-- ==================== ASSIGNMENT ==================== --}}
            <div class="tab-pane fade" id="assignmentTab">
                @if ($asset->currentAssigneeUser)
                    <div class="fs-card mb-3">
                        <strong>Current holder:</strong> {{ $asset->currentAssigneeUser->name }} ({{ $asset->currentAssigneeUser->employee_id }})
                    </div>
                @endif
                <h6 class="fw-semibold mb-2" style="font-size:12px;">Assignment History</h6>
                @forelse ($asset->assignments as $a)
                    <div class="fs-card">
                        <div class="fs-meta">{{ $a->assigned_at?->format('d M Y, h:i A') }} &middot; assigned by {{ $a->assignedBy->name ?? '-' }}</div>
                        <div class="fs-body">
                            <strong>{{ $a->user->name ?? '-' }}</strong> &mdash; <x-ui.status-badge :status="$a->status" />
                            @if ($a->accepted_at) &middot; accepted {{ $a->accepted_at->format('d M Y') }} @endif
                            @if ($a->returned_at) &middot; returned {{ $a->returned_at->format('d M Y') }} @endif
                        </div>
                        @if ($a->remarks)
                            <div class="fs-body text-muted">{{ $a->remarks }}</div>
                        @endif
                    </div>
                @empty
                    <x-ui.empty-state icon="user" title="No assignment history yet" />
                @endforelse
            </div>

            {{-- ==================== HISTORY ==================== --}}
            <div class="tab-pane fade" id="historyTab">
                @forelse ($asset->histories as $h)
                    <div class="fs-card">
                        <div class="fs-meta">{{ $h->created_at?->format('d M Y, h:i A') }} &middot; {{ $h->actor->name ?? 'System' }}</div>
                        <div class="fs-body">
                            <span class="badge bg-{{ $h->action_color }}">{{ str_replace('_', ' ', $h->action) }}</span>
                            @if ($h->remarks) &mdash; {{ $h->remarks }} @endif
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state icon="clock" title="No history yet" />
                @endforelse
            </div>

            {{-- ==================== REPAIRS ==================== --}}
            <div class="tab-pane fade" id="repairsTab">
                @forelse ($asset->repairs as $r)
                    <div class="fs-card">
                        <div class="fs-meta">{{ $r->reported_at?->format('d M Y') }} &middot; {{ ucfirst($r->status) }}</div>
                        <div class="fs-body">{{ $r->issue_description ?? '-' }}</div>
                        @if ($r->status !== 'completed' && $r->status !== 'cancelled')
                            <button class="btn btn-sm btn-light mt-2" onclick="openCompleteRepair('{{ encrypt($r->id) }}')">
                                <i class="feather-check me-1"></i> Mark Complete
                            </button>
                        @endif
                    </div>
                @empty
                    <x-ui.empty-state icon="tool" title="No repair records" />
                @endforelse
            </div>

            {{-- ==================== DAMAGE / LOSS ==================== --}}
            <div class="tab-pane fade" id="damageTab">
                @forelse ($asset->damageReports as $d)
                    <div class="fs-card">
                        <div class="fs-meta">{{ $d->reported_at?->format('d M Y') }} &middot; {{ ucfirst($d->type) }}</div>
                        <div class="fs-body">{{ $d->description ?? '-' }}</div>
                        @if (!$d->resolution)
                            <button class="btn btn-sm btn-light mt-2" onclick="openResolveDamage('{{ encrypt($d->id) }}')">
                                <i class="feather-check me-1"></i> Resolve
                            </button>
                        @else
                            <div class="fs-meta mt-1">Resolved: {{ str_replace('_', ' ', $d->resolution) }}</div>
                        @endif
                    </div>
                @empty
                    <x-ui.empty-state icon="alert-triangle" title="No damage/loss reports" />
                @endforelse
            </div>

            {{-- ==================== ATTACHMENTS ==================== --}}
            <div class="tab-pane fade" id="attachmentsTab">
                @forelse ($asset->attachments as $att)
                    <div class="fs-card d-flex justify-content-between align-items-center">
                        <div>
                            <a href="{{ file_url($att->file_path, 'asset_attachment') }}" target="_blank">{{ $att->original_filename ?? $att->file_path }}</a>
                            <div class="fs-meta">{{ ucfirst(str_replace('_', ' ', $att->context ?? 'general')) }} &middot; {{ $att->uploadedBy->name ?? '-' }} &middot; {{ $att->created_at?->format('d M Y') }}</div>
                        </div>
                        <button class="btn btn-sm btn-light text-danger" onclick="deleteAttachment('{{ encrypt($att->id) }}')">
                            <i class="feather-trash-2"></i>
                        </button>
                    </div>
                @empty
                    <x-ui.empty-state icon="paperclip" title="No attachments yet" />
                @endforelse
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    @include('client.asset.partials.action-modals', ['asset' => $asset, 'users' => $users, 'branches' => $branches, 'vendors' => $vendors])
@endsection

@section('script-area')
    <script>
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        const assetId = '{{ encrypt($asset->id) }}';

        // ==================== EMPLOYEE PICKER (Select2 inside a Bootstrap modal
        // needs its own dropdownParent + explicit width — same fix as
        // shift/roster.blade.php's #user_ids — otherwise the popup is clipped
        // behind the modal and the box collapses to 0 width while hidden) ====
        function initEmployeeSelect2(selector, modalSelector, placeholder) {
            if (!$.fn.select2) return;
            const $el = $(selector);
            if (!$el.length) return;
            if ($el.hasClass('select2-hidden-accessible')) { $el.select2('destroy'); }
            $el.select2({
                placeholder: placeholder,
                width: '100%',
                dropdownParent: $(modalSelector),
                dropdownCssClass: 'emp-opt-dropdown',
                // Avatar + name (ID) + email; the option text (name, ID, email) is what search matches.
                templateResult: function(item) {
                    if (!item.id) return item.text;
                    const data = $(item.element).data();
                    const name = String(data.name || '');
                    const $row = $(
                        '<span class="emp-opt"><span class="emp-opt-avatar"></span>' +
                        '<span class="emp-opt-text"><span class="emp-opt-name"></span><span class="emp-opt-email"></span></span></span>'
                    );
                    $row.find('.emp-opt-avatar').text(name.split(' ').filter(Boolean).map(w => w[0]).join('').toUpperCase().substring(0, 2) || 'NA');
                    $row.find('.emp-opt-name').text(name + (data.empid ? ' (' + data.empid + ')' : ''));
                    $row.find('.emp-opt-email').text(data.email || '');
                    return $row;
                },
                templateSelection: function(item) {
                    if (!item.id) return item.text;
                    const data = $(item.element).data();
                    return data.name + (data.empid ? ' (' + data.empid + ')' : '');
                },
            });
            $el.next('.select2-container').addClass('emp-opt-select');
        }
        initEmployeeSelect2('#transferModal select[name="to_user_id"]', '#transferModal', 'Select employee');

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

        function forceAccept() {
            if (!confirm('Force-accept this asset on behalf of the assignee?')) return;
            postAction("{{ route('assets.assignments.accept', encrypt($asset->currentAssignment->id ?? 0)) }}", {}, 'Asset accepted.');
        }

        $('#transferForm').on('submit', function(e) {
            e.preventDefault();
            postAction("{{ route('assets.transfer', encrypt($asset->id)) }}", $(this).serialize(), 'Asset transferred.');
        });

        $('#returnForm').on('submit', function(e) {
            e.preventDefault();
            postAction("{{ route('assets.assignments.return', encrypt($asset->currentAssignment->id ?? 0)) }}", $(this).serialize(), 'Asset returned.');
        });

        function openCompleteRepair(repairId) {
            $('#completeRepairForm').data('repair-id', repairId);
            new bootstrap.Modal(document.getElementById('completeRepairModal')).show();
        }

        $('#completeRepairForm').on('submit', function(e) {
            e.preventDefault();
            const repairId = $(this).data('repair-id');
            postAction("{{ url('company-assets/repairs') }}/" + repairId + "/complete", $(this).serialize(), 'Repair completed.');
        });

        function openResolveDamage(reportId) {
            $('#resolveDamageForm').data('report-id', reportId);
            new bootstrap.Modal(document.getElementById('resolveDamageModal')).show();
        }

        $('#resolveDamageForm').on('submit', function(e) {
            e.preventDefault();
            const reportId = $(this).data('report-id');
            postAction("{{ url('company-assets/damage-reports') }}/" + reportId + "/resolve", $(this).serialize(), 'Report resolved.');
        });

        function deleteAttachment(id) {
            if (!confirm('Delete this attachment?')) return;
            $.ajax({
                url: "{{ url('company-assets/attachments') }}/" + id,
                type: 'DELETE',
                data: { _token: csrfToken },
                dataType: 'json',
                success: function(res) {
                    toastr.success(res.message);
                    setTimeout(() => location.reload(), 900);
                },
                error: function() { toastr.error('Failed to delete attachment.'); }
            });
        }
    </script>
@endsection
