@extends('client.layout.master')

@section('style')
    <style>
        /* Settings Page Styles */
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .settings-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #edf2f7;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .settings-header {
            padding: 20px 24px;
            border-bottom: 1px solid #edf2f7;
            background: #fafbfc;
        }

        .settings-header h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }

        .settings-header p {
            margin: 4px 0 0;
            font-size: 12px;
            color: #64748b;
        }

        .settings-body {
            padding: 24px;
        }

        .setting-item {
            margin-bottom: 24px;
        }

        .setting-item:last-child {
            margin-bottom: 0;
        }

        .setting-label {
            font-weight: 600;
            font-size: 14px;
            color: #1e293b;
            margin-bottom: 8px;
            display: block;
        }

        .setting-label i {
            color: #4f46e5;
            margin-right: 8px;
        }

        .setting-help {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 6px;
        }

        .setting-help i {
            font-size: 10px;
            margin-right: 4px;
        }

        .form-control-custom {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-control-custom:focus {
            border-color: #4f46e5;
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-select-custom {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        .switch-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: 0.3s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #4f46e5;
        }

        input:checked + .slider:before {
            transform: translateX(20px);
        }

        .info-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 16px;
            margin-top: 24px;
        }

        .info-box i {
            color: #10b981;
            font-size: 20px;
            margin-right: 12px;
        }

        .info-box-content {
            flex: 1;
        }

        .info-box-title {
            font-weight: 600;
            font-size: 14px;
            color: #065f46;
            margin-bottom: 4px;
        }

        .info-box-text {
            font-size: 12px;
            color: #047857;
        }

        .btn-save {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-save:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }

        .btn-reset {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            margin-right: 12px;
        }

        .btn-reset:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        @media (max-width: 768px) {
            .settings-container {
                padding: 0 16px;
            }
            
            .settings-body {
                padding: 16px;
            }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Overtime Settings</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Overtime Settings</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">
        <div class="settings-container">
            <form id="settingsForm" action="{{ route('overtime.settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <!-- General Settings Card -->
                <div class="settings-card">
                    <div class="settings-header">
                        <h5><i class="feather-sliders me-2"></i>General Settings</h5>
                        <p>Configure basic overtime rules and policies</p>
                    </div>
                    <div class="settings-body">
                        <div class="setting-item">
                            <label class="setting-label">
                                <i class="feather-dollar-sign"></i> Overtime Rate Multiplier
                            </label>
                            <select name="rate_multiplier" class="form-select-custom">
                                <option value="1.00" {{ ($settings->rate_multiplier ?? 1.5) == 1.00 ? 'selected' : '' }}>
                                    1.0x (Normal Rate)
                                </option>
                                <option value="1.25" {{ ($settings->rate_multiplier ?? 1.5) == 1.25 ? 'selected' : '' }}>
                                    1.25x
                                </option>
                                <option value="1.50" {{ ($settings->rate_multiplier ?? 1.5) == 1.50 ? 'selected' : '' }}>
                                    1.5x (Time and a Half) - Recommended
                                </option>
                                <option value="2.00" {{ ($settings->rate_multiplier ?? 1.5) == 2.00 ? 'selected' : '' }}>
                                    2.0x (Double Time)
                                </option>
                            </select>
                            <div class="setting-help">
                                <i class="feather-info"></i> Multiplier applied to regular hourly rate for overtime calculation
                            </div>
                        </div>

                        <div class="setting-item">
                            <label class="setting-label">
                                <i class="feather-clock"></i> Maximum Hours Per Day
                            </label>
                            <input type="number" name="max_hours_per_day" class="form-control-custom" 
                                   value="{{ $settings->max_hours_per_day ?? 4 }}" step="0.5" min="0" max="24">
                            <div class="setting-help">
                                <i class="feather-alert-circle"></i> Maximum overtime hours allowed per day (Leave empty for no limit)
                            </div>
                        </div>

                        <div class="setting-item">
                            <label class="setting-label">
                                <i class="feather-calendar"></i> Maximum Hours Per Month
                            </label>
                            <input type="number" name="max_hours_per_month" class="form-control-custom" 
                                   value="{{ $settings->max_hours_per_month ?? 50 }}" step="0.5" min="0">
                            <div class="setting-help">
                                <i class="feather-alert-circle"></i> Maximum overtime hours allowed per month (Leave empty for no limit)
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Approval Settings Card -->
                <div class="settings-card">
                    <div class="settings-header">
                        <h5><i class="feather-check-circle me-2"></i>Approval Settings</h5>
                        <p>Configure approval workflow for overtime requests</p>
                    </div>
                    <div class="settings-body">
                        <div class="setting-item">
                            <div class="switch-wrapper">
                                <div>
                                    <label class="setting-label" style="margin-bottom: 0;">
                                        <i class="feather-shield"></i> Require Manager Approval
                                    </label>
                                    <div class="setting-help">
                                        Employees must get approval before overtime is counted
                                    </div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="require_approval" value="1" 
                                           {{ ($settings->require_approval ?? true) ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="setting-item">
                            <label class="setting-label">
                                <i class="feather-zap"></i> Auto-Approve Limit (Hours)
                            </label>
                            <input type="number" name="auto_approve_limit" class="form-control-custom" 
                                   value="{{ $settings->auto_approve_limit ?? 2 }}" step="0.5" min="0">
                            <div class="setting-help">
                                <i class="feather-bell"></i> Requests with hours ≤ this limit will be automatically approved
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <!--<div class="info-box d-flex align-items-start">-->
                <!--    <i class="feather-info"></i>-->
                <!--    <div class="info-box-content">-->
                <!--        <div class="info-box-title">How Overtime is Calculated?</div>-->
                <!--        <div class="info-box-text">-->
                <!--            Overtime amount = (Basic Salary / 208) × Rate Multiplier × Overtime Hours<br>-->
                <!--            <small>* 208 = Average working hours per month (26 days × 8 hours)</small>-->
                <!--        </div>-->
                <!--    </div>-->
                <!--</div>-->

                <!-- Action Buttons -->
                <div class="d-flex justify-content-end mt-4">
                    <button type="button" class="btn-reset" id="resetBtn">
                        <i class="feather-refresh-cw me-1"></i> Reset to Default
                    </button>
                    <button type="submit" class="btn-save">
                        <i class="feather-save me-1"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script-area')
<script>
    toastr.options = {
        "closeButton": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "timeOut": "3000"
    };

    $(document).ready(function() {
        // Save Settings
        $('#settingsForm').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                url: $(this).attr('action'),
                type: 'PUT',
                data: $(this).serialize(),
                success: function(response) {
                    if(response.success) {
                        toastr.success('Settings saved successfully!');
                        setTimeout(() => location.reload(), 1000);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to save settings');
                }
            });
        });

        // Reset to Default
        $('#resetBtn').on('click', function() {
            if(confirm('Reset all settings to default values?')) {
                $.ajax({
                    url: "{{ route('overtime.settings.reset') }}",
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        _method: 'POST'
                    },
                    success: function(response) {
                        if(response.success) {
                            toastr.success('Settings reset to default');
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function() {
                        toastr.error('Failed to reset settings');
                    }
                });
            }
        });
    });
</script>
@endsection