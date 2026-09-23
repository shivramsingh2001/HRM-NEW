@extends('client.layout.master')

@section('style')
    <style>
        .asset-header {
            background: linear-gradient(135deg, var(--primary, #1e3a8a) 0%, var(--primary-mid, #2563eb) 100%);
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

        .info-item { padding: 6px 0; border-bottom: 1px solid #f1f5f9; display: flex; font-size: 11.5px; }
        .info-item:last-child { border-bottom: none; }
        .info-label { min-width: 150px; color: #64748b; }

        .fs-card { border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
        .fs-meta { font-size: 9px; color: #6b7385; }
        .fs-body { font-size: 11px; color: #1a2236; margin-top: 4px; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="My Asset" :parent="['label' => 'My Assets', 'route' => 'my-assets.index']" />

    @php($myAssignment = $asset->assignments->first())

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

        @if ($myAssignment && $myAssignment->status === 'pending_acceptance')
            <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2" style="font-size:11.5px;border-radius:10px;">
                <span><i class="feather-alert-circle me-1"></i> This asset is awaiting your acceptance.</span>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#acceptModal">
                    <i class="feather-check me-1"></i> Accept Asset
                </button>
            </div>
        @endif

        @if ($myAssignment && $myAssignment->status === 'accepted' && $asset->current_assignee_id == auth()->id())
            <div class="mb-3">
                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#myReturnModal">
                    <i class="feather-corner-down-left me-1"></i> Request / Confirm Return
                </button>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card"><div class="card-body">
                    <h6 class="fw-semibold mb-2" style="font-size:12px;">Details</h6>
                    <div class="info-item"><span class="info-label">Serial Number</span><span>{{ $asset->serial_number ?? '-' }}</span></div>
                    <div class="info-item"><span class="info-label">Brand / Model</span><span>{{ $asset->brand ?? '-' }} {{ $asset->model_number ?? '' }}</span></div>
                    <div class="info-item"><span class="info-label">Condition</span><span>{{ $asset->condition ? ucfirst($asset->condition) : '-' }}</span></div>
                    <div class="info-item"><span class="info-label">Assigned On</span><span>{{ $myAssignment?->assigned_at?->format('d M Y') ?? '-' }}</span></div>
                    <div class="info-item"><span class="info-label">Accepted On</span><span>{{ $myAssignment?->accepted_at?->format('d M Y') ?? '-' }}</span></div>
                </div></div>
            </div>
            <div class="col-md-6">
                <div class="card"><div class="card-body">
                    <h6 class="fw-semibold mb-2" style="font-size:12px;">History</h6>
                    @forelse ($asset->histories->take(10) as $h)
                        <div class="fs-card">
                            <div class="fs-meta">{{ $h->created_at?->format('d M Y, h:i A') }}</div>
                            <div class="fs-body">
                                <span class="badge bg-{{ $h->action_color }}">{{ str_replace('_', ' ', $h->action) }}</span>
                                @if ($h->remarks) &mdash; {{ $h->remarks }} @endif
                            </div>
                        </div>
                    @empty
                        <x-ui.empty-state icon="clock" title="No history yet" />
                    @endforelse
                </div></div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    @if ($myAssignment)
        <div class="modal fade-scale" id="acceptModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm compact-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="mb-0"><span class="fs-14 fw-bold">Accept Asset</span></h2>
                        <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal"><i class="feather-x text-danger"></i></a>
                    </div>
                    <div class="modal-body p-0">
                        <div class="card m-0"><div class="card-body">
                            <form id="acceptForm">
                                <p style="font-size:11.5px;">You are confirming receipt of <strong>{{ $asset->name }}</strong> ({{ $asset->asset_code }}) in good working order.</p>
                                <div class="mb-3">
                                    <label class="fw-semibold">Note (optional)</label>
                                    <textarea class="form-control" name="note" rows="2"></textarea>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Confirm Acceptance</button>
                                    <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </form>
                        </div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade-scale" id="myReturnModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm compact-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="mb-0"><span class="fs-14 fw-bold">Return Asset</span></h2>
                        <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal"><i class="feather-x text-danger"></i></a>
                    </div>
                    <div class="modal-body p-0">
                        <div class="card m-0"><div class="card-body">
                            <form id="myReturnForm">
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
    @endif
@endsection

@section('script-area')
    <script>
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        @if ($myAssignment)
        const myAssignmentId = '{{ encrypt($myAssignment->id) }}';

        $('#acceptForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: "{{ url('assets/assignments') }}/" + myAssignmentId + "/accept",
                type: 'POST', data: Object.assign({ _token: csrfToken }, $(this).serializeArray().reduce((o, x) => (o[x.name] = x.value, o), {})),
                success: function(res) { toastr.success(res.message); setTimeout(() => location.reload(), 900); },
                error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Failed to accept asset.'); }
            });
        });

        $('#myReturnForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: "{{ url('assets/assignments') }}/" + myAssignmentId + "/return",
                type: 'POST', data: Object.assign({ _token: csrfToken }, $(this).serializeArray().reduce((o, x) => (o[x.name] = x.value, o), {})),
                success: function(res) { toastr.success(res.message); setTimeout(() => location.reload(), 900); },
                error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Failed to return asset.'); }
            });
        });
        @endif
    </script>
@endsection
