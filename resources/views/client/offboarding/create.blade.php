@extends('client.layout.master')

@section('style')
    <style>
        .ob-card {
            background: #fff;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 14px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
            max-width: 680px;
        }

        .ob-card__head {
            padding: 12px 18px;
            border-bottom: 1px solid #f1f5f9;
            background: #fafbfc;
        }

        .ob-card__title {
            margin: 0;
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ob-card__title i {
            font-size: 14px;
            color: var(--icon-color, #0D6EFD);
        }

        .ob-card__body {
            padding: 18px;
        }

        .field-label {
            font-size: 11.5px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .field-input,
        .field-select,
        textarea.field-input {
            width: 100%;
            font-size: 12.5px;
            color: #1e293b;
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            background: #fff;
            transition: all .15s ease;
            outline: none;
        }

        .field-input:focus,
        .field-select:focus {
            border-color: var(--primary, #0D6EFD);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.08);
        }

        .field-hint {
            font-size: 10.5px;
            color: #94a3b8;
            margin-top: 4px;
        }

        .hint-bar {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 8px;
            background: #f8fafc;
            border-left: 3px solid var(--primary, #0D6EFD);
            margin: 4px 0 16px;
        }

        .hint-bar i {
            font-size: 12px;
            color: var(--icon-color, #0D6EFD);
            margin-top: 2px;
            flex-shrink: 0;
        }

        .hint-bar span {
            font-size: 11px;
            color: #475569;
            line-height: 1.5;
        }

        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD);
            color: #fff;
            border: none;
            font-size: 12.5px;
            font-weight: 500;
            padding: 7px 18px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-save:hover {
            filter: brightness(0.9);
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
        }

        .alert-box {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.5;
            max-width: 680px;
        }

        .alert-box--danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fee2e2;
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="New Offboarding Request" />

    <div class="main-content" style="padding: 20px !important;">

        @if (session('error'))
            <div class="alert-box alert-box--danger"><i class="feather-alert-circle"></i><span>{{ session('error') }}</span></div>
        @endif
        @if ($errors->any())
            <div class="alert-box alert-box--danger"><i class="feather-alert-triangle"></i><span>{{ $errors->first() }}</span></div>
        @endif

        <div class="ob-card">
            <div class="ob-card__head">
                <h5 class="ob-card__title"><i class="feather-user-minus"></i> Employee Exit Details</h5>
            </div>
            <div class="ob-card__body">
                <form action="{{ route('offboarding.store') }}" method="POST" id="offboardingForm">
                    @csrf

                    @if (count($employees) > 1)
                        <div class="mb-3">
                            <label for="employee_id" class="field-label"><i class="feather-user"></i> Employee</label>
                            <select name="employee_id" id="employee_id" class="field-select" required>
                                <option value="">Select employee</option>
                                @foreach ($employees as $emp)
                                    <option value="{{ $emp->id }}" {{ old('employee_id', $selectedEmployee?->id) == $emp->id ? 'selected' : '' }}>
                                        {{ $emp->name }} ({{ $emp->employee_id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="employee_id" value="{{ $selectedEmployee?->id }}">
                        <div class="mb-3">
                            <label class="field-label"><i class="feather-user"></i> Employee</label>
                            <input type="text" class="field-input" value="{{ $selectedEmployee?->name }}" disabled>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="reason" class="field-label"><i class="feather-flag"></i> Reason</label>
                        <select name="reason" id="reason" class="field-select" required>
                            <option value="">Select reason</option>
                            @foreach ($reasons as $value => $label)
                                <option value="{{ $value }}" {{ old('reason') == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="hint-bar">
                        <i class="feather-info"></i>
                        <span id="noticeHint">Minimum notice period: {{ $noticeDays }} day(s). Last working date must be on or after that.</span>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="resignation_date" class="field-label"><i class="feather-calendar"></i> Request Date</label>
                            <input type="date" class="field-input" id="resignation_date" name="resignation_date"
                                value="{{ old('resignation_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-md-6">
                            <label for="last_working_date" class="field-label"><i class="feather-log-out"></i> Last Working Date</label>
                            <input type="date" class="field-input" id="last_working_date" name="last_working_date"
                                value="{{ old('last_working_date') }}" required>
                            <div class="field-hint" id="minDateHint"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="reason_detail" class="field-label"><i class="feather-file-text"></i> Details</label>
                        <textarea class="field-input" id="reason_detail" name="reason_detail" rows="3" maxlength="2000">{{ old('reason_detail') }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label for="feedback" class="field-label"><i class="feather-message-square"></i> Additional Feedback (optional)</label>
                        <textarea class="field-input" id="feedback" name="feedback" rows="2" maxlength="2000">{{ old('feedback') }}</textarea>
                    </div>

                    @if (in_array($selectedEmployee ? auth()->user()->role : auth()->user()->role, ['admin', 'hr']))
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="eligible_for_rehire" name="eligible_for_rehire" value="1"
                                {{ old('eligible_for_rehire', true) ? 'checked' : '' }}>
                            <label class="form-check-label field-label d-inline" for="eligible_for_rehire" style="margin:0">Eligible for rehire</label>
                        </div>
                    @endif

                    <button type="submit" class="btn-save"><i class="feather-send"></i> Submit Request</button>
                </form>
            </div>
        </div>

    </div>
@endsection

@section('script-area')
    <script>
        (function () {
            'use strict';

            var reasonRules = @json($reasonRules);
            var noticeDays = {{ (int) $noticeDays }};
            var reasonSelect = document.getElementById('reason');
            var lastWorkingDate = document.getElementById('last_working_date');
            var noticeHint = document.getElementById('noticeHint');
            var minDateHint = document.getElementById('minDateHint');

            function todayPlus(days) {
                var d = new Date();
                d.setDate(d.getDate() + days);
                return d.toISOString().split('T')[0];
            }

            function sync() {
                var reason = reasonSelect.value;
                var rules = reasonRules[reason];
                var requiresNotice = !rules || rules.requires_notice;

                if (requiresNotice) {
                    var minDate = todayPlus(noticeDays);
                    lastWorkingDate.min = minDate;
                    minDateHint.textContent = 'Earliest allowed: ' + minDate;
                    noticeHint.textContent = 'Minimum notice period: ' + noticeDays + ' day(s). Last working date must be on or after that.';
                } else {
                    lastWorkingDate.removeAttribute('min');
                    minDateHint.textContent = '';
                    noticeHint.textContent = 'This reason does not require a minimum notice period.';
                }
            }

            reasonSelect.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection
