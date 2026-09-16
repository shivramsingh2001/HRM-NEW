@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== PAGE STYLES ==================== */


        .manual-credit-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #edf2f7;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
        }

        .card-header-custom {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            padding: 20px 24px;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-header-custom i {
            width: 40px;
            height: 40px;
            background: #1e3a8a;
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .card-header-custom h5 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }

        .card-header-custom p {
            margin: 4px 0 0;
            font-size: 13px;
            color: #64748b;
        }

        /* ==================== FORM STYLES ==================== */
        .form-section {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 14px;
            color: #334155;
            margin-bottom: 8px;
        }

        .form-label i {
            color: #1e3a8a;
            font-size: 16px;
        }

        .required-star {
            color: #1e3a8a;
            margin-left: 4px;
            font-size: 14px;
        }

        /* Select2 Customization */
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 6px 12px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 28px;
            color: #1e293b;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
            right: 8px;
        }

        .select2-dropdown {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        /* Form Controls */
        .form-control-modern {
            width: 100%;
            height: 42px;
            padding: 8px 14px;
            font-size: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .form-control-modern:focus {
            border-color: #1e3a8a;
            outline: none;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            background: white;
        }

        .form-control-modern.is-invalid {
            border-color: #1e3a8a;
            background: #e3edfe;
        }

        textarea.form-control-modern {
            height: auto;
            min-height: 100px;
            resize: vertical;
        }

      

        /* Balance Preview Card */
        .balance-preview {
            background: linear-gradient(145deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            border: 1px dashed #cbd5e1;
        }

        .balance-preview-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .balance-preview-title i {
            color: #1e3a8a;
            font-size: 16px;
        }

        .balance-amount {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .balance-amount small {
            font-size: 14px;
            font-weight: 400;
            color: #64748b;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            background: #1e3a8a;
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }

        .employee-details {
            flex: 1;
        }

        .employee-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 12px;
            margin-bottom: 2px;
        }

        .employee-meta {
            font-size: 8px;
            color: #64748b;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .btn-modern {
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-primary-modern {
            background: #1e3a8a;
            color: white;
            box-shadow: 0 4px 6px rgba(30, 58, 138, 0.2);
        }

        .btn-primary-modern:hover:not(:disabled) {
            background: #1e3a8a;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(30, 58, 138, 0.3);
        }

        .btn-secondary-modern {
            background: white;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary-modern:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .btn-primary-modern:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Info Tooltip */
        .info-tooltip {
            display: inline-flex;
            margin-left: 6px;
            color: #94a3b8;
            cursor: help;
            position: relative;
        }

        .info-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        .tooltip-text {
            visibility: hidden;
            opacity: 0;
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            transition: all 0.2s;
            pointer-events: none;
            margin-bottom: 8px;
        }

        .tooltip-text::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border-width: 5px;
            border-style: solid;
            border-color: #1e293b transparent transparent transparent;
        }

        /* Loading Spinner */
        .spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* Alert Messages */
        .alert-message {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: #e3edfe;
            border: 1px solid #93c5fd;
            color: #1d4ed8;
        }

        .alert-error {
            background: #e3edfe;
            border: 1px solid #93c5fd;
            color: #1e3a8a;
        }

        .alert-warning {
            background: #e3edfe;
            border: 1px solid #93c5fd;
            color: #2563eb;
        }

        .alert-info {
            background: #e0f2fe;
            border: 1px solid #bae6fd;
            color: #0369a1;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .balance-amount {
                font-size: 24px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-modern {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Add Leave Credit" :parent="['label' => 'Leave Credit', 'route' => 'leave-credit.index']" />

    <div class="main-content" style="padding: 20px !important;">
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <!-- Alert Container for dynamic messages -->
                <div id="alertContainer"></div>

                <div class="manual-credit-card">
                    <div class="card-header-custom">
                        <i class="fas fa-plus-circle"></i>
                        <div>
                            <h5>Add Manual Credit</h5>
                            <p>Select employee and leave type to add credit</p>
                        </div>
                    </div>

                    <div class="form-section">
                        <form action="{{ route('leave-credit.manual.store') }}" method="POST" id="manualCreditForm">
                            @csrf

                            <!-- Employee Selection -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user"></i>
                                    Select Employee
                                    <span class="required-star">*</span>
                                    <span class="info-tooltip">
                                        <i class="fas fa-info-circle"></i>
                                        <span class="tooltip-text">Choose employee to credit leaves</span>
                                    </span>
                                </label>
                                <select name="user_id" class="form-control select2" id="employeeSelect" required>
                                    <option value="">Search and select employee...</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}"
                                            data-balance="{{ optional($user->leaveBalance)->balance ?? 0 }}"
                                            data-joining="{{ $user->joining_date ? date('d M Y', strtotime($user->joining_date)) : 'N/A' }}"
                                            data-email="{{ $user->email }}">
                                            {{ $user->name }} ({{ $user->email }}) - Current:
                                            {{ optional($user->leaveBalance)->balance ?? 0 }} days
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Selected Employee Preview (shown after selection) -->
                            <div id="employeePreview" style="display: none;" class="mb-4">
                                <div class="employee-info">
                                    <div class="employee-avatar" id="employeeInitials">JD</div>
                                    <div class="employee-details">
                                        <div class="employee-name" id="employeeName"></div>
                                        <div class="employee-meta" id="employeeMeta"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Leave Type Selection -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-tag"></i>
                                    Leave Type
                                    <span class="required-star">*</span>
                                </label>
                                <select name="leave_type_id" class="form-control" id="leaveTypeSelect" required>
                                    <option value="">Select Leave Type</option>
                                    @foreach ($leaveTypes as $type)
                                        <option value="{{ $type->id }}" data-credit="{{ $type->credit_value }}"
                                            data-frequency="{{ $type->credit_type }}"
                                            data-description="{{ $type->description }}">
                                            {{ $type->name }} ({{ $type->credit_value }} {{ $type->credit_type }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted" id="leaveTypeDescription"></small>
                            </div>

                            <!-- Credit Value -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calculator"></i>
                                    Credit Value (Days)
                                    <span class="required-star">*</span>
                                    <span class="info-tooltip">
                                        <i class="fas fa-info-circle"></i>
                                        <span class="tooltip-text">Enter in days (e.g., 1, 1.5, 2)</span>
                                    </span>
                                </label>
                                <div class="input-group">
                                    {{-- <span class="input-group-text">Days</span> --}}
                                    <input type="number" name="credit_value" class="form-control-modern" id="creditValue"
                                        step="0.5" min="0.5" required placeholder="Enter credit value">
                                </div>
                            </div>

                            <!-- Balance Preview -->
                            <div class="balance-preview">
                                <div class="balance-preview-title">
                                    <i class="fas fa-chart-line"></i>
                                    Balance Preview
                                </div>
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="balance-amount" id="currentBalance">0.00</div>
                                        <small class="text-muted">Current Balance</small>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="balance-amount" id="newBalance">0.00</div>
                                        <small class="text-muted">Balance After Credit</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Remarks -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-comment"></i>
                                    Remarks
                                </label>
                                <textarea name="remarks" class="form-control-modern" id="remarks" rows="3"
                                    placeholder="Enter reason for manual credit (optional)"></textarea>
                            </div>

                            <!-- Action Buttons -->
                            <div class="action-buttons">
                                <button type="submit" class="btn-modern btn-primary-modern" id="submitBtn">
                                    <i class="fas fa-save"></i>
                                    <span>Add Credit</span>
                                </button>
                                <a href="{{ route('leave-credit.index') }}" class="btn-modern btn-secondary-modern">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
   
    <script>
        // Configure Toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut",
            "preventDuplicates": true
        };

        $(document).ready(function() {
          

            // Show alert function
            function showAlert(type, message) {
                const alertHtml = `
                <div class="alert-message alert-${type}">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
                $('#alertContainer').html(alertHtml);

                // Auto hide after 5 seconds
                setTimeout(() => {
                    $('#alertContainer').fadeOut(300, function() {
                        $(this).html('').fadeIn(300);
                    });
                }, 5000);
            }

            // Get initials from name
            function getInitials(name) {
                if (!name) return 'NA';
                return name.split(' ').map(word => word[0]).join('').toUpperCase().substring(0, 2);
            }

            // Format number to 2 decimal places
            function formatNumber(num) {
                num = parseFloat(num) || 0;
                return num.toFixed(2);
            }

            // Update new balance calculation
            function updateNewBalance() {
                const currentBalance = parseFloat($('#currentBalance').text()) || 0;
                const creditValue = parseFloat($('#creditValue').val()) || 0;
                const newBalance = currentBalance + creditValue;
                $('#newBalance').text(formatNumber(newBalance));
            }

            // Handle employee selection change
            $('#employeeSelect').on('change', function() {
                const selected = $(this).find('option:selected');
                const userId = $(this).val();
                console.log('Employee selected:',userId);
                if (userId && userId !== '') {
                    // Get data from selected option
                    const fullText = selected.text();
                    const name = fullText.split(' (')[0];
                    const email = selected.data('email');
                    const joiningDate = selected.data('joining');
                    const balance = selected.data('balance');

                    // Ensure balance is a number
                    const currentBalance = parseFloat(balance) || 0;

                    console.log('Employee selected:', {
                        name: name,
                        email: email,
                        balance: currentBalance
                    });

                    // Show employee preview
                    $('#employeePreview').show();
                    $('#employeeInitials').text(getInitials(name));
                    $('#employeeName').text(name);
                    $('#employeeMeta').html(
                        `<i class="fas fa-envelope me-1"></i> ${email} | <i class="fas fa-calendar me-1"></i> Joined: ${joiningDate}`
                    );

                    // Update balance display
                    $('#currentBalance').text(formatNumber(currentBalance));

                    // Reset credit value
                    $('#creditValue').val('');

                    // Update new balance
                    updateNewBalance();
                } else {
                    // Hide preview and reset balances
                    $('#employeePreview').hide();
                    $('#currentBalance').text('0.00');
                    $('#newBalance').text('0.00');
                    $('#creditValue').val('');
                }
            });

            // Handle leave type selection
            $('#leaveTypeSelect').on('change', function() {
                const selected = $(this).find('option:selected');
                const creditValue = selected.data('credit') || 0;
                const description = selected.data('description');
                const frequency = selected.data('frequency');

                // Show description if available
                if (description) {
                    $('#leaveTypeDescription').text(`📝 ${description}`);
                } else {
                    $('#leaveTypeDescription').text(`⏱️ Frequency: ${frequency || 'N/A'}`);
                }

                // Auto-fill credit value if not manually entered
                const currentCredit = $('#creditValue').val();
                if (!currentCredit && creditValue > 0) {
                    $('#creditValue').val(creditValue);
                }

                updateNewBalance();
            });

            // Handle credit value change
            $('#creditValue').on('input', function() {
                updateNewBalance();
            });

            // Form submission
            $('#manualCreditForm').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var url = form.attr('action');
                var data = form.serialize();
                var submitBtn = $('#submitBtn');
                var originalText = submitBtn.html();

                // Validate that an employee is selected
                if (!$('select[name="user_id"]').val()) {
                    toastr.error('Please select an employee');
                    return false;
                }

                // Validate credit value
                var creditValue = parseFloat($('#creditValue').val());
                if (!creditValue || creditValue <= 0) {
                    toastr.error('Please enter a valid credit value');
                    return false;
                }

                // Show loading state
                submitBtn.prop('disabled', true).html(
                    '<i class="fas fa-spinner fa-spin"></i> Processing...');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            toastr.success(response.message);
                            showAlert('success', response.message);

                            // Update balance in dropdown
                            var selectedOption = $('select[name="user_id"] option:selected');
                            var newBalance = response.new_balance || 0;

                            // Update option text with new balance
                            var oldText = selectedOption.text();
                            var newText = oldText.replace(/Current: [\d.]+/, 'Current: ' +
                                formatNumber(newBalance));
                            selectedOption.text(newText);
                            selectedOption.data('balance', newBalance);

                            // Update preview
                            $('#currentBalance').text(formatNumber(newBalance));
                            $('#newBalance').text(formatNumber(newBalance));
                            $('#creditValue').val('');

                            // Reset leave type
                            $('select[name="leave_type_id"]').val('').trigger('change');
                            $('textarea[name="remarks"]').val('');

                            // Redirect after 2 seconds
                            setTimeout(function() {
                                window.location.href =
                                    "{{ route('leave-credit.index') }}";
                            }, 2000);
                        } else {
                            toastr.error(response.message);
                            submitBtn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function(xhr) {
                        submitBtn.prop('disabled', false).html(originalText);

                        if (xhr.status === 422) {
                            var errors = xhr.responseJSON.errors;
                            if (errors) {
                                $.each(errors, function(key, value) {
                                    toastr.error(value[0]);
                                });
                            }
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            toastr.error(xhr.responseJSON.message);
                        } else {
                            toastr.error('An error occurred while processing');
                        }
                    }
                });

                return false;
            });

            // Initialize with default values
            updateNewBalance();

            // Trigger change event on page load if there's a pre-selected value
            if ($('#employeeSelect').val()) {
                $('#employeeSelect').trigger('change');
            }
        });
    </script>
@endsection
