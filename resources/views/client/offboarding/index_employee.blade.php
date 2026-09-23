@extends('client.layout.master')

@section('style')
    <style>
        .ob-card { background: #fff; border: 1px solid #edf2f7; border-radius: 12px; padding: 20px; max-width: 560px; }
        .ob-btn { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; border: none; font-size: 12.5px; font-weight: 500; padding: 7px 18px; border-radius: 8px; text-decoration: none; }
        .ob-btn:hover { filter: brightness(0.9); color: #fff; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="My Offboarding" />

    <div class="main-content" style="padding: 20px !important;">

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($activeRequest)
            <div class="ob-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="mb-0">{{ $activeRequest->reason_label }}</h5>
                    <x-ui.status-badge :status="$activeRequest->badge_status" :label="$activeRequest->status_label" />
                </div>
                <p style="font-size:12px;color:#64748b;">
                    Submitted {{ $activeRequest->created_at->format('d M Y') }} ·
                    Last working date: {{ optional($activeRequest->last_working_date)->format('d M Y') }}
                </p>
                @if ($activeRequest->status === 'approved')
                    <p style="font-size:12px;color:#64748b;">Current stage: <strong>{{ $activeRequest->stage_label }}</strong></p>
                @endif
                <a href="{{ route('offboarding.show', $activeRequest->id) }}" class="ob-btn"><i class="feather-eye"></i> View Details</a>
            </div>
        @else
            <div class="ob-card text-center">
                <i class="feather-user-minus" style="font-size:32px;color:#cbd5e1;"></i>
                <p class="mt-2 mb-3" style="font-size:12.5px;color:#64748b;">You don't have an active offboarding request.</p>
                <a href="{{ route('offboarding.create') }}" class="ob-btn"><i class="feather-plus"></i> Submit Resignation</a>
            </div>
        @endif

    </div>
@endsection
