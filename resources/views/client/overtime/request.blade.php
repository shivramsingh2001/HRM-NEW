@extends('client.layout.master')

@section('style')
    <style>
        /* .stats-grid/.stats-card/.stats-icon/.stats-info are centralized
           in client.layout.head (single blue-only theme — the per-type
           total-icon/pending-icon/approved-icon/rejected-icon colors are
           dropped so every icon shares the same blue) — no local copy. */

        .stats-sub {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* Filter Section */
        .filter-bar {
            background: white;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        .filter-group label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 4px;
            display: block;
        }

        .filter-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            background: #f8fafc;
        }

        .filter-control:focus {
            border-color: var(--primary-mid);
            outline: none;
            background: white;
        }

        .btn-filter {
            background: var(--primary-mid);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
        }

        .btn-reset {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
        }

        /* Table Styles */
        .table th {
            background: #f8fafc;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .table td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: white;
            transform: translateY(-2px);
        }

        .action-btn.edit:hover {
            color: var(--primary-mid);
            border-color: var(--primary-mid);
        }

        .action-btn.delete:hover {
            color: var(--danger);
            border-color: var(--danger);
        }

        .empty-state {
            text-align: center;
            padding: 60px 24px;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Overtime Requests</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Overtime Requests</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addOvertimeModal">
                <i class="feather-plus me-2"></i>
                <span>Request Overtime</span>
            </button>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <!-- Stats Section -->
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon total-icon">
                    <i class="feather-file-text"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $totalRequests ?? 0 }}</h3>
                    <p>Total Requests</p>
                    <div class="stats-sub">{{ $totalHours ?? 0 }} total hours</div>
                </div>
            </div>

            <div class="stats-card">
                <div class="stats-icon pending-icon">
                    <i class="feather-clock"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $pendingRequests ?? 0 }}</h3>
                    <p>Pending</p>
                    <div class="stats-sub">{{ $pendingHours ?? 0 }} hours</div>
                </div>
            </div>

            <div class="stats-card">
                <div class="stats-icon approved-icon">
                    <i class="feather-check-circle"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $approvedRequests ?? 0 }}</h3>
                    <p>Approved</p>
                    <div class="stats-sub">{{ $approvedHours ?? 0 }} hours</div>
                </div>
            </div>

            <div class="stats-card">
                <div class="stats-icon rejected-icon">
                    <i class="feather-x-circle"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $rejectedRequests ?? 0 }}</h3>
                    <p>Rejected</p>
                    <div class="stats-sub">{{ $rejectedHours ?? 0 }} hours</div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form action="{{ route('overtime.index') }}" method="GET" id="filterForm">
                <div class="filter-form">
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" class="filter-control">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>From Date</label>
                        <input type="date" name="from_date" class="filter-control" value="{{ request('from_date') }}">
                    </div>

                    <div class="filter-group">
                        <label>To Date</label>
                        <input type="date" name="to_date" class="filter-control" value="{{ request('to_date') }}">
                    </div>

                    {{-- <div class="filter-group">
                        <button type="submit" class="btn-filter">
                            <i class="feather-search me-1"></i> Apply
                        </button>
                    </div> --}}

                    <div class="filter-group">
                        <a href="{{ route('overtime.index') }}" class="btn-reset d-inline-block text-center">
                            <i class="feather-refresh-cw me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Requests Table -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>Date</th>
                                <th>Hours</th>
                                <th>Approved Hours</th>
                                <th>Reason</th>
                                <th>Applied On</th>
                                <th>Status</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requests as $index => $request)
                            <tr>
                                <td>{{ $requests->firstItem() + $index }}</td>
                                <td>{{ \Carbon\Carbon::parse($request->date)->format('d M Y') }}</td>
                                <td>
                                    <strong>{{ number_format($request->overtime_hours, 1) }}</strong> hrs
                                </td>
                                <td>
                                    @if($request->approved_hours)
                                        <span class="text-success">{{ number_format($request->approved_hours, 1) }} hrs</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="max-width: 200px;">
                                        {{ Str::limit($request->reason, 50) }}
                                    </div>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($request->created_at)->format('d M Y') }}</td>
                                <td>
                                    <x-ui.status-badge :status="$request->status" />
                                </td>
                                <td>
                                    @if($request->status == 'pending')
                                        <div class="d-flex gap-1">
                                            <button class="action-btn edit" onclick="editRequest({{ $request->id }}, '{{ $request->date }}', {{ $request->overtime_hours }}, '{{ addslashes($request->reason) }}')" title="Edit" data-bs-toggle="tooltip">
                                                <i class="feather-edit-2"></i>
                                            </button>
                                            <button class="action-btn delete" onclick="cancelRequest({{ $request->id }})" title="Cancel" data-bs-toggle="tooltip">
                                                <i class="feather-x"></i>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center">
                                    <div class="empty-state">
                                        <i class="feather-clock"></i>
                                        <h5>No Overtime Requests</h5>
                                        <p class="text-muted">You haven't submitted any overtime requests yet</p>
                                        {{-- <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addOvertimeModal">
                                            <i class="feather-plus me-2"></i>Request Overtime
                                        </button> --}}
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($requests->hasPages())
            <div class="card-footer">
                {{ $requests->links() }}
            </div>
            @endif
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Overtime Modal -->
    <div class="modal fade" id="addOvertimeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Overtime</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addOvertimeForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" class="form-control" name="date" required min="{{ date('Y-m-d') }}">
                            <small class="text-danger error-date"></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Overtime Hours *</label>
                            <input type="number" class="form-control" name="overtime_hours" step="0.5" min="0.5" max="24" required>
                            <small class="text-danger error-hours"></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason *</label>
                            <textarea class="form-control" name="reason" rows="3" required></textarea>
                            <small class="text-danger error-reason"></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Overtime Modal -->
    <div class="modal fade" id="editOvertimeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Overtime Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editOvertimeForm">
                    @csrf
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" class="form-control" name="date" id="edit_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Overtime Hours *</label>
                            <input type="number" class="form-control" name="overtime_hours" id="edit_hours" step="0.5" min="0.5" max="24" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason *</label>
                            <textarea class="form-control" name="reason" id="edit_reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="feather-alert-triangle text-warning" style="font-size: 48px;"></i>
                    <p class="mt-3">Are you sure you want to cancel this request?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">Yes, Cancel</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
<script>
    let cancelId = null;

    $(document).ready(function() {
        // Auto-submit filter on change
        $('.filter-control').on('change', function() {
            $('#filterForm').submit();
        });

        // Add Overtime Request
        $('#addOvertimeForm').on('submit', function(e) {
            e.preventDefault();
            $('.text-danger').text('');

            $.ajax({
                url: "{{ route('overtime.store') }}",
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    if(response.success) {
                        toastr.success(response.message);
                        $('#addOvertimeModal').modal('hide');
                        setTimeout(() => location.reload(), 1000);
                    }
                },
                error: function(xhr) {
                    if(xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        if(errors.date) $('.error-date').text(errors.date[0]);
                        if(errors.overtime_hours) $('.error-hours').text(errors.overtime_hours[0]);
                        if(errors.reason) $('.error-reason').text(errors.reason[0]);
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                    }
                }
            });
        });

        // Update Request
        $('#editOvertimeForm').on('submit', function(e) {
            e.preventDefault();
            let id = $('#edit_id').val();

            $.ajax({
                url: "{{ url('overtime/update') }}/" + id,
                type: 'PUT',
                data: $(this).serialize(),
                success: function(response) {
                    if(response.success) {
                        toastr.success(response.message);
                        $('#editOvertimeModal').modal('hide');
                        setTimeout(() => location.reload(), 1000);
                    }
                },
                error: function() {
                    toastr.error('Failed to update request');
                }
            });
        });

        // Reset forms on modal close
        $('#addOvertimeModal, #editOvertimeModal').on('hidden.bs.modal', function() {
            $(this).find('form')[0].reset();
            $('.text-danger').text('');
        });
    });

    // Edit Request
    function editRequest(id, date, hours, reason) {
        $('#edit_id').val(id);
        $('#edit_date').val(date);
        $('#edit_hours').val(hours);
        $('#edit_reason').val(reason);
        $('#editOvertimeModal').modal('show');
    }

    // Cancel Request
    function cancelRequest(id) {
        cancelId = id;
        $('#cancelModal').modal('show');
    }

    $('#confirmCancel').on('click', function() {
        if(!cancelId) return;

        $.ajax({
            url: "{{ url('overtime/destroy') }}/" + cancelId,
            type: 'DELETE',
            data: {_token: '{{ csrf_token() }}'},
            success: function(response) {
                if(response.success) {
                    toastr.success(response.message);
                    $('#cancelModal').modal('hide');
                    setTimeout(() => location.reload(), 1000);
                }
            },
            error: function() {
                toastr.error('Failed to cancel request');
            }
        });
    });
</script>
@endsection