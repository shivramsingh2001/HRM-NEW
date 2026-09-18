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
                @if (!in_array($role, ['admin']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-clock"></i></span>
                            <span class="nxl-mtext">Attendance</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link"
                                    href="{{ route('attendance.index') }}">Attendance</a>
                            </li>
                        </ul>
                    </li>
                @endif
                <li class="nxl-item nxl-hasmenu">
                    <a href="#" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-calendar"></i></span>
                        <span class="nxl-mtext">Leave</span><span class="nxl-arrow"><i
                                class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        @if (!in_array($role, ['admin']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('leave.view') }}">View
                                    Leave</a>
                            </li>
                        @endif
                        @if (!in_array($role, ['admin']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('leave-credit.my-transactions') }}">
                                    My Leave Report</a>
                            </li>
                        @endif
                        @if (in_array($role, ['admin', 'hr']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('leave-credit.index') }}">Leave
                                    Report</a>
                            </li>
                        @endif
                    </ul>
                </li>

                @if (!in_array($role, ['admin']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-edit-3"></i></span>
                            <span class="nxl-mtext">Regularization</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link"
                                    href="{{ route('attendance-regularization.index') }}">
                                    Regularization</a>
                            </li>
                        </ul>
                    </li>
                @endif

                @if (!in_array($role, ['admin']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-check-square"></i></span>
                            <span class="nxl-mtext">Task</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('task.assigned-to-me') }}">
                                    Assigned To me Task </a>
                            </li>
                        </ul>
                    </li>
                @endif
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

                @if (in_array($role, ['hr', 'employee', 'manager','admin']) && $user->id != 162)
                    <li class="nxl-item">
                        <a href="{{ route('meetings.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-video"></i></span>
                            <span class="nxl-mtext">Meetings</span><span class="nxl-arrow"></span>
                        </a>
                    </li>
                @endif

                {{-- ==================== Tier 2: Frequent self-service ==================== --}}
                @if (!in_array($role, ['admin']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">

                            <span class="nxl-micon"><i class="feather-watch"></i></span>
                            <span class="nxl-mtext">Overtime</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('overtime.index') }}">
                                    Overtime</a>
                            </li>
                        </ul>
                    </li>
                @endif

                @if (!in_array($role, ['admin']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-send"></i></span>
                            <span class="nxl-mtext">Travel & WFH</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item">
                                <a class="nxl-link" href="{{ route('requests.index') }}">
                                    My Requests
                                </a>
                            </li>
                        </ul>
                    </li>
                @endif

                @if (in_array($role, ['hr', 'employee', 'manager']))
                    <li class="nxl-item">
                        <a href="{{ route('my-payroll.my-salary-slips') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-file-text"></i></span>
                            <span class="nxl-mtext">Salary Slips</span><span class="nxl-arrow"></span>
                        </a>
                    </li>
                @endif

                {{-- ==================== Approval Requests (consolidated) ==================== --}}
                @if (in_array($role, ['admin', 'hr', 'manager']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-check-circle"></i></span>
                            <span class="nxl-mtext">Approval Requests</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @if (in_array($role, ['admin', 'manager']))
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('leave.view-all') }}">
                                        Leave</a>
                                </li>
                            @endif
                            @if (in_array($role, ['admin', 'manager']))
                                <li class="nxl-item"><a class="nxl-link"
                                        href="{{ route('attendance-regularization.manage') }}">
                                        Regularization</a>
                                </li>
                            @endif
                            @if (in_array($role, ['admin', 'manager', 'hr']))
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('manager.requests') }}">
                                        WFH & Travel</a>
                                </li>
                            @endif
                            @if (in_array($role, ['admin', 'hr']))
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.view-all') }}">
                                        Expense</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.payments.index') }}">
                                        Expense Payment</a>
                                </li>
                            @endif
                            @if (in_array($role, ['hr', 'admin']))
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('loan.approvals.pending') }}">
                                        Loan Requests</a>
                                </li>
                            @endif
                            @if (in_array($role, ['admin', 'hr', 'manager']))
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('overtime.view-all') }}">
                                        Overtime</a>
                                </li>
                            @endif
                            @if (in_array($role, ['admin', 'manager', 'hr']))
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('task.assigned-by-me') }}">
                                        Tasks</a>
                                </li>
                            @endif
                            @if (in_array($role, ['admin', 'hr']))
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('offboarding.index') }}">
                                        Offboarding</a>
                                </li>
                            @endif
                            @if (in_array($role, ['manager']))
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('offboarding.manager') }}">
                                        Offboarding</a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif

                {{-- ==================== Tier 3: Team & growth ==================== --}}
                @if (in_array($role, ['admin', 'hr', 'manager']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">

                            <span class="nxl-micon"><i class="feather-users"></i></span>
                            <span class="nxl-mtext">Team</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @if (in_array($role, ['admin', 'hr', 'manager']))
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('team.index') }}">
                                        Team</a>
                                </li>
                            @endif
                            <!--@if (in_array($role, ['admin', 'hr']))-->
                            <!--    <li class="nxl-item"><a class="nxl-link"-->
                            <!--            href="{{ route('team.attendance-summary') }}">-->
                            <!--            Summary</a>-->
                            <!--    </li>-->
                            <!--@endif-->
                            <!-- @if (in_array($role, ['admin', 'hr']))-->
                            <!--    <li class="nxl-item"><a class="nxl-link"-->
                            <!--            href="{{ route('report.attendance.index') }}">-->
                            <!--            Attendance Report</a>-->
                            <!--    </li>-->
                            <!--@endif-->

                        </ul>
                    </li>
                @endif

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
                        @if (in_array($role, ['manager','admin','hr']))
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

                {{-- ==================== Tier 4: Financial requests ==================== --}}
                @if (!in_array($role, ['admin']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-credit-card"></i></span>
                            <span class="nxl-mtext">Expense</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.index') }}">
                                    Expense</a>
                            </li>
                        </ul>
                    </li>
                @endif

                @if (in_array($role, ['hr','manager','employee']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-repeat"></i></span>
                            <span class="nxl-mtext">Loan</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item">
                                <a class="nxl-link" href="{{ route('loan.requests.index') }}">
                                   My Request
                                </a>
                            </li>
                        </ul>
                    </li>
                @endif

                {{-- ==================== Tier 5: Payroll administration ==================== --}}
                @feature('payroll')
                @if (in_array($role, ['admin', 'hr']))
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

                {{-- ==================== Tier 6: HR operations ==================== --}}
                @if (in_array($role, ['admin']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-user-plus"></i></span>
                            <span class="nxl-mtext">Employee</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('employee.index') }}">View
                                    Employee</a>
                            </li>
                        </ul>
                    </li>
                @endif

                @if (in_array($role, ['manager', 'employee']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-user-minus"></i></span>
                            <span class="nxl-mtext">Off Boarding</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('offboarding.employee') }}">
                                    My Request</a>
                            </li>
                        </ul>
                    </li>
                @endif

                @if (in_array($role, ['admin', 'hr']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-sunrise"></i></span>
                            <span class="nxl-mtext">Shift</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @if (optional($currentTenant)->custom_shifts_enabled)
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('shift.roster') }}">
                                        Shift Roster</a>
                                </li>
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('shift.index') }}">
                                        Manage Shifts</a>
                                </li>
                            @endif
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('shift-settings.index') }}">
                                    Shift Settings</a>
                            </li>
                        </ul>
                    </li>
                @endif

                {{-- ==================== Tier 7: Reference & one-time setup ==================== --}}
                <li class="nxl-item">
                    <a href="{{ route('holiday.index') }}" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-sun"></i></span>
                        <span class="nxl-mtext">Holidays</span><span class="nxl-arrow"></span>
                    </a>
                </li>

                @if (in_array($role, ['admin', 'hr']))
                    <li class="nxl-item">
                        <a href="{{ route('report.attendance.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-bar-chart-2"></i></span>
                            <span class="nxl-mtext">Reports</span><span class="nxl-arrow"></span>
                        </a>
                    </li>
                @endif

                <li class="nxl-item">
                    <a href="{{ route('project.index') }}" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-folder"></i></span>
                        <span class="nxl-mtext">Projects</span><span class="nxl-arrow"></span>
                    </a>
                </li>

                @if (in_array($role, ['admin', 'hr']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-briefcase"></i></span>
                            <span class="nxl-mtext">Company</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('branch.index') }}">
                                    Branch</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('leave-type.index') }}">
                                    Leave Types</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('expense-type.index') }}">
                                    Expense Types</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('loan.categories.index') }}">
                                    Loan Categories</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('overtime.settings') }}">
                                    Overtime Settings</a>
                            </li>
                        </ul>
                    </li>
                @endif

                @if (in_array($role, ['admin']))
                    <li class="nxl-item">
                        <a href="{{ route('job-openings.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-user-check"></i></span>
                            <span class="nxl-mtext">Hiring</span><span class="nxl-arrow"></span>
                        </a>
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
                            @if (optional($currentTenant)->field_tracking_enabled)
                                <li class="nxl-item"><a class="nxl-link" href="{{ route('settings.field-tracking.index') }}">
                                        Field Tracking</a>
                                </li>
                            @endif
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('settings.biometric.index') }}">
                                    Biometric Terminals</a>
                            </li>
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
