    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/logo/shurt_logo_black.png') }}" />
    <!--! END: Favicon-->
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/bootstrap.min.css') }}" />
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/vendors.min.css') }}" />
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/daterangepicker.min.css') }}" />
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/theme.min.css') }}" />
    <!--! END: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    {{-- <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/dataTables.bs5.min.css') }}"> --}}

    <!--<style>-->
    <!--    .card-footer .remove-internal-para p {-->
    <!--        display: none;-->
    <!--    }-->

    <!--    .btn-icon {-->
    <!--        padding: 5px 5px !important;-->
    <!--    }-->
    <!--    .btn-primary {-->
    <!--        background: #4f46e5;-->
    <!--        color: #fff;-->
    <!--        border: none;-->
    <!--        font-size: 12px;-->
    <!--        padding: 8px 16px;-->
    <!--        border-radius: 8px;-->
    <!--    }-->

    <!--    .btn-primary:hover {-->
    <!--        background: #4338ca;-->
    <!--    }-->
    <!--      :root {-->
    <!--        --primary: #4f46e5;-->
    <!--        --primary-light: #eef2ff;-->
    <!--        --success: #10b981;-->
    <!--        --success-light: #d1fae5;-->
    <!--        --danger: #ef4444;-->
    <!--        --danger-light: #fee2e2;-->
    <!--        --warning: #f59e0b;-->
    <!--        --warning-light: #fef3c7;-->
    <!--        --gray-50: #f8fafc;-->
    <!--        --gray-100: #f1f5f9;-->
    <!--        --gray-200: #e2e8f0;-->
    <!--        --gray-300: #cbd5e1;-->
    <!--        --gray-400: #94a3b8;-->
    <!--        --gray-500: #64748b;-->
    <!--        --gray-600: #475569;-->
    <!--        --gray-700: #334155;-->
    <!--        --gray-800: #1e293b;-->
    <!--        --gray-900: #0f172a;-->
    <!--    }-->
        
    <!--    .table-responsive {-->
    <!--        overflow-x: auto !important;-->
    <!--        touch-action: pan-x pan-y !important;-->
    <!--        cursor: grab;-->
    <!--    }-->
        
    <!--    .table-responsive.grabbing {-->
    <!--        cursor: grabbing;-->
    <!--        user-select: none;-->
    <!--    }-->

    <!--</style>-->
    <style>
        .card-footer .remove-internal-para p {
            display: none;
        }

        .btn-icon {
            padding: 5px 5px !important;
        }

        .btn-primary {
            background: #4f46e5;
            color: #fff;
            border: none;
            font-size: 12px;
            padding: 8px 16px;
            border-radius: 8px;
        }

        .btn-primary:hover {
            background: #4338ca;
        }

        :root {
            --primary: #2563eb;
            --primary-light: #eff6ff;
            --primary-mid: #bfdbfe;
            --primary-dark: #1d4ed8;
            --purple: #7c3aed;
            --purple-light: #f5f3ff;
            --purple-mid: #ddd6fe;
            --success: #059669;
            --success-light: #ecfdf5;
            --danger: #dc2626;
            --danger-light: #fef2f2;
            --warning: #d97706;
            --warning-light: #fffbeb;
            --border: #e5e7eb;
            --border-focus: #93c5fd;
            --surface: #ffffff;
            --surface-2: #f9fafb;
            --surface-3: #f3f4f6;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 18px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .06), 0 1px 2px rgba(0, 0, 0, .04);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, .08);
            --shadow-focus: 0 0 0 3px rgba(37, 99, 235, .18);
            --font: 'DM Sans', system-ui, sans-serif;
            --transition: .15s ease;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
        }


        .table-responsive {
            overflow-x: auto !important;
            touch-action: pan-x pan-y !important;
            cursor: grab;
        }

        .table-responsive.grabbing {
            cursor: grabbing;
            user-select: none;
        }

        /* ==================== EMPLOYEE INFO ==================== */
        .employee-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .employee-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e0e7ff;
            color: #4f46e5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
        }

        .employee-details {
            line-height: 1.3;
        }

        .employee-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 13px;
        }

        .employee-id {
            font-size: 10px;
            color: #64748b;
        }

        /* Simple consistent styling */
        .custom-employee-dropdown .btn {
            height: 36px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #1e293b;
            font-size: 13px;
            padding: 0 12px;
        }

        .custom-employee-dropdown .btn:hover {
            background: #ffffff;
            border-color: #cbd5e1;
        }

        .custom-employee-dropdown .btn:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* Consistent initials style - same color for all */
        .employee-initials,
        .employee-initials-sm {
            width: 28px;
            height: 28px;
            background: #4f46e5;
            /* Single consistent color */
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
            flex-shrink: 0;
        }

        .employee-initials-sm {
            width: 24px;
            height: 24px;
            font-size: 11px;
        }

        /* Dropdown menu styling */
        .custom-employee-dropdown .dropdown-menu {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 8px;
            max-height: 300px;
            overflow-y: auto;

            min-width: 250px;
        }

        .custom-employee-dropdown .dropdown-item {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .custom-employee-dropdown .dropdown-item:hover {
            background: #f1f5f9;
        }

        .custom-employee-dropdown .dropdown-item.active {
            background: #eef2ff;
            color: #4f46e5;
        }

        .custom-employee-dropdown .dropdown-item.active .text-muted {
            color: #4f46e5 !important;
            opacity: 0.8;
        }

        /* Employee name styling */
        .employee-name {
            color: #1e293b;
            font-weight: 500;
        }
    </style>
