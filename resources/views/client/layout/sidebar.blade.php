<!--! ================================================================ !-->
<!--! [Start] Navigation Manu !-->
<!--! ================================================================ !-->

@php
    $user = Auth::user();
    $role = $user->role;
@endphp
<nav class="nxl-navigation">
    <div class="navbar-wrapper">
        <div class="m-header justify-content-center">
            <a href="{{ route('dashboard') }}" class="b-brand">
                <!-- ========   change your logo hear   ============ -->
                <img src="{{ asset('assets/images/logo/shurt_logo_black.png') }}" alt="Shurt Tech" class="logo logo-lg"
                    height="50" />
                <img src="{{ asset('assets/images/logo/shurt_logo_black.png') }}" alt="Shurt Tech"
                    class="logo logo-sm" />
                <!-- <span class="h2">Shurt Tech</span> -->
            </a>
        </div>
        <div class="navbar-content">
            <ul class="nxl-navbar">
                <li class="nxl-item nxl-caption">
                    <label>Navigation</label>
                </li>

                {{-- ==================== Tier 1: Daily core ==================== --}}
                <li class="nxl-item nxl-hasmenu">
                    <a href="{{ route('dashboard') }}" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-home"></i></span>
                        <span class="nxl-mtext">Dashboards</span><span class="nxl-arrow"></span>
                    </a>
                </li>

                {{-- ==================== Admin-only grouped order:
                     Team, Holiday, Reports, Project, Meeting, Hiring, Employee,
                     Shift, Asset, Payroll, Performance, Approval, Company, Basic Setup ==================== --}}
                @if ($role === 'admin')
                    <li class="nxl-item">
                        <a href="{{ route('team.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-users"></i></span>
                            <span class="nxl-mtext">Team</span><span class="nxl-arrow"></span>
                        </a>
                    </li>

                    @feature('holiday')
                        <li class="nxl-item">
                            <a href="{{ route('holiday.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-sun"></i></span>
                                <span class="nxl-mtext">Holidays</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endfeature

                    @feature('daily_reports')
                        <li class="nxl-item">
                            <a href="{{ route('report.attendance.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-bar-chart-2"></i></span>
                                <span class="nxl-mtext">Reports</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endfeature

                    @feature('project_management')
                        <li class="nxl-item">
                            <a href="{{ route('project.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-folder"></i></span>
                                <span class="nxl-mtext">Projects</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endfeature

                    @feature('meetings')
                        @if ($user->id != 162)
                            <li class="nxl-item">
                                <a href="{{ route('meetings.index') }}" class="nxl-link">
                                    <span class="nxl-micon"><i class="feather-video"></i></span>
                                    <span class="nxl-mtext">Meetings</span><span class="nxl-arrow"></span>
                                </a>
                            </li>
                        @endif
                    @endfeature

                    @feature('recruitment')
                        <li class="nxl-item">
                            <a href="{{ route('job-openings.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-user-check"></i></span>
                                <span class="nxl-mtext">Hiring</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endfeature

                    <li class="nxl-item">
                        <a href="{{ route('employee.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-user-plus"></i></span>
                            <span class="nxl-mtext">Employee</span><span class="nxl-arrow"></span>
                        </a>
                    </li>

                    @if (optional($currentTenant)->custom_shifts_enabled && app(\App\Services\FeatureService::class)->enabledForCurrentTenant('custom_shift'))
                        <li class="nxl-item nxl-hasmenu">
                            <a href="#" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-sunrise"></i></span>
                                <span class="nxl-mtext">Shift</span><span class="nxl-arrow"><i
                                        class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('shift.roster') }}">
                                        Shift Roster</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('shift.index') }}">
                                        Manage Shifts</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('shift-settings.index') }}">
                                        Shift Settings</a>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li class="nxl-item">
                            <a href="{{ route('shift-settings.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-sunrise"></i></span>
                                <span class="nxl-mtext">Shift Settings</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endif

                    @feature('asset_management')
                        <li class="nxl-item nxl-hasmenu">
                            <a href="#" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-hard-drive"></i></span>
                                <span class="nxl-mtext">Assets</span><span class="nxl-arrow"><i
                                        class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('assets.index') }}">
                                        All Assets</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('my-assets.index') }}">
                                        My Assets</a>
                                </li>
                            </ul>
                        </li>
                    @endfeature

                    @feature('payroll')
                        <li class="nxl-item nxl-hasmenu">
                            <a href="#" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-dollar-sign"></i></span>
                                <span class="nxl-mtext">Payrolls</span><span class="nxl-arrow"><i
                                        class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('monthly-payrolls.index') }}">
                                        Payroll Monthly</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-components.index') }}">
                                        Payroll Components</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-structures.index') }}">
                                        Payroll Structures</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-employee-structures.index') }}">
                                        Employee Payroll Structures</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-compliance.index') }}">
                                        Statutory Compliance</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-bonuses.index') }}">
                                        Payroll Bonuses</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-arrears.index') }}">
                                        Payroll Arrears</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-engine-settings.index') }}">
                                        Engine Settings</a>
                                </li>
                            </ul>
                        </li>
                    @endfeature

                    @feature('kpi_performance')
                        <li class="nxl-item nxl-hasmenu">
                            <a href="#" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-trending-up"></i></span>
                                <span class="nxl-mtext">Performance</span><span class="nxl-arrow"><i
                                        class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('performance.team') }}">
                                        Team Performance</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('performance.reviews.index') }}">
                                        Team Review</a>
                                </li>
                            </ul>
                        </li>
                    @endfeature

                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-check-circle"></i></span>
                            <span class="nxl-mtext">Approval Requests</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @feature('leave_management')
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('leave.view-all') }}">
                                        Leave</a>
                                </li>
                            @endfeature
                            @feature('regularization')
                                <li class="nxl-item"><a class="nxl-link"
                                        href="{{ route('attendance-regularization.manage') }}">
                                        Regularization</a>
                                </li>
                            @endfeature
                            @feature('wfh_travel')
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('manager.requests') }}">
                                        WFH & Travel</a>
                                </li>
                            @endfeature
                            @feature('expense_management')
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.view-all') }}">
                                        Expense</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.payments.index') }}">
                                        Expense Payment</a>
                                </li>
                                @if (\App\Support\ExpenseFeatures::payrollRouteEnabled((int) auth()->user()->tenant_id))
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.payroll.index') }}">
                                            Reimbursements via Payroll</a>
                                    </li>
                                @endif
                            @endfeature
                            @feature('loan_management')
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('loan.approvals.pending') }}">
                                        Loans & Advances</a>
                                </li>
                            @endfeature
                            @feature('overtime')
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('overtime.view-all') }}">
                                        Overtime</a>
                                </li>
                            @endfeature
                            @if (app(\App\Services\FeatureService::class)->enabledForCurrentTenant('task_single') || app(\App\Services\FeatureService::class)->enabledForCurrentTenant('task_group'))
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('task.assigned-by-me') }}">
                                        Tasks</a>
                                </li>
                            @endif
                            @feature('offboarding')
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('offboarding.index') }}">
                                        Offboarding</a>
                                </li>
                            @endfeature
                        </ul>
                    </li>

                    @if (in_array($role, ['admin', 'hr']))
                        <li class="nxl-item nxl-hasmenu">
                            <a href="#" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-briefcase"></i></span>
                                <span class="nxl-mtext">Company</span><span class="nxl-arrow"><i
                                        class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">
                                @feature('branches')
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('branch.index') }}">
                                            Branch</a>
                                    </li>
                                @endfeature
                                @feature('leave_management')
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('leave-type.index') }}">
                                            Leave Types</a>
                                    </li>
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('leave-credit.index') }}">
                                            Leave Credit</a>
                                    </li>
                                @endfeature
                                @feature('expense_management')
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('expense-type.index') }}">
                                            Expense Types</a>
                                    </li>
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.budgets.index') }}">
                                            Expense Budgets</a>
                                    </li>
                                @endfeature
                                @feature('loan_management')
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('loan.categories.index') }}">
                                            Loan & Advance Types</a>
                                    </li>
                                @endfeature
                                @feature('asset_management')
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('asset-categories.index') }}">
                                            Asset Categories</a>
                                    </li>
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('asset-types.index') }}">
                                            Asset Types</a>
                                    </li>
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('asset-vendors.index') }}">
                                            Asset Vendors</a>
                                    </li>
                                @endfeature
                            </ul>
                        </li>
                    @endif

                    @if (in_array($role, ['admin', 'hr']))
                        <li class="nxl-item nxl-hasmenu">
                            <a href="#" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-sliders"></i></span>
                                <span class="nxl-mtext">Basic Setup</span><span class="nxl-arrow"><i
                                        class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('department.index') }}">
                                        Departments</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('designation.index') }}">
                                        Designation</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('attendance-location.index') }}">
                                        Attendance Locations</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('workforce-settings.index') }}">
                                        Company Policies</a>
                                </li>
                                @feature('broadcast_notifications')
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('broadcast.index') }}">
                                            Broadcast</a>
                                    </li>
                                @endfeature
                                @feature('attendance_biometric')
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('settings.biometric.index') }}">
                                            Biometric Terminals</a>
                                    </li>
                                @endfeature
                            </ul>
                        </li>
                    @endif

                    @feature('announcements')
                        <li class="nxl-item nxl-hasmenu">
                            <a href="#" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-volume-2"></i></span>
                                <span class="nxl-mtext">Announcement</span><span class="nxl-arrow"><i
                                        class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('announcement.index') }}">
                                        My Announcement</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('announcement.all') }}">
                                        All Announcement </a>
                                </li>
                            </ul>
                        </li>
                    @endfeature
                @endif

                {{-- ==================== Everyone else: unchanged prior order ==================== --}}
                @feature('attendance')
                    @if (!in_array($role, ['admin']))
                        <li class="nxl-item">
                            <a href="{{ route('attendance.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-clock"></i></span>
                                <span class="nxl-mtext">Attendance</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endif
                @endfeature
                @feature('leave_management')
                    @if ($role === 'admin')
                        {{-- handled in admin-only track above (no leave link for admin) --}}
                    @elseif ($role === 'hr')
                        <li class="nxl-item nxl-hasmenu">
                            <a href="#" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-calendar"></i></span>
                                <span class="nxl-mtext">Leave</span><span class="nxl-arrow"><i
                                        class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('leave.view') }}">View
                                        Leave</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('leave-credit.index') }}">Leave
                                        Credit</a>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li class="nxl-item">
                            <a href="{{ route('leave.view') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-calendar"></i></span>
                                <span class="nxl-mtext">Leave</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endif
                @endfeature

                @feature('holiday')
                    @if ($role !== 'admin')
                        <li class="nxl-item">
                            <a href="{{ route('holiday.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-sun"></i></span>
                                <span class="nxl-mtext">Holidays</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endif
                @endfeature

                @feature('regularization')
                    @if (!in_array($role, ['admin']))
                        <li class="nxl-item">
                            <a href="{{ route('attendance-regularization.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-edit-3"></i></span>
                                <span class="nxl-mtext">Regularization</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endif
                @endfeature

                @feature('wfh_travel')
                    @if (!in_array($role, ['admin']))
                        <li class="nxl-item">
                            <a href="{{ route('requests.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-send"></i></span>
                                <span class="nxl-mtext">Travel & WFH</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endif
                @endfeature

                @if ((app(\App\Services\FeatureService::class)->enabledForCurrentTenant('task_single') || app(\App\Services\FeatureService::class)->enabledForCurrentTenant('task_group')) && !in_array($role, ['admin']))
                    <li class="nxl-item">
                        <a href="{{ route('task.assigned-to-me') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-check-square"></i></span>
                            <span class="nxl-mtext">Task</span><span class="nxl-arrow"></span>
                        </a>
                    </li>
                @endif

                @feature('project_management')
                    @if ($role !== 'admin')
                        <li class="nxl-item">
                            <a href="{{ route('project.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-folder"></i></span>
                                <span class="nxl-mtext">Projects</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endif
                @endfeature

                @feature('meetings')
                    @if (in_array($role, ['hr', 'employee', 'manager']) && $user->id != 162)
                        <li class="nxl-item">
                            <a href="{{ route('meetings.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-video"></i></span>
                                <span class="nxl-mtext">Meetings</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endif
                @endfeature

                @feature('expense_management')
                    @if (!in_array($role, ['admin']))
                        <li class="nxl-item">
                            <a href="{{ route('expense.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-credit-card"></i></span>
                                <span class="nxl-mtext">Expense</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endif
                @endfeature

                @feature('overtime')
                    @if (!in_array($role, ['admin']))
                        <li class="nxl-item">
                            <a href="{{ route('overtime.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-watch"></i></span>
                                <span class="nxl-mtext">Overtime</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endif
                @endfeature

                @if (in_array($role, ['hr', 'employee', 'manager']))
                    <li class="nxl-item">
                        <a href="{{ route('my-payroll.my-salary-slips') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-file-text"></i></span>
                            <span class="nxl-mtext">Salary Slips</span><span class="nxl-arrow"></span>
                        </a>
                    </li>
                @endif

                @if ($role !== 'admin' && app(\App\Services\FeatureService::class)->enabledForCurrentTenant('kpi_performance'))
                    @if ($role === 'employee')
                        <li class="nxl-item">
                            <a href="{{ route('performance.my-dashboard') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-trending-up"></i></span>
                                <span class="nxl-mtext">Performance</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @else
                        <li class="nxl-item nxl-hasmenu">
                            <a href="#" class="nxl-link">

                                <span class="nxl-micon"><i class="feather-trending-up"></i></span>
                                <span class="nxl-mtext">Performance</span><span class="nxl-arrow"><i
                                        class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">
                                @if (in_array($role, ['manager', 'employee']))
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('performance.my-dashboard') }}">
                                            My Performance</a>
                                    </li>
                                @endif
                                @if (in_array($role, ['manager', 'hr']))
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('performance.team') }}">
                                            Team Performance</a>
                                    </li>
                                @endif
                                @if (!in_array($role, ['employee']))
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('performance.reviews.index') }}">
                                            Team Review</a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif
                @endif

                @feature('asset_management')
                    @if ($role !== 'admin')
                        @if (in_array($role, ['hr', 'manager']))
                            <li class="nxl-item nxl-hasmenu">
                                <a href="#" class="nxl-link">
                                    <span class="nxl-micon"><i class="feather-hard-drive"></i></span>
                                    <span class="nxl-mtext">Assets</span><span class="nxl-arrow"><i
                                            class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('assets.index') }}">
                                            All Assets</a>
                                    </li>
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('my-assets.index') }}">
                                            My Assets</a>
                                    </li>
                                </ul>
                            </li>
                        @else
                            <li class="nxl-item">
                                <a href="{{ route('my-assets.index') }}" class="nxl-link">
                                    <span class="nxl-micon"><i class="feather-hard-drive"></i></span>
                                    <span class="nxl-mtext">My Assets</span><span class="nxl-arrow"></span>
                                </a>
                            </li>
                        @endif
                    @endif
                @endfeature

                @if (in_array($role, ['manager', 'employee']))
                    @feature('offboarding')
                        <li class="nxl-item">
                            <a href="{{ route('offboarding.employee') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-user-minus"></i></span>
                                <span class="nxl-mtext">Off Boarding</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endfeature
                @endif

                @feature('loan_management')
                    @if (in_array($role, ['hr', 'manager', 'employee']))
                        <li class="nxl-item">
                            <a href="{{ route('loan.requests.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-repeat"></i></span>
                                <span class="nxl-mtext">Loans & Advances</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endif
                @endfeature

                {{-- ==================== Other modules ==================== --}}
                @feature('announcements')
                    @if ($role !== 'admin')
                        <li class="nxl-item nxl-hasmenu">
                            <a href="#" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-volume-2"></i></span>
                                <span class="nxl-mtext">Announcement</span><span class="nxl-arrow"><i
                                        class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">

                                <li class="nxl-item"><a class="nxl-link" href="{{ route('announcement.index') }}">
                                        My Announcement</a>
                                </li>

                                <li class="nxl-item"><a class="nxl-link" href="{{ route('announcement.all') }}">
                                        All Announcement </a>
                                </li>

                            </ul>
                        </li>
                    @endif
                @endfeature

                @if (in_array($role, ['hr', 'manager']))
                    <li class="nxl-item">
                        <a href="{{ route('team.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-users"></i></span>
                            <span class="nxl-mtext">Team</span><span class="nxl-arrow"></span>
                        </a>
                    </li>
                @endif

                @feature('daily_reports')
                    @if ($role === 'hr')
                        <li class="nxl-item">
                            <a href="{{ route('report.attendance.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-bar-chart-2"></i></span>
                                <span class="nxl-mtext">Reports</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endif
                @endfeature

                @if (in_array($role, ['hr']))
                    @if (optional($currentTenant)->custom_shifts_enabled && app(\App\Services\FeatureService::class)->enabledForCurrentTenant('custom_shift'))
                        <li class="nxl-item nxl-hasmenu">
                            <a href="#" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-sunrise"></i></span>
                                <span class="nxl-mtext">Shift</span><span class="nxl-arrow"><i
                                        class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('shift.roster') }}">
                                        Shift Roster</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('shift.index') }}">
                                        Manage Shifts</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('shift-settings.index') }}">
                                        Shift Settings</a>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li class="nxl-item">
                            <a href="{{ route('shift-settings.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-sunrise"></i></span>
                                <span class="nxl-mtext">Shift Settings</span><span class="nxl-arrow"></span>
                            </a>
                        </li>
                    @endif
                @endif

                @feature('payroll')
                    @if (in_array($role, ['hr']))
                        <li class="nxl-item nxl-hasmenu">
                            <a href="#" class="nxl-link">

                                <span class="nxl-micon"><i class="feather-dollar-sign"></i></span>
                                <span class="nxl-mtext">Payrolls</span><span class="nxl-arrow"><i
                                        class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">

                                <li class="nxl-item"><a class="nxl-link" href="{{ route('monthly-payrolls.index') }}">
                                        Payroll Monthly</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-components.index') }}">
                                        Payroll Components</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-structures.index') }}">
                                        Payroll Structures</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-employee-structures.index') }}">
                                        Employee Payroll Structures</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-compliance.index') }}">
                                        Statutory Compliance</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-bonuses.index') }}">
                                        Payroll Bonuses</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-arrears.index') }}">
                                        Payroll Arrears</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-engine-settings.index') }}">
                                        Engine Settings</a>
                                </li>
                            </ul>
                        </li>
                    @endif
                @endfeature

                @if (in_array($role, ['hr', 'manager']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-check-circle"></i></span>
                            <span class="nxl-mtext">Approval Requests</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @feature('leave_management')
                                @if (in_array($role, ['manager']))
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('leave.view-all') }}">
                                            Leave</a>
                                    </li>
                                @endif
                            @endfeature
                            @feature('regularization')
                                @if (in_array($role, ['manager']))
                                    <li class="nxl-item"><a class="nxl-link"
                                            href="{{ route('attendance-regularization.manage') }}">
                                            Regularization</a>
                                    </li>
                                @endif
                            @endfeature
                            @feature('wfh_travel')
                                @if (in_array($role, ['manager', 'hr']))
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('manager.requests') }}">
                                            WFH & Travel</a>
                                    </li>
                                @endif
                            @endfeature
                            @feature('expense_management')
                                @if (in_array($role, ['hr']))
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.view-all') }}">
                                            Expense</a>
                                    </li>
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.payments.index') }}">
                                            Expense Payment</a>
                                    </li>
                                    @if (\App\Support\ExpenseFeatures::payrollRouteEnabled((int) auth()->user()->tenant_id))
                                        <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.payroll.index') }}">
                                                Reimbursements via Payroll</a>
                                        </li>
                                    @endif
                                @endif
                            @endfeature
                            @feature('loan_management')
                                @if (in_array($role, ['hr']))
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('loan.approvals.pending') }}">
                                            Loan Requests</a>
                                    </li>
                                @endif
                            @endfeature
                            @feature('overtime')
                                @if (in_array($role, ['hr', 'manager']))
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('overtime.view-all') }}">
                                            Overtime</a>
                                    </li>
                                @endif
                            @endfeature
                            @if ((app(\App\Services\FeatureService::class)->enabledForCurrentTenant('task_single') || app(\App\Services\FeatureService::class)->enabledForCurrentTenant('task_group')) && in_array($role, ['manager', 'hr']))
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('task.assigned-by-me') }}">
                                        Tasks</a>
                                </li>
                            @endif
                            @feature('offboarding')
                                @if (in_array($role, ['hr']))
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('offboarding.index') }}">
                                            Offboarding</a>
                                    </li>
                                @endif
                                @if (in_array($role, ['manager']))
                                    <li class="nxl-item"><a class="nxl-link" href="{{ route('offboarding.manager') }}">
                                            Offboarding</a>
                                    </li>
                                @endif
                            @endfeature
                        </ul>
                    </li>
                @endif

                @if (in_array($role, ['hr']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-briefcase"></i></span>
                            <span class="nxl-mtext">Company</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @feature('branches')
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('branch.index') }}">
                                        Branch</a>
                                </li>
                            @endfeature
                            @feature('leave_management')
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('leave-type.index') }}">
                                        Leave Types</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('leave-credit.index') }}">
                                        Leave Credit</a>
                                </li>
                            @endfeature
                            @feature('expense_management')
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('expense-type.index') }}">
                                        Expense Types</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.budgets.index') }}">
                                        Expense Budgets</a>
                                </li>
                            @endfeature
                            @feature('loan_management')
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('loan.categories.index') }}">
                                        Loan Categories</a>
                                </li>
                            @endfeature
                            @feature('asset_management')
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('asset-categories.index') }}">
                                        Asset Categories</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('asset-types.index') }}">
                                        Asset Types</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('asset-vendors.index') }}">
                                        Asset Vendors</a>
                                </li>
                            @endfeature
                        </ul>
                    </li>
                @endif

                @if (in_array($role, ['hr']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-sliders"></i></span>
                            <span class="nxl-mtext">Basic Setup</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('department.index') }}">
                                    Departments</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('designation.index') }}">
                                    Designation</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('attendance-location.index') }}">
                                    Attendance Locations</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('workforce-settings.index') }}">
                                    Company Policies</a>
                            </li>
                            @feature('broadcast_notifications')
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('broadcast.index') }}">
                                        Broadcast</a>
                                </li>
                            @endfeature
                            @feature('attendance_biometric')
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('settings.biometric.index') }}">
                                        Biometric Terminals</a>
                                </li>
                            @endfeature
                        </ul>
                    </li>
                @endif

                {{-- ==================== Account ==================== --}}
                <li class="nxl-item nxl-hasmenu">
                    <a href="{{ route('logout') }}" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-log-out"></i></span>
                        <span class="nxl-mtext">Log Out</span><span class="nxl-arrow"></span>
                    </a>
                </li>

            </ul>

        </div>
    </div>
</nav>
<!--! ================================================================ !-->
<!--! [End]  Navigation Manu !-->
<!--! ================================================================ !-->
