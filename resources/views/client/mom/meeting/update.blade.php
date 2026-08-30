{{-- resources/views/client/mom/meeting/edit.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        /* ─── Google Font ─── */
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap');

        /* ─── Main card ─── */
        .meeting-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        /* Coloured top stripe */
        .meeting-card::before {
            content: '';
            display: block;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, #60a5fa 50%, var(--purple) 100%);
        }

        .meeting-card .card-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 22px 28px 18px;
        }

        .card-header-inner {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .card-header-icon {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-md);
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .card-header-text .title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 2px;
        }

        .card-header-text .subtitle {
            font-size: 12px;
            color: var(--text-muted);
            margin: 0;
        }

        /* ─── Card body ─── */
        .meeting-card .card-body {
            padding: 28px;
        }

        /* ─── Section block ─── */
        .form-section {
            margin-bottom: 28px;
            padding-bottom: 28px;
            border-bottom: 1px solid var(--border);
        }

        .form-section:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .section-label-icon {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .section-label-text {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            letter-spacing: .01em;
        }

        .section-label-required {
            font-size: 10px;
            font-weight: 600;
            color: var(--primary);
            background: var(--primary-light);
            border: 1px solid var(--primary-mid);
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: auto;
            letter-spacing: .02em;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-left: auto;
        }

        .status-badge.scheduled {
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid var(--primary-mid);
        }

        .status-badge.completed {
            background: var(--success-light);
            color: var(--success);
            border: 1px solid #a7f3d0;
        }

        .status-badge.cancelled {
            background: var(--danger-light);
            color: var(--danger);
            border: 1px solid #fca5a5;
        }

        /* ─── Inputs ─── */
        .field-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .field-label i {
            font-size: 13px;
            color: var(--text-muted);
        }

        .field-input {
            width: 100%;
            height: 42px;
            padding: 0 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: var(--font);
            color: var(--text-primary);
            background: var(--surface);
            transition: border-color var(--transition), box-shadow var(--transition);
            outline: none;
        }

        .field-input:hover {
            border-color: #cbd5e1;
        }

        .field-input:focus {
            border-color: var(--primary);
            box-shadow: var(--shadow-focus);
        }

        .field-input.has-error {
            border-color: var(--danger);
        }

        textarea.field-input {
            height: auto;
            padding: 12px 14px;
            resize: vertical;
            min-height: 100px;
        }

        select.field-input {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
            cursor: pointer;
        }

        /* Input group with prefix */
        .input-group-enhanced {
            display: flex;
            align-items: stretch;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            transition: all var(--transition);
            background: var(--surface);
        }

        .input-group-enhanced:focus-within {
            border-color: var(--primary);
            box-shadow: var(--shadow-focus);
        }

        .input-group-enhanced:hover {
            border-color: #cbd5e1;
        }

        .input-prefix {
            display: flex;
            align-items: center;
            padding: 0 14px;
            background: var(--surface-2);
            border-right: 1px solid var(--border);
            border-radius: var(--radius-md) 0 0 var(--radius-md);
            color: var(--text-muted);
            font-size: 14px;
        }

        .input-group-enhanced .field-input {
            flex: 1;
            border: none;
            height: 42px;
            padding: 0 14px;
        }

        .input-group-enhanced .field-input:focus {
            box-shadow: none;
        }

        .field-hint {
            font-size: 11.5px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 4px;
        }

        .field-hint i {
            font-size: 11px;
        }

        /* Meeting type cards */
        .type-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .type-card {
            position: relative;
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 18px 12px 14px;
            cursor: pointer;
            transition: all var(--transition);
            background: var(--surface);
            text-align: center;
            user-select: none;
        }

        .type-card:hover {
            border-color: var(--primary);
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .type-card.active {
            border-color: var(--primary);
            background: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .1);
        }

        .type-card input[type="radio"] {
            display: none;
        }

        .type-card-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            background: var(--surface-3);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin: 0 auto 10px;
            transition: all var(--transition);
        }

        .type-card.active .type-card-icon {
            background: var(--primary);
            color: #fff;
            transform: scale(1.05);
        }

        .type-card-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
            display: block;
            margin-bottom: 3px;
        }

        .type-card-desc {
            font-size: 11px;
            color: var(--text-muted);
        }

        .type-card.active .type-card-name {
            color: var(--primary);
        }

        .type-check {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--border);
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: transparent;
            transition: all var(--transition);
        }

        .type-card.active .type-check {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        /* Date/time row */
        .datetime-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr 1fr;
            gap: 16px;
        }

        .datetime-block .datetime-chip {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 6px;
        }

        .datetime-block .datetime-chip i {
            font-size: 12px;
        }

        .datetime-input-wrapper {
            position: relative;
        }

        .datetime-input-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            pointer-events: none;
        }

        .datetime-input-wrapper .field-input {
            padding-left: 36px;
        }

        /* Participants wrapper */
        .participants-wrapper {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            background: var(--surface);
        }

        .participants-wrapper-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 13px 16px;
            border-bottom: 1px solid var(--border);
            background: var(--surface-2);
        }

        .participants-wrapper-header .pw-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .participants-wrapper-header .pw-hint {
            font-size: 11.5px;
            color: var(--text-muted);
        }

        .participants-panels {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
        }

        .participants-panels>div {
            border-right: 1px solid var(--border);
        }

        .participants-panels>div:last-child {
            border-right: none;
        }

        .panel-hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 14px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .panel-hd-left {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .panel-hd-icon {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .panel-hd-icon.blue {
            background: var(--primary-light);
            color: var(--primary);
        }

        .panel-hd-icon.green {
            background: var(--success-light);
            color: var(--success);
        }

        .panel-hd-icon.purple {
            background: var(--purple-light);
            color: var(--purple);
        }

        .panel-hd-name {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .count-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
            min-width: 22px;
            text-align: center;
        }

        .count-badge.blue {
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid var(--primary-mid);
        }

        .count-badge.green {
            background: var(--success-light);
            color: var(--success);
            border: 1px solid #a7f3d0;
        }

        .count-badge.purple {
            background: var(--purple-light);
            color: var(--purple);
            border: 1px solid var(--purple-mid);
        }

        .pane-scroll {
            height: 300px;
            overflow-y: auto;
            padding: 8px;
            background: var(--surface);
        }

        .search-wrap {
            padding: 8px 8px 4px;
            background: var(--surface);
        }

        .search-input {
            width: 100%;
            height: 32px;
            padding: 0 10px 0 30px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 12px;
            outline: none;
        }

        .search-wrap-inner {
            position: relative;
        }

        .search-wrap-inner i {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            color: var(--text-muted);
        }

        .participant-item {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 7px 8px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: background var(--transition);
            margin-bottom: 3px;
        }

        .participant-item:hover {
            background: var(--surface-3);
        }

        .participant-item.selected {
            background: var(--primary-light);
        }

        .p-avatar-sm {
            width: 30px;
            height: 30px;
            min-width: 30px;
            border-radius: 50%;
            background: var(--surface-3);
            color: var(--text-secondary);
            font-size: 10.5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
        }

        .participant-item.selected .p-avatar-sm {
            background: var(--primary);
            color: #fff;
        }

        .p-info {
            flex: 1;
            min-width: 0;
        }

        .p-info b {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .p-info span {
            display: block;
            font-size: 10.5px;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .p-check {
            width: 17px;
            height: 17px;
            border-radius: 4px;
            border: 1.5px solid var(--border);
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: transparent;
            flex-shrink: 0;
            transition: all var(--transition);
        }

        .participant-item.selected .p-check {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .selected-participant {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 10px;
            margin-bottom: 5px;
            border-radius: var(--radius-sm);
            background: var(--surface);
            border: 1px solid var(--border);
        }

        .participant-avatar {
            width: 30px;
            height: 30px;
            min-width: 30px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            font-size: 10.5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
        }

        .participant-avatar.mom-active {
            background: var(--purple-light);
            color: var(--purple);
        }

        .participant-info {
            flex: 1;
            min-width: 0;
        }

        .participant-info strong {
            display: block;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .participant-info small {
            display: block;
            font-size: 10.5px;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .participant-actions {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }

        .mom-toggle-label {
            display: flex;
            align-items: center;
            gap: 3px;
            cursor: pointer;
            padding: 3px 8px;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: var(--surface-3);
            transition: all var(--transition);
        }

        .mom-toggle-label:hover {
            background: var(--purple-light);
            border-color: var(--purple-mid);
        }

        .mom-toggle-label.active {
            background: var(--purple-light);
            border-color: var(--purple-mid);
        }

        .mom-toggle-label input[type="checkbox"] {
            display: none;
        }

        .mom-toggle-text {
            font-size: 10px;
            font-weight: 600;
            color: var(--text-muted);
            transition: color var(--transition);
        }

        .mom-toggle-label.active .mom-toggle-text {
            color: var(--purple);
        }

        .mom-toggle-icon {
            font-size: 11px;
        }

        .remove-participant {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: none;
            background: transparent;
            color: #d1d5db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            cursor: pointer;
            transition: background var(--transition), color var(--transition);
        }

        .remove-participant:hover {
            background: var(--danger-light);
            color: var(--danger);
        }

        .mom-writer-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--purple-light);
            color: var(--purple);
            border: 1px solid var(--purple-mid);
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            min-height: 200px;
            color: var(--text-muted);
            gap: 8px;
            padding: 20px;
            text-align: center;
        }

        .empty-state-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            background: var(--surface-3);
            color: #d1d5db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .empty-state p {
            font-size: 11.5px;
            margin: 0;
            line-height: 1.6;
        }

        /* Reminder pills */
        .reminder-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .reminder-pill {
            position: relative;
        }

        .reminder-pill input[type="radio"] {
            display: none;
        }

        .reminder-pill label {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 7px 14px;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: var(--surface);
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all var(--transition);
        }

        .reminder-pill label:hover {
            border-color: var(--primary-mid);
            background: var(--primary-light);
            color: var(--primary);
        }

        .reminder-pill input:checked+label {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 2px 6px rgba(37, 99, 235, .3);
        }

        .error-message {
            color: var(--danger);
            font-size: 11.5px;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .meeting-card .card-footer {
            background: var(--surface-2);
            border-top: 1px solid var(--border);
            padding: 18px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-update {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            height: 42px;
            padding: 0 24px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
        }

        .btn-update:hover {
            background: var(--primary-dark);
            box-shadow: 0 4px 12px rgba(37, 99, 235, .35);
            transform: translateY(-1px);
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 42px;
            padding: 0 20px;
            background: var(--surface);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
        }

        .btn-cancel:hover {
            background: var(--danger-light);
            border-color: #fca5a5;
            color: var(--danger);
        }

        .meeting-form .alert {
            border-radius: var(--radius-md);
            border: none;
            padding: 12px 16px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .meeting-form .alert-danger {
            background: var(--danger-light);
            color: #991b1b;
            border-left: 3px solid var(--danger);
        }

        .meeting-form .alert-warning {
            background: var(--warning-light);
            color: #92400e;
            border-left: 3px solid var(--warning);
        }

        @media (max-width: 767px) {
            .type-cards {
                grid-template-columns: 1fr;
            }

            .datetime-grid {
                grid-template-columns: 1fr;
            }

            .participants-panels {
                grid-template-columns: 1fr;
            }

            .participants-panels>div {
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .meeting-card .card-body {
                padding: 18px;
            }

            .meeting-card .card-footer {
                padding: 14px 18px;
            }
        }
    </style>
@endsection

@php
    $currentUser = Auth::user();
    $role = $currentUser->role;

    // Get existing participants and MOM writer
    $existingParticipantsArray = $meeting->participants->pluck('user_id')->toArray();
    $existingMomWriterId = $meeting->participants->where('is_mom_writer', true)->first()?->user_id;

    // Check if meeting is editable
    $isEditable = $meeting->status == 'scheduled';

    $warningMessage = '';
    if ($meeting->status == 'completed') {
        $warningMessage = 'This meeting is already completed. You cannot edit it.';
    } elseif ($meeting->status == 'cancelled') {
        $warningMessage = 'This meeting has been cancelled. You cannot edit it.';
    }
@endphp

@section('content-area')

    {{-- ─── Page header ─── --}}
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Meeting Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('meetings.index') }}">Meetings</a></li>
                <li class="breadcrumb-item"><a href="{{ route('meetings.show', $meeting->id) }}">{{ $meeting->title }}</a>
                </li>
                <li class="breadcrumb-item active">Edit Meeting</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="feather-alert-circle"></i>
                {{ session('error') }}
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($warningMessage)
            <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                <i class="feather-alert-triangle"></i>
                {{ $warningMessage }}
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="feather-alert-circle"></i>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form action="{{ route('meetings.update', $meeting->id) }}" method="POST" enctype="multipart/form-data"
            id="meetingForm">
            @csrf
            @method('PUT')
            <div class="meeting-card">

                <div class="card-header">
                    <div class="card-header-inner">
                        <div class="card-header-icon">
                            <i class="feather-calendar"></i>
                        </div>
                        <div class="card-header-text">
                            <p class="title">Edit Meeting</p>
                            <p class="subtitle">Update meeting details below</p>
                        </div>
                        <span class="status-badge {{ $meeting->status }}">
                            {{ ucfirst($meeting->status) }}
                        </span>
                    </div>
                </div>

                <div class="card-body">

                    {{-- ════ SECTION 1 — Basic Information ════ --}}
                    <div class="form-section">
                        <div class="section-label">
                            <div class="section-label-icon"><i class="feather-info"></i></div>
                            <span class="section-label-text">Basic Information</span>
                            <span class="section-label-required">REQUIRED</span>
                        </div>

                        <div class="row g-4">
                            <div class="col-12">
                                <div class="field-group">
                                    <label class="field-label" for="title">
                                        Meeting Title <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group-enhanced">
                                        <div class="input-prefix">
                                            <i class="feather-edit-2"></i>
                                        </div>
                                        <input type="text" class="field-input @error('title') has-error @enderror"
                                            id="title" name="title"
                                            placeholder="e.g., Q4 Product Review, Weekly Sync, Client Meeting"
                                            value="{{ old('title', $meeting->title) }}" required
                                            {{ !$isEditable ? 'readonly' : '' }}>
                                    </div>
                                    @error('title')
                                        <span class="error-message"><i
                                                class="feather-alert-circle"></i>{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="field-group">
                                    <label class="field-label" for="description">
                                        Description
                                    </label>
                                    <textarea class="field-input @error('description') has-error @enderror" id="description" name="description"
                                        rows="4" placeholder="What will be discussed? Include agenda items, goals, and expected outcomes..."
                                        {{ !$isEditable ? 'readonly' : '' }}>{{ old('description', $meeting->description) }}</textarea>
                                    @error('description')
                                        <span class="error-message"><i
                                                class="feather-alert-circle"></i>{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ════ SECTION 2 — Meeting Type & Location ════ --}}
                    <div class="form-section">
                        <div class="section-label">
                            <div class="section-label-icon"><i class="feather-map-pin"></i></div>
                            <span class="section-label-text">Meeting Type & Location</span>
                            <span class="section-label-required">REQUIRED</span>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-5">
                                <div class="field-group">
                                    <label class="field-label"><i class="feather-video"></i> Meeting Type *</label>
                                    <div class="type-cards" id="typeCards">
                                        <div class="type-card {{ old('meeting_type', $meeting->meeting_type) == 'physical' ? 'active' : '' }}"
                                            data-value="physical">
                                            <input type="radio" name="meeting_type" value="physical"
                                                {{ old('meeting_type', $meeting->meeting_type) == 'physical' ? 'checked' : '' }}
                                                {{ !$isEditable ? 'disabled' : '' }}>
                                            <span class="type-check">✓</span>
                                            <div class="type-card-icon"><i class="feather-users"></i></div>
                                            <span class="type-card-name">Physical</span>
                                            <span class="type-card-desc">In-person meeting</span>
                                        </div>
                                        <div class="type-card {{ old('meeting_type', $meeting->meeting_type) == 'virtual' ? 'active' : '' }}"
                                            data-value="virtual">
                                            <input type="radio" name="meeting_type" value="virtual"
                                                {{ old('meeting_type', $meeting->meeting_type) == 'virtual' ? 'checked' : '' }}
                                                {{ !$isEditable ? 'disabled' : '' }}>
                                            <span class="type-check">✓</span>
                                            <div class="type-card-icon"><i class="feather-video"></i></div>
                                            <span class="type-card-name">Virtual</span>
                                            <span class="type-card-desc">Online meeting</span>
                                        </div>
                                        <div class="type-card {{ old('meeting_type', $meeting->meeting_type) == 'hybrid' ? 'active' : '' }}"
                                            data-value="hybrid">
                                            <input type="radio" name="meeting_type" value="hybrid"
                                                {{ old('meeting_type', $meeting->meeting_type) == 'hybrid' ? 'checked' : '' }}
                                                {{ !$isEditable ? 'disabled' : '' }}>
                                            <span class="type-check">✓</span>
                                            <div class="type-card-icon"><i class="feather-globe"></i></div>
                                            <span class="type-card-name">Hybrid</span>
                                            <span class="type-card-desc">Both options</span>
                                        </div>
                                    </div>
                                    @error('meeting_type')
                                        <span class="error-message"><i
                                                class="feather-alert-circle"></i>{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-lg-7">
                                <div class="field-group">
                                    <label class="field-label" id="locationLabel" for="location">
                                        <i class="feather-map-pin"></i> Location / Meeting Link *
                                    </label>
                                    <div class="input-group-enhanced">
                                        <div class="input-prefix" id="locationPrefix">
                                            <i class="feather-map-pin"></i>
                                        </div>
                                        <textarea class="field-input @error('location') has-error @enderror" id="location" name="location"
                                            placeholder="Enter room number, address, or meeting link" rows="2" {{ !$isEditable ? 'readonly' : '' }}>{{ old('location', $meeting->location) }}</textarea>
                                    </div>
                                    <div class="field-hint" id="locationInfo">
                                        <i class="feather-info"></i>
                                        Physical: Room/Building address | Virtual: Google Meet / Zoom link
                                    </div>
                                    @error('location')
                                        <span class="error-message"><i
                                                class="feather-alert-circle"></i>{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ════ SECTION 3 — Date & Time ════ --}}
                    <div class="form-section">
                        <div class="section-label">
                            <div class="section-label-icon"><i class="feather-clock"></i></div>
                            <span class="section-label-text">Date &amp; Time</span>
                            <span class="section-label-required">REQUIRED</span>
                        </div>

                        <div class="datetime-grid">
                            <div class="datetime-block">
                                <div class="datetime-chip">
                                    <i class="feather-calendar"></i> Date
                                </div>
                                <div class="datetime-input-wrapper">
                                    <i class="feather-calendar"></i>
                                    <input type="date" class="field-input @error('meeting_date') has-error @enderror"
                                        id="meeting_date" name="meeting_date"
                                        value="{{ old('meeting_date', $meeting->meeting_date instanceof \Carbon\Carbon ? $meeting->meeting_date->format('Y-m-d') : $meeting->meeting_date) }}"
                                        min="{{ date('Y-m-d') }}" required {{ !$isEditable ? 'readonly' : '' }}>
                                </div>
                                @error('meeting_date')
                                    <span class="error-message"><i
                                            class="feather-alert-circle"></i>{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="datetime-block">
                                <div class="datetime-chip">
                                    <i class="feather-play"></i> Start Time
                                </div>
                                <div class="datetime-input-wrapper">
                                    <i class="feather-clock"></i>
                                    <input type="time" class="field-input @error('start_time') has-error @enderror"
                                        id="start_time" name="start_time"
                                        value="{{ old('start_time', \Carbon\Carbon::parse($meeting->start_time)->format('H:i')) }}"
                                        required {{ !$isEditable ? 'readonly' : '' }}>
                                </div>
                                @error('start_time')
                                    <span class="error-message"><i
                                            class="feather-alert-circle"></i>{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="datetime-block">
                                <div class="datetime-chip">
                                    <i class="feather-stop-circle"></i> End Time
                                </div>
                                <div class="datetime-input-wrapper">
                                    <i class="feather-clock"></i>
                                    <input type="time" class="field-input @error('end_time') has-error @enderror"
                                        id="end_time" name="end_time"
                                        value="{{ old('end_time', \Carbon\Carbon::parse($meeting->end_time)->format('H:i')) }}"
                                        required {{ !$isEditable ? 'readonly' : '' }}>
                                </div>
                                @error('end_time')
                                    <span class="error-message"><i
                                            class="feather-alert-circle"></i>{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- ════ SECTION 4 — Participants (Read-only for non-editable meetings) ════ --}}
                    <div class="form-section">
                        <div class="section-label">
                            <div class="section-label-icon"><i class="feather-users"></i></div>
                            <span class="section-label-text">Participants &amp; MOM Writer</span>
                            <span class="section-label-required">REQUIRED</span>
                        </div>

                        <div class="participants-wrapper">
                            <div class="participants-wrapper-header">
                                <span class="pw-title">
                                    @if ($isEditable)
                                        Select attendees and assign ONE MOM writer
                                    @else
                                        Meeting Participants
                                    @endif
                                </span>
                                <span class="pw-hint">
                                    @if ($isEditable)
                                        Only one person can be the MOM writer
                                    @endif
                                </span>
                            </div>

                            <div class="participants-panels">

                                {{-- ── Left: all users (only show if editable) ── --}}
                                @if ($isEditable)
                                    <div>
                                        <div class="panel-hd">
                                            <div class="panel-hd-left">
                                                <div class="panel-hd-icon blue"><i class="feather-list"></i></div>
                                                <span class="panel-hd-name">All Users</span>
                                            </div>
                                            <span class="count-badge blue"
                                                id="allUsersCount">{{ count($allUsers) }}</span>
                                        </div>

                                        <div class="search-wrap">
                                            <div class="search-wrap-inner">
                                                <i class="feather-search"></i>
                                                <input type="text" class="search-input" id="participantSearch"
                                                    placeholder="Search name or email…">
                                            </div>
                                        </div>
                                        <div class="pane-scroll" id="allUsersList">
                                            @foreach ($allUsers as $userItem)
                                                @php
                                                    $initials = collect(explode(' ', $userItem->name))
                                                        ->take(2)
                                                        ->map(fn($w) => strtoupper($w[0]))
                                                        ->join('');
                                                    $isSelected = in_array($userItem->id, $existingParticipantsArray);
                                                    $isMomWriter = $existingMomWriterId == $userItem->id;
                                                @endphp
                                                <div class="participant-item {{ $isSelected ? 'selected' : '' }}"
                                                    data-user-id="{{ $userItem->id }}"
                                                    data-user-name="{{ $userItem->name }}"
                                                    data-user-email="{{ $userItem->email }}"
                                                    data-initials="{{ $initials }}">
                                                    <div class="p-avatar-sm">{{ $initials }}</div>
                                                    <div class="p-info">
                                                        <b>{{ $userItem->name }} <small>( {{ $userItem->employee_id }}
                                                                )</small></b>
                                                        <span>{{ $userItem->email }}</span>
                                                    </div>
                                                    <div class="p-check">✓</div>
                                                    <input type="checkbox" class="participant-checkbox"
                                                        style="display:none" value="{{ $userItem->id }}"
                                                        id="user_{{ $userItem->id }}" {{ $isSelected ? 'checked' : '' }}>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- ── Middle: selected ── --}}
                                <div>
                                    <div class="panel-hd">
                                        <div class="panel-hd-left">
                                            <div class="panel-hd-icon green"><i class="feather-user-check"></i></div>
                                            <span class="panel-hd-name">Selected</span>
                                        </div>
                                        <span class="count-badge green"
                                            id="selectedCount">{{ count($existingParticipantsArray) }}</span>
                                    </div>
                                    <div class="pane-scroll" id="selectedParticipantsList">
                                        @if (empty($existingParticipantsArray))
                                            <div class="empty-state">
                                                <div class="empty-state-icon"><i class="feather-user-plus"></i></div>
                                                <p>No participants selected yet.</p>
                                            </div>
                                        @else
                                            @foreach ($allUsers as $userItem)
                                                @if (in_array($userItem->id, $existingParticipantsArray))
                                                    @php
                                                        $isMom = $existingMomWriterId == $userItem->id;
                                                        $initials = collect(explode(' ', $userItem->name))
                                                            ->take(2)
                                                            ->map(fn($w) => strtoupper($w[0]))
                                                            ->join('');
                                                    @endphp
                                                    <div class="selected-participant" data-id="{{ $userItem->id }}">
                                                        <div class="participant-avatar {{ $isMom ? 'mom-active' : '' }}">
                                                            {{ $initials }}</div>
                                                        <div class="participant-info">
                                                            <strong>{{ $userItem->name }}</strong>
                                                            <small>{{ $userItem->email }}</small>
                                                        </div>
                                                        @if ($isEditable)
                                                            <div class="participant-actions">
                                                                <label
                                                                    class="mom-toggle-label {{ $isMom ? 'active' : '' }}">
                                                                    <input type="checkbox" class="mom-writer-checkbox"
                                                                        data-id="{{ $userItem->id }}"
                                                                        {{ $isMom ? 'checked' : '' }}>
                                                                    <span class="mom-toggle-icon">✦</span>
                                                                    <span class="mom-toggle-text">MOM</span>
                                                                </label>
                                                                <button type="button" class="remove-participant"
                                                                    data-id="{{ $userItem->id }}" title="Remove">
                                                                    <i class="feather-x"></i>
                                                                </button>
                                                            </div>
                                                        @else
                                                            @if ($isMom)
                                                                <span class="mom-writer-badge">✦ MOM Writer</span>
                                                            @endif
                                                        @endif
                                                    </div>
                                                @endif
                                            @endforeach
                                        @endif
                                    </div>
                                </div>

                                {{-- ── Right: MOM Writer (Single) ── --}}
                                <div>
                                    <div class="panel-hd">
                                        <div class="panel-hd-left">
                                            <div class="panel-hd-icon purple"><i class="feather-edit-3"></i></div>
                                            <span class="panel-hd-name">MOM Writer</span>
                                        </div>
                                        <span class="count-badge purple"
                                            id="momCount">{{ $existingMomWriterId ? 1 : 0 }}</span>
                                    </div>
                                    <div class="pane-scroll" id="momWritersList">
                                        @if ($existingMomWriterId)
                                            @php
                                                $momWriterUser = $allUsers->firstWhere('id', $existingMomWriterId);
                                            @endphp
                                            @if ($momWriterUser)
                                                @php
                                                    $initials = collect(explode(' ', $momWriterUser->name))
                                                        ->take(2)
                                                        ->map(fn($w) => strtoupper($w[0]))
                                                        ->join('');
                                                @endphp
                                                <div class="selected-participant" data-id="{{ $existingMomWriterId }}">
                                                    <div class="participant-avatar mom-active">{{ $initials }}
                                                    </div>
                                                    <div class="participant-info">
                                                        <strong>{{ $momWriterUser->name }}</strong>
                                                        <small>{{ $momWriterUser->email }}</small>
                                                    </div>
                                                    <span class="mom-writer-badge">✦ MOM Writer</span>
                                                </div>
                                            @else
                                                <div class="empty-state">
                                                    <div class="empty-state-icon"><i class="feather-alert-circle"></i>
                                                    </div>
                                                    <p>MOM writer not found (User may be inactive or deleted)</p>
                                                </div>
                                            @endif
                                        @else
                                            <div class="empty-state">
                                                <div class="empty-state-icon"><i class="feather-file-text"></i></div>
                                                <p>No MOM writer assigned.</p>
                                                @if ($isEditable)
                                                    <p class="text-muted mt-2" style="font-size: 10px;">⚠️ Only ONE
                                                        person can be MOM writer</p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>

                            </div>
                        </div>

                        <input type="hidden" name="participants" id="participantsInput"
                            value="{{ json_encode($existingParticipantsArray) }}">
                        <input type="hidden" name="mom_writers" id="momWritersInput"
                            value="{{ $existingMomWriterId ? json_encode([$existingMomWriterId]) : json_encode([]) }}">

                        @error('participants')
                            <span class="error-message mt-2"><i class="feather-alert-circle"></i>{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- ════ SECTION 5 — Reminder (only show if editable) ════ --}}
                    @if ($isEditable)
                        <div class="form-section">
                            <div class="section-label">
                                <div class="section-label-icon"><i class="feather-bell"></i></div>
                                <span class="section-label-text">Reminder</span>
                            </div>

                            <div class="field-group">
                                <label class="field-label"><i class="feather-bell"></i> Send reminder before
                                    meeting</label>
                                <div class="reminder-pills" id="reminderPills">
                                    @foreach ([['5', '5 min'], ['10', '10 min'], ['15', '15 min'], ['30', '30 min'], ['60', '1 hour'], ['120', '2 hours'], ['1440', '1 day'], ['0', 'None']] as [$val, $lbl])
                                        <div class="reminder-pill">
                                            <input type="radio" name="reminder_choice" id="rem_{{ $val }}"
                                                value="{{ $val }}"
                                                {{ old('reminder_minutes', $meeting->reminder_minutes_before ?? 15) == $val ? 'checked' : '' }}>
                                            <label for="rem_{{ $val }}">{{ $lbl }}</label>
                                        </div>
                                    @endforeach
                                </div>
                                <select name="reminder_minutes" id="reminder_minutes" style="display:none">
                                    <option value="5">5</option>
                                    <option value="10">10</option>
                                    <option value="15" selected>15</option>
                                    <option value="30">30</option>
                                    <option value="60">60</option>
                                    <option value="120">120</option>
                                    <option value="1440">1440</option>
                                    <option value="0">0</option>
                                </select>
                            </div>
                        </div>
                    @endif

                </div>

                <div class="card-footer">
                    @if ($isEditable)
                        <button type="submit" class="btn-update">
                            <i class="feather-save"></i> Update Meeting
                        </button>
                    @endif
                    <a href="{{ route('meetings.show', $meeting->id) }}" class="btn-cancel">
                        <i class="feather-x"></i> Cancel
                    </a>
                </div>

            </div>
        </form>
    </div>

@endsection

@section('script-area')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize from existing data
            let selectedParticipants = [];
            let momWriterId = null;

            // Get initial data from hidden inputs
            const initialParticipants = document.getElementById('participantsInput').value;
            const initialMomWriters = document.getElementById('momWritersInput').value;
            const isEditable = {{ $isEditable ? 'true' : 'false' }};

            if (initialParticipants) {
                try {
                    const participantIds = JSON.parse(initialParticipants);
                    const allUserItems = document.querySelectorAll('#allUsersList .participant-item');
                    selectedParticipants = Array.from(participantIds).map(id => {
                        const item = document.querySelector(
                            `#allUsersList .participant-item[data-user-id="${id}"]`);
                        if (item) {
                            return {
                                id: parseInt(id),
                                name: item.dataset.userName,
                                email: item.dataset.userEmail
                            };
                        }
                        return {
                            id: parseInt(id),
                            name: '',
                            email: ''
                        };
                    }).filter(p => p.name);
                } catch (e) {
                    console.log('Error parsing participants:', e);
                    selectedParticipants = [];
                }
            }

            if (initialMomWriters) {
                try {
                    const momArray = JSON.parse(initialMomWriters);
                    momWriterId = momArray.length > 0 ? momArray[0] : null;
                } catch (e) {
                    momWriterId = null;
                }
            }

            function escapeHtml(t) {
                const d = document.createElement('div');
                d.textContent = t;
                return d.innerHTML;
            }

            function getInitials(name) {
                return name.trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
            }

            // Meeting type toggle cards (only if editable)
            if (isEditable) {
                document.querySelectorAll('.type-card').forEach(card => {
                    card.addEventListener('click', function() {
                        document.querySelectorAll('.type-card').forEach(c => c.classList.remove(
                            'active'));
                        this.classList.add('active');
                        const radio = this.querySelector('input[type="radio"]');
                        if (radio) radio.checked = true;
                        updateLocationField(this.dataset.value);
                    });
                });
            }

            const locationInput = document.getElementById('location');
            const locationLabel = document.getElementById('locationLabel');
            const locationInfo = document.getElementById('locationInfo');
            const locationPrefix = document.getElementById('locationPrefix');

            function updateLocationField(type) {
                if (type === 'virtual') {
                    locationLabel.innerHTML = '<i class="feather-link"></i> Meeting Link *';
                    locationInput.placeholder =
                        'https://meet.google.com/xxx-xxxx-xxx\nhttps://zoom.us/j/xxxxx\nhttps://teams.microsoft.com/xxxxx';
                    locationInfo.innerHTML =
                        '<i class="feather-link"></i> Add the virtual meeting link so participants can join';
                    if (locationPrefix) locationPrefix.innerHTML = '<i class="feather-link"></i>';
                    locationInput.rows = 2;
                } else if (type === 'physical') {
                    locationLabel.innerHTML = '<i class="feather-map-pin"></i> Location *';
                    locationInput.placeholder =
                        'Conference Room A, 3rd Floor\nBuilding Name, Street Address\nCity, State - PIN Code';
                    locationInfo.innerHTML =
                        '<i class="feather-map-pin"></i> Physical location where attendees will gather';
                    if (locationPrefix) locationPrefix.innerHTML = '<i class="feather-map-pin"></i>';
                    locationInput.rows = 2;
                } else {
                    locationLabel.innerHTML = '<i class="feather-globe"></i> Location / Meeting Link *';
                    locationInput.placeholder =
                        'Physical Address:\nConference Room A, Building Name\n\nVirtual Link:\nhttps://meet.google.com/xxx-xxxx-xxx';
                    locationInfo.innerHTML =
                        '<i class="feather-globe"></i> Provide location for in-person and link for virtual attendees';
                    if (locationPrefix) locationPrefix.innerHTML = '<i class="feather-globe"></i>';
                    locationInput.rows = 3;
                }
            }

            const initialType = document.querySelector('.type-card.active');
            if (initialType && isEditable) updateLocationField(initialType.dataset.value);

            // Time validation
            const startTime = document.getElementById('start_time');
            const endTime = document.getElementById('end_time');
            const meetingDate = document.getElementById('meeting_date');

            function validateTimes() {
                if (startTime.value && endTime.value) {
                    if (endTime.value <= startTime.value) {
                        endTime.setCustomValidity('End time must be after start time');
                        endTime.classList.add('has-error');
                    } else {
                        endTime.setCustomValidity('');
                        endTime.classList.remove('has-error');
                    }
                }
            }

            if (startTime && endTime && isEditable) {
                startTime.addEventListener('change', validateTimes);
                endTime.addEventListener('change', validateTimes);
            }

            // Participant search (only if editable)
            const searchInput = document.getElementById('participantSearch');
            if (searchInput && isEditable) {
                searchInput.addEventListener('input', function() {
                    const q = this.value.toLowerCase();
                    document.querySelectorAll('#allUsersList .participant-item').forEach(item => {
                        const name = (item.dataset.userName || '').toLowerCase();
                        const email = (item.dataset.userEmail || '').toLowerCase();
                        item.style.display = (name.includes(q) || email.includes(q)) ? '' : 'none';
                    });
                });
            }

            function updateSelectedParticipants() {
                const selList = document.getElementById('selectedParticipantsList');
                const momList = document.getElementById('momWritersList');
                const selCount = document.getElementById('selectedCount');
                const momCount = document.getElementById('momCount');

                if (selCount) selCount.textContent = selectedParticipants.length;
                if (momCount) momCount.textContent = momWriterId ? 1 : 0;

                // Update selected participants list
                if (selectedParticipants.length === 0) {
                    if (selList) {
                        selList.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="feather-user-plus"></i></div>
                                <p>No participants selected yet.</p>
                            </div>`;
                    }
                } else {
                    if (selList) {
                        selList.innerHTML = selectedParticipants.map(p => {
                            const isMom = (momWriterId === p.id);
                            const initials = getInitials(p.name);
                            return `
                                <div class="selected-participant" data-id="${p.id}">
                                    <div class="participant-avatar ${isMom ? 'mom-active' : ''}">${escapeHtml(initials)}</div>
                                    <div class="participant-info">
                                        <strong>${escapeHtml(p.name)}</strong>
                                        <small>${escapeHtml(p.email)}</small>
                                    </div>
                                    ${isEditable ? `
                                            <div class="participant-actions">
                                                <label class="mom-toggle-label ${isMom ? 'active' : ''}">
                                                    <input type="checkbox" class="mom-writer-checkbox"
                                                        data-id="${p.id}" ${isMom ? 'checked' : ''}>
                                                    <span class="mom-toggle-icon">✦</span>
                                                    <span class="mom-toggle-text">MOM</span>
                                                </label>
                                                <button type="button" class="remove-participant" data-id="${p.id}" title="Remove">
                                                    <i class="feather-x"></i>
                                                </button>
                                            </div>
                                            ` : (isMom ? `<span class="mom-writer-badge">✦ MOM Writer</span>` : '')}
                                </div>`;
                        }).join('');
                    }
                }

                // Update MOM writer list (only ONE person)
                const momData = selectedParticipants.filter(p => momWriterId === p.id);
                if (!momWriterId || momData.length === 0) {
                    if (momList) {
                        momList.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="feather-file-text"></i></div>
                                <p>No MOM writer assigned.</p>
                                ${isEditable ? '<p class="text-muted mt-2" style="font-size: 10px;">⚠️ Only ONE person can be MOM writer</p>' : ''}
                            </div>`;
                    }
                } else {
                    if (momList) {
                        momList.innerHTML = momData.map(p => `
                            <div class="selected-participant" data-id="${p.id}">
                                <div class="participant-avatar mom-active">${escapeHtml(getInitials(p.name))}</div>
                                <div class="participant-info">
                                    <strong>${escapeHtml(p.name)}</strong>
                                    <small>${escapeHtml(p.email)}</small>
                                </div>
                                <span class="mom-writer-badge">✦ MOM Writer</span>
                            </div>`).join('');
                    }
                }

                // Update hidden inputs
                const participantsInput = document.getElementById('participantsInput');
                const momWritersInput = document.getElementById('momWritersInput');

                if (participantsInput && isEditable) {
                    participantsInput.value = JSON.stringify(selectedParticipants.map(p => p.id));
                }
                if (momWritersInput && isEditable) {
                    momWritersInput.value = momWriterId ? JSON.stringify([momWriterId]) : JSON.stringify([]);
                }
            }

            // Handle participant selection from left panel (only if editable)
            const allUsersList = document.getElementById('allUsersList');
            if (allUsersList && isEditable) {
                allUsersList.addEventListener('click', function(e) {
                    const item = e.target.closest('.participant-item');
                    if (!item) return;

                    const userId = parseInt(item.dataset.userId);
                    const userName = item.dataset.userName;
                    const userEmail = item.dataset.userEmail;
                    const checkbox = item.querySelector('.participant-checkbox');

                    if (selectedParticipants.find(p => p.id === userId)) {
                        selectedParticipants = selectedParticipants.filter(p => p.id !== userId);
                        if (momWriterId === userId) {
                            momWriterId = null;
                        }
                        item.classList.remove('selected');
                        if (checkbox) checkbox.checked = false;
                    } else {
                        selectedParticipants.push({
                            id: userId,
                            name: userName,
                            email: userEmail
                        });
                        item.classList.add('selected');
                        if (checkbox) checkbox.checked = true;
                    }
                    updateSelectedParticipants();
                });
            }

            // Handle MOM checkbox toggle and removal (only if editable)
            const selectedList = document.getElementById('selectedParticipantsList');
            if (selectedList && isEditable) {
                selectedList.addEventListener('change', function(e) {
                    if (e.target.classList.contains('mom-writer-checkbox')) {
                        const userId = parseInt(e.target.dataset.id);

                        if (e.target.checked) {
                            momWriterId = userId;
                        } else {
                            if (momWriterId === userId) {
                                momWriterId = null;
                            }
                        }
                        updateSelectedParticipants();
                    }
                });

                selectedList.addEventListener('click', function(e) {
                    const btn = e.target.closest('.remove-participant');
                    if (!btn) return;
                    const userId = parseInt(btn.dataset.id);

                    selectedParticipants = selectedParticipants.filter(p => p.id !== userId);
                    if (momWriterId === userId) {
                        momWriterId = null;
                    }

                    const leftItem = document.querySelector(
                        `#allUsersList .participant-item[data-user-id="${userId}"]`);
                    if (leftItem) {
                        leftItem.classList.remove('selected');
                        const cb = leftItem.querySelector('.participant-checkbox');
                        if (cb) cb.checked = false;
                    }
                    updateSelectedParticipants();
                });
            }

            // Reminder pills (only if editable)
            if (isEditable) {
                const reminderPills = document.querySelectorAll('input[name="reminder_choice"]');
                if (reminderPills.length) {
                    reminderPills.forEach(radio => {
                        radio.addEventListener('change', function() {
                            document.getElementById('reminder_minutes').value = this.value;
                        });
                        if (radio.checked) {
                            document.getElementById('reminder_minutes').value = radio.value;
                        }
                    });
                }
            }

            // Form validation
            const meetingForm = document.getElementById('meetingForm');
            if (meetingForm && isEditable) {
                meetingForm.addEventListener('submit', function(e) {
                    if (selectedParticipants.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one participant for the meeting.');
                        return false;
                    }

                    if (meetingDate && meetingDate.value) {
                        const d = meetingDate.value;
                        if (d && new Date(d) < new Date().setHours(0, 0, 0, 0)) {
                            e.preventDefault();
                            alert('Meeting date cannot be in the past.');
                            return false;
                        }
                    }

                    if (startTime && endTime) {
                        validateTimes();
                        if (endTime.validationMessage) {
                            e.preventDefault();
                            alert('End time must be after start time.');
                            return false;
                        }
                    }

                    // Update hidden inputs one more time before submit
                    const participantsInput = document.getElementById('participantsInput');
                    const momWritersInput = document.getElementById('momWritersInput');
                    if (participantsInput) {
                        participantsInput.value = JSON.stringify(selectedParticipants.map(p => p.id));
                    }
                    if (momWritersInput) {
                        momWritersInput.value = momWriterId ? JSON.stringify([momWriterId]) : JSON
                            .stringify([]);
                    }
                });
            }

            // Initial update
            updateSelectedParticipants();

            // Auto-hide alerts
            setTimeout(() => {
                document.querySelectorAll('.alert .btn-close').forEach(btn => {
                    if (btn) btn.click();
                });
            }, 5000);
        });
    </script>
@endsection
