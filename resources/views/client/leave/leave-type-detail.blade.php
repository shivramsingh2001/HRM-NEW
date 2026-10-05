@extends('client.layout.master')

@section('style')
<style>
    .personal-info .input-group-text { background: #EFF6FF; color: #0D6EFD; border-color: #dfe5f0; }
    .personal-info .form-control[readonly] { background: #f8fafc; border-color: #dfe5f0; color: #1a2236; }
    .personal-info label { font-size: 12px; }

    .lt-detail-badge {
        padding: 3px 10px;
        border-radius: 30px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .2px;
    }

    .badge-active { background: #3b82f6; color: #fff; }
    .badge-inactive { background: #0D6EFD; color: #fff; }
    .badge-system { background: #EFF6FF; color: #0D6EFD; }
    .badge-unpaid { background: #93c5fd; color: #0D6EFD; }

    .customers-nav-tabs .nav-link.active {
        color: #0D6EFD;
        border-color: #dfe5f0 #dfe5f0 #fff;
    }

    .customers-nav-tabs .nav-link {
        color: #6b7385;
    }
</style>
@endsection

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Leave Type Management" current="Details" :crumbs="[['label' => 'Leave Types', 'url' => route('leave-type.index')]]">
        <x-slot:actions>
            @if ($leaveType->isSystemType())
                <span class="lt-detail-badge badge-system" title="System-managed leave type — cannot be edited or deleted">
                    <i class="feather-lock me-1"></i>System type — locked
                </span>
            @endif
            @if ($leaveType->is_unpaid)
                <span class="lt-detail-badge badge-unpaid">Unpaid</span>
            @endif
            <span class="lt-detail-badge {{ $leaveType->status ? 'badge-active' : 'badge-inactive' }}">
                {{ $leaveType->status ? 'Active' : 'Inactive' }}
            </span>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">
        <div class="row">
            <div class="col-lg-12">
                <div class="card border-top-0">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="myTab"
                            role="tablist">
                            <li class="nav-item flex-fill border-top" role="presentation">
                                <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab"
                                    data-bs-target="#profileTab" role="tab">Leave Type Information</a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="profileTab" role="tabpanel">
                            <div class="card-body personal-info">
                                <div class="mb-4 d-flex align-items-center justify-content-between">
                                    <h5 class="fw-bold mb-0 me-4">
                                        <span class="d-block mb-2">Leave Type Information:</span>
                                        <span class="fs-12 fw-normal text-muted text-truncate-1-line">Read-only
                                            overview — use the edit icon on the Leave Type card to update these
                                            details.</span>
                                    </h5>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label for="name" class="fw-semibold">Name: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-user"></i></div>
                                            <input type="text" id="name"
                                                value="{{ ucfirst($leaveType->name) ?? '' }}" class="form-control" readonly
                                                placeholder="Leave Type Name">
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label for="credit_type" class="fw-semibold">Credit Type: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-repeat"></i></div>
                                            <input type="text" id="credit_type"
                                                value="{{ ucfirst($leaveType->credit_type) ?? '' }}" class="form-control"
                                                readonly placeholder="Leave Credit Type">
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label for="credit_value" class="fw-semibold">Credit Value: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-hash"></i></div>
                                            <input type="text" id="credit_value"
                                                value="{{ $leaveType->credit_value ?? '' }}" class="form-control" readonly
                                                placeholder="Leave Credit Value">
                                        </div>
                                    </div>
                                </div>

                                @if (!$leaveType->is_unpaid && $leaveType->credit_type !== 'no')
                                    @php
                                        $cfText = $leaveType->carryForwardText();
                                        if ($leaveType->credit_type === 'yearly' && $leaveType->carry_forward_expiry_months) {
                                            $cfText .= ' · carried days expire after ' . $leaveType->carry_forward_expiry_months . ' month(s)';
                                        }
                                        if (! $carryForwardEnabled) {
                                            $cfText .= ' (company carry forward is off)';
                                        }
                                    @endphp
                                    <div class="row mb-4 align-items-center">
                                        <div class="col-lg-3">
                                            <label for="carry_forward" class="fw-semibold">Carry Forward: </label>
                                        </div>
                                        <div class="col-lg-9">
                                            <div class="input-group">
                                                <div class="input-group-text"><i class="feather-corner-down-right"></i></div>
                                                <input type="text" id="carry_forward" value="{{ $cfText }}" class="form-control" readonly>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label for="description" class="fw-semibold">Description: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-file-text"></i></div>
                                            <textarea id="description" class="form-control" readonly placeholder="Leave Type Description" rows="3">{{ $leaveType->description ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
