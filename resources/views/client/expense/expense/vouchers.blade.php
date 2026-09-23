@extends('client.layout.master')

@section('style')
    @include('client.expense._ui')
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Payment Vouchers</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('expense.payments.index') }}">Payments</a></li>
                <li class="breadcrumb-item active">Vouchers</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="d-flex gap-2">
                <a href="{{ route('expense.payments.batch') }}" class="btn btn-primary btn-sm"><i
                        class="feather-layers me-2"></i>Pay Batch</a>
                <a href="{{ route('expense.payments.index') }}" class="btn btn-light-brand btn-sm"><i
                        class="feather-arrow-left me-2"></i>Back</a>
            </div>
        </div>
    </div>

    <div class="main-content ex-page" style="padding: 20px !important;">
        @php
            $modeLabel = fn($m) => ucwords(str_replace('_', ' ', (string) $m));
            $voidedCount = (int) $stats->voided;
            $postedCount = (int) $stats->c - $voidedCount;
            $filterKeys = ['search', 'status', 'payment_mode', 'from_date', 'to_date'];
            $activeFilterCount = collect(request()->only($filterKeys))->filter()->count();
            $tagUrl = fn(array $drop) => route('expense.vouchers.index', request()->except(array_merge($drop, ['page'])));
        @endphp

        {{-- Overview tiles (same small cards as Payment Management) --}}
        <div class="row ex-tiles">
            <div class="col-xl-3 col-md-6">
                <div class="ex-tile">
                    <div class="ex-tile-icon"><i class="fas fa-file-invoice"></i></div>
                    <div>
                        <h6 class="ex-tile-val">{{ number_format((int) $stats->c) }}</h6>
                        <div class="ex-tile-label">Vouchers</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="ex-tile">
                    <div class="ex-tile-icon"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <h6 class="ex-tile-val">{{ number_format($postedCount) }}</h6>
                        <div class="ex-tile-label">Posted</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="ex-tile">
                    <div class="ex-tile-icon"><i class="fas fa-rupee-sign"></i></div>
                    <div>
                        <h6 class="ex-tile-val">₹{{ number_format((float) $stats->posted_total, 2) }}</h6>
                        <div class="ex-tile-label">Total paid</div>
                        <div class="ex-tile-sub">posted vouchers only</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="ex-tile">
                    <div class="ex-tile-icon"><i class="fas fa-ban"></i></div>
                    <div>
                        <h6 class="ex-tile-val">{{ number_format($voidedCount) }}</h6>
                        <div class="ex-tile-label">Voided</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filter Section --}}
        <div class="filter-wrapper">
            <form action="{{ route('expense.vouchers.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item search">
                        <div class="search-wrapper">
                            <i class="feather-search"></i>
                            <input type="text" name="search" class="form-control" aria-label="Search" value="{{ $filters['search'] ?? '' }}"
                                placeholder="Voucher no., reference, expense code, employee…" autocomplete="off">
                        </div>
                    </div>

                    <div class="filter-item">
                        <select name="status" class="filter-select" aria-label="Status">
                            <option value="">All Status</option>
                            <option value="posted" @selected(($filters['status'] ?? '') === 'posted')>Posted</option>
                            <option value="voided" @selected(($filters['status'] ?? '') === 'voided')>Voided</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <select name="payment_mode" class="filter-select" aria-label="Payment mode">
                            <option value="">All Modes</option>
                            @foreach ($paymentModes as $m)
                                <option value="{{ $m }}" @selected(($filters['payment_mode'] ?? '') === $m)>{{ $modeLabel($m) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-item">
                        <input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" title="From date" aria-label="From date"
                            class="filter-select filter-date">
                    </div>

                    <div class="filter-item">
                        <input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" title="To date" aria-label="To date"
                            class="filter-select filter-date">
                    </div>

                    <div class="filter-item reset">
                        <a href="{{ route('expense.vouchers.index') }}" class="reset-btn" title="Reset all filters">
                            <i class="feather-refresh-cw"></i>
                        </a>
                    </div>
                </div>
            </form>

            @if ($activeFilterCount > 0)
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>
                    @if (request('search'))
                        <span class="filter-tag"><i class="feather-search"></i> "{{ request('search') }}"
                            <a href="{{ $tagUrl(['search']) }}" class="remove-tag"><i class="feather-x"></i></a></span>
                    @endif
                    @if (request('status'))
                        <span class="filter-tag"><i class="feather-activity"></i> Status: {{ ucfirst(request('status')) }}
                            <a href="{{ $tagUrl(['status']) }}" class="remove-tag"><i class="feather-x"></i></a></span>
                    @endif
                    @if (request('payment_mode'))
                        <span class="filter-tag"><i class="feather-credit-card"></i> Mode: {{ $modeLabel(request('payment_mode')) }}
                            <a href="{{ $tagUrl(['payment_mode']) }}" class="remove-tag"><i class="feather-x"></i></a></span>
                    @endif
                    @if (request('from_date'))
                        <span class="filter-tag"><i class="feather-calendar"></i> From: {{ request('from_date') }}
                            <a href="{{ $tagUrl(['from_date']) }}" class="remove-tag"><i class="feather-x"></i></a></span>
                    @endif
                    @if (request('to_date'))
                        <span class="filter-tag"><i class="feather-calendar"></i> To: {{ request('to_date') }}
                            <a href="{{ $tagUrl(['to_date']) }}" class="remove-tag"><i class="feather-x"></i></a></span>
                    @endif
                    <a href="{{ route('expense.vouchers.index') }}" class="filter-tag clear-all"><i class="feather-refresh-cw"></i> Clear All</a>
                </div>
            @endif
        </div>

        {{-- Vouchers Table --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Voucher History</h5>
                <span class="badge bg-info"><i class="feather-list"></i> Total: {{ $batches->total() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="col-sr">Sr. No.</th>
                                <th>Voucher</th>
                                <th>Date</th>
                                <th>Mode</th>
                                <th>Reference</th>
                                <th class="text-end">Lines</th>
                                <th class="text-end">Total</th>
                                <th>Posted by</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($batches as $b)
                                <tr>
                                    <td class="col-sr">{{ $batches->firstItem() + $loop->index }}</td>
                                    <td><a class="code-link" href="{{ route('expense.vouchers.show', $b->id) }}">{{ $b->voucher_number }}</a></td>
                                    <td class="text-nowrap">{{ $b->payment_date?->format('d M Y') }}</td>
                                    <td><span class="badge bg-primary">{{ $modeLabel($b->payment_mode) }}</span></td>
                                    <td>{{ $b->reference_number ?: '-' }}</td>
                                    <td class="text-end">{{ $b->line_count }}</td>
                                    <td class="text-end fw-bold text-nowrap">₹{{ number_format((float) $b->total_amount, 2) }}</td>
                                    <td>{{ $b->creator?->name ?? '-' }}</td>
                                    <td>
                                        @if ($b->isVoided())
                                            <span class="badge bg-danger" title="{{ $b->void_reason }}">Voided</span>
                                        @else
                                            <span class="badge bg-success">Posted</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2 justify-content-center flex-nowrap">
                                            <a class="action-btn primary" href="{{ route('expense.vouchers.show', $b->id) }}"
                                                title="View voucher"><i class="feather-eye"></i></a>
                                            <a class="action-btn" href="{{ route('expense.vouchers.pdf', $b->id) }}"
                                                title="Download PDF"><i class="feather-file-text"></i></a>
                                            <a class="action-btn" href="{{ route('expense.vouchers.csv', $b->id) }}"
                                                title="Bank-transfer CSV"><i class="feather-download"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-5 text-muted">
                                        <i class="feather-file-text d-block mb-2" style="font-size: 40px; color: #cbd5e1;"></i>
                                        No vouchers found. <a href="{{ route('expense.payments.batch') }}">Pay a batch</a> to
                                        create one.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($batches->hasPages())
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing {{ $batches->firstItem() }} to {{ $batches->lastItem() }} of {{ $batches->total() }} entries
                        </div>
                        <div>{{ $batches->links() }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('script-area')
    @include('client.expense._filter-js')
@endsection
