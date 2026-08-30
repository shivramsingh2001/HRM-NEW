@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== CARD GRID ==================== */
        .buildings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            /* padding: 16px; */
        }

        /* ==================== BUILDING CARD ==================== */
        .building-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            transition: all 0.2s ease;
            overflow: hidden;
        }

        .building-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-color: #cbd5e0;
        }

        /* Card Header */
        .card-header-custom {
            padding: 16px 16px 8px 16px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .building-icon {
            width: 36px;
            height: 36px;
            background: #f0f4ff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4b7bec;
            font-size: 18px;
        }

        /* Action Button */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            transition: all 0.2s;
            cursor: pointer;
        }

        .action-btn:hover {
            background: #f8fafc;
            color: #4b7bec;
        }

        /* Card Body */
        .card-body-custom {
            padding: 8px 16px 16px 16px;
        }

        .building-name {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .building-name i {
            color: #4b7bec;
            font-size: 14px;
        }

        .building-address {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
            display: flex;
            gap: 6px;
            margin-top: 6px;
        }

        .building-address i {
            color: #94a3b8;
            font-size: 13px;
            margin-top: 2px;
        }

        /* Card Footer */
        .card-footer-custom {
            padding: 12px 16px;
            background: #f8fafc;
            border-top: 1px solid #edf2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ==================== STATUS TOGGLE ==================== */
        .status-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toggle-switch {
            position: relative;
            width: 44px;
            height: 22px;
            background: #e2e8f0;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .toggle-switch.active {
            background: #10b981;
        }

        .toggle-switch .toggle-circle {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 18px;
            height: 18px;
            background: white;
            border-radius: 50%;
            transition: left 0.2s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .toggle-switch.active .toggle-circle {
            left: 24px;
        }

        .status-label {
            font-size: 13px;
            font-weight: 500;
        }

        .status-label.active {
            color: #10b981;
        }

        .status-label.inactive {
            color: #ef4444;
        }

        /* ==================== EMPTY STATE ==================== */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 48px 20px;
            background: white;
            border-radius: 12px;
            border: 1px dashed #e2e8f0;
        }

        .empty-state i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 12px;
        }

        .empty-state h4 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .empty-state p {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 16px;
        }

        /* ==================== LOADING OVERLAY ==================== */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid #e2e8f0;
            border-top: 3px solid #4b7bec;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* ==================== TOAST NOTIFICATION ==================== */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            padding: 12px 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: none;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            font-size: 13px;
            border-left: 3px solid;
        }

        .toast-notification.success {
            border-left-color: #10b981;
        }

        .toast-notification.error {
            border-left-color: #ef4444;
        }

        .toast-notification i {
            font-size: 16px;
        }

        .toast-notification.success i {
            color: #10b981;
        }

        .toast-notification.error i {
            color: #ef4444;
        }

       
        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #4b7bec;
            color: white;
        }

        .btn-primary:hover {
            background: #3a6bd4;
        }

        .btn-outline-secondary {
            background: white;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        .btn-outline-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .text-danger {
            font-size: 11px;
            color: #ef4444;
            margin-top: 4px;
            display: block;
        }

     
    </style>
@endsection

@section('content-area')
   
     <div class="page-header">
         <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">Payroll Master Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('payroll-masters.index') }}">Payroll</a></li>
                <li class="breadcrumb-item active">Payroll Details</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                <a href="{{ route('payroll-masters.create') }}" class="btn btn-primary btn-sm">
                    <i class="feather-plus me-1"></i>Add Payroll Master
                </a>
            </div>
        </div>
    </div>

    <div class="content-area-body pb-0 h-100">
        <!-- Buildings Grid -->
        <div class="buildings-grid" id="buildingsGrid">
            @forelse($payrollMasters as $payrollMaster)
                <div class="building-card" id="building-{{ $payrollMaster->id }}">
                   <div class="card-header-custom">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="building-icon">
                                <i class="feather-home"></i>
                            </div>
                            <span style="background: #e2e8f0; color: #475569; padding: 3px 8px; border-radius: 20px; font-size: 11px; font-weight: 500;">
                                {{ $payrollMaster->payroll_code ?? 'N/A' }}
                            </span>
                        </div>
                        <div class="building-actions">
                            <a class="dropdown-item edit-building"
                                href="{{ route('payroll-masters.edit', ['id' => $payrollMaster->id]) }}">
                                <i class="feather-edit-3"></i>
                            </a>
                        </div>
                    </div>

                    <div class="card-body-custom">
                        <h3 class="building-name">
                            <i class="feather-home"></i>
                            {{ $payrollMaster->name }}
                        </h3>
                        
                        @if ($payrollMaster->description)
                            <div class="building-address">
                                <i class="feather-map-pin"></i>
                                <span>{{ $payrollMaster->description }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="card-footer-custom">
                        <div class="status-toggle">
                            <div class="toggle-switch {{ $payrollMaster->status == 1 ? 'active' : '' }}"
                                onclick="toggleStatus({{ $payrollMaster->id }})">
                                <div class="toggle-circle"></div>
                            </div>
                            <span class="status-label {{ $payrollMaster->status == 1 ? 'active' : 'inactive' }}"
                                id="status-label-{{ $payrollMaster->id }}">
                                {{ $payrollMaster->status == 1 ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <i class="feather-home"></i>
                    <h4>No Payroll Master Found</h4>
                    <p>Get started by adding your first Payroll Master</p>
                    <!--<a class="btn btn-primary btn-sm" href="{{ route('payroll-masters.create') }}">-->
                    <!--    <i class="feather-plus me-1"></i>Add Payroll Master-->
                    <!--</a>-->
                </div>
            @endforelse
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toastNotification">
        <i class="feather-check-circle"></i>
        <span id="toastMessage"></span>
    </div>
@endsection

@section('create-modal')
   
@endsection

@section('script-area')
    <script>
        

        // ==================== TOGGLE STATUS FUNCTION ====================
       function toggleStatus(id) {
    // Get current status from the toggle class
    let toggleElement = $(`.toggle-switch`).filter(function() {
        return $(this).closest('.building-card').attr('id') === 'building-' + id;
    });
    
    let currentStatus = toggleElement.hasClass('active') ? 1 : 0;
    let newStatus = currentStatus == 1 ? 0 : 1;
    
    
    // CORRECTED URL - Remove the extra slash
    let url = "{{ route('payroll-masters.status', '') }}/" + id;
    // This was generating: /payroll-masters//status/1
   
    
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            _method: 'PATCH',
            status: newStatus
        },
        beforeSend: function() {
            $('#loadingOverlay').addClass('active');
        },
        success: function(response) {
            $('#loadingOverlay').removeClass('active');
            
            if (response.success) {
                const toggle = $(`.toggle-switch`).filter(function() {
                    return $(this).closest('.building-card').attr('id') === 'building-' + id;
                });
                const label = $(`#status-label-${id}`);
                
                if (response.status == 1) {
                    toggle.addClass('active');
                    label.removeClass('inactive').addClass('active').text('Active');
                    showToast('Payroll master activated successfully', 'success');
                } else {
                    toggle.removeClass('active');
                    label.removeClass('active').addClass('inactive').text('Inactive');
                    showToast('Payroll master deactivated successfully', 'success');
                }
            }
        },
        error: function(xhr) {
            $('#loadingOverlay').removeClass('active');
            showToast('Error changing status: ' + (xhr.responseJSON?.message || 'Unknown error'), 'error');
            console.error('Status update error:', xhr);
        }
    });
}

        // ==================== SHOW TOAST FUNCTION ====================
        function showToast(message, type = 'success') {
            const toast = $('#toastNotification');
            const icon = toast.find('i');
            const messageSpan = $('#toastMessage');

            toast.removeClass('success error');
            toast.addClass(type);

            icon.removeClass().addClass(type === 'success' ? 'feather-check-circle' : 'feather-alert-circle');
            messageSpan.text(message);

            toast.fadeIn(200);

            setTimeout(() => {
                toast.fadeOut(200);
            }, 2000);
        }
    </script>
@endsection
