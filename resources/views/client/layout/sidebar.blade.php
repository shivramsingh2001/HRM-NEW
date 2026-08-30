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
                <li class="nxl-item nxl-hasmenu">
                    <a href="{{ route('dashboard') }}" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-airplay"></i></span>
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
                        @if (in_array($role, ['admin', 'hr']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('leave-type.index') }}">
                                    Leave Type</a>
                            </li>
                        @endif
                        @if (!in_array($role, ['admin']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('leave.view') }}">View
                                    Leave</a>
                            </li>
                        @endif
                        @if (in_array($role, ['admin', 'manager']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('leave.view-all') }}">Team
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

                <li class="nxl-item nxl-hasmenu">
                    <a href="#" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-check-square"></i></span>
                        <span class="nxl-mtext">Task</span><span class="nxl-arrow"><i
                                class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">

                        <li class="nxl-item"><a class="nxl-link" href="{{ route('task.create') }}">
                                Create Task</a>
                        </li>
                        @if (in_array($role, ['admin', 'manager', 'hr']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('task.assigned-by-me') }}">
                                    Assigned By me Task</a>
                            </li>
                        @endif
                        @if (!in_array($role, ['admin']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('task.assigned-to-me') }}">
                                    Assigned To me Task </a>
                            </li>
                        @endif
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="#" class="nxl-link">
                        <span class="nxl-micon"><i class="fa-solid fa-bullhorn"></i></span>
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
                @if (in_array($role, ['admin', 'hr']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">

                            <span class="nxl-micon"><i class="fas fa-rupee-sign"></i></span>
                            <span class="nxl-mtext">Payrolls</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">

                            <li class="nxl-item"><a class="nxl-link" href="{{ route('payroll-masters.index') }}">
                                    Payroll Master</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('employee-payrolls.index') }}">
                                    Payroll Employee</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('monthly-payrolls.index') }}">
                                    Payroll Monthly</a>
                            </li>
                        </ul>
                    </li>
                @endif
                
                <li class="nxl-item nxl-hasmenu">
                    <a href="#" class="nxl-link">

                        <span class="nxl-micon"><i class="fas fa-chart-line"></i></span>
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
                
                <li class="nxl-item nxl-hasmenu">
                    <a href="{{ route('holiday.index') }}" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-calendar"></i></span>
                        <span class="nxl-mtext">Holidays</span><span class="nxl-arrow"></span>
                    </a>

                </li>
                @if (in_array($role, ['admin', 'hr']))
                 <li class="nxl-item nxl-hasmenu">
                    <a href="{{ route('report.attendance.index') }}" class="nxl-link">
                        <span class="nxl-micon"><i class="fas fa-file-alt"></i></span>
                        <span class="nxl-mtext">Reports</span><span class="nxl-arrow"></span>
                    </a>
                </li>
                @endif
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
                        <span class="nxl-micon"><i class="fas fa-plane-departure"></i></span>
                        <span class="nxl-mtext">Travel & WFH</span>
                        <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        @if (!in_array($role, ['admin']))
                            <li class="nxl-item">
                                <a class="nxl-link" href="{{ route('requests.index') }}">
                                    My Requests
                                </a>
                            </li>
                        @endif
                        @if (in_array($role, ['admin', 'manager', 'hr']))
                            <li class="nxl-item">
                                <a class="nxl-link" href="{{ route('manager.requests') }}">
                                    Team Requests
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
                 <li class="nxl-item nxl-hasmenu">
                    <a href="#" class="nxl-link">
                        <span class="nxl-micon"><i class="fas fa-hand-holding-usd"></i></span>
                        <span class="nxl-mtext">Loan</span>
                        <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        @if (in_array($role, ['admin','hr']))
                            <li class="nxl-item">
                                <a class="nxl-link" href="{{ route('loan.categories.index') }}">
                                    Loan Category
                                </a>
                            </li>
                        @endif
                        @if (in_array($role, ['hr','manager','employee']))
                            <li class="nxl-item">
                                <a class="nxl-link" href="{{ route('loan.requests.index') }}">
                                   My Request
                                </a>
                            </li>
                        @endif
                        @if (in_array($role, ['hr','admin']))
                            <li class="nxl-item">
                                <a class="nxl-link" href="{{ route('loan.approvals.pending') }}">
                                   All Request
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
                 @if (in_array($role, ['admin']))
                 <li class="nxl-item nxl-hasmenu">
                    <a href="{{ route('job-openings.index') }}" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-calendar"></i></span>
                        <span class="nxl-mtext">Hiring</span><span class="nxl-arrow"></span>
                    </a>

                </li>
                 @endif
                 <li class="nxl-item nxl-hasmenu">
                    <a href="#" class="nxl-link">

                        <span class="nxl-micon"><i class="feather-users"></i></span>
                        <span class="nxl-mtext">Off Boarding</span><span class="nxl-arrow"><i
                                class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        @if (in_array($role, ['admin', 'hr']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('offboarding.index') }}">
                                    All Off boarding</a>
                            </li>
                        @endif
                        @if (in_array($role, ['manager']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('offboarding.manager') }}">
                                    Team Off Boarding</a>
                            </li>
                        @endif
                        @if (in_array($role, ['manager', 'employee']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('offboarding.employee') }}">
                                    My Request</a>
                            </li>
                        @endif
                    </ul>
                </li>
                
                @if (in_array($role, ['admin', 'hr']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="{{ route('shift.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-clock"></i></span>
                            <span class="nxl-mtext">Shift</span><span class="nxl-arrow"></span>
                        </a>
                    </li>
                @endif
                <li class="nxl-item nxl-hasmenu">
                    <a href="{{ route('project.index') }}" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-briefcase"></i></span>
                        <span class="nxl-mtext">Projects</span><span class="nxl-arrow"></span>
                    </a>

                </li>
                @if (in_array($role, ['admin', 'hr']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="{{ route('department.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-grid"></i></span>
                            <span class="nxl-mtext">Departments</span><span class="nxl-arrow"></span>
                        </a>

                    </li>
                @endif


                @if (in_array($role, ['admin', 'hr']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="{{ route('designation.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-tag"></i></span>
                            <span class="nxl-mtext">Designation</span><span class="nxl-arrow"></span>
                        </a>

                    </li>
                @endif


                @if (in_array($role, ['admin', 'hr']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="{{ route('branch.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-home"></i></span>
                            <span class="nxl-mtext">Branch</span><span class="nxl-arrow"></span>
                        </a>

                    </li>
                @endif

                <li class="nxl-item nxl-hasmenu">
                    <a href="#" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-credit-card"></i></span>
                        <span class="nxl-mtext">Expense</span><span class="nxl-arrow"><i
                                class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        @if (in_array($role, ['admin', 'hr']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('expense-type.index') }}">
                                    Type</a>
                            </li>
                        @endif
                        @if (!in_array($role, ['admin']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.index') }}">
                                    Expense</a>
                            </li>
                        @endif
                        @if (in_array($role, ['admin', 'hr']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.view-all') }}">
                                    Team Expense</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('expense.payments.index') }}">
                                    Payment</a>
                            </li>
                        @endif
                    </ul>
                </li>

                <li class="nxl-item nxl-hasmenu">
                    <a href="#" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-edit-3"></i></span>
                        <span class="nxl-mtext">Regularization</span><span class="nxl-arrow"><i
                                class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        @if (!in_array($role, ['admin']))
                            <li class="nxl-item"><a class="nxl-link"
                                    href="{{ route('attendance-regularization.index') }}">
                                    Regularization</a>
                            </li>
                        @endif

                        @if (in_array($role, ['admin', 'manager']))
                            <li class="nxl-item"><a class="nxl-link"
                                    href="{{ route('attendance-regularization.manage') }}">
                                    Team Regularization</a>
                            </li>
                        @endif
                    </ul>
                </li>
               
                <li class="nxl-item nxl-hasmenu">
                    <a href="#" class="nxl-link">

                        <span class="nxl-micon"><i class="fa-regular fa-clock"></i></span>
                        <span class="nxl-mtext">Overtime</span><span class="nxl-arrow"><i
                                class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        @if (in_array($role, ['admin', 'hr']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('overtime.settings') }}">
                                    Overtime Settings</a>
                            </li>
                        @endif
                        @if (!in_array($role, ['admin']))
                        <li class="nxl-item"><a class="nxl-link" href="{{ route('overtime.index') }}">
                                Overtime</a>
                        </li>
                        @endif
                         @if (in_array($role, ['admin', 'hr' ,'manager']))
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('overtime.view-all') }}">
                                    Team Overtime</a>
                            </li>
                        @endif
            
                    </ul>
                </li>
               
                @if (in_array($role, ['hr', 'employee', 'manager','admin']) && $user->id != 162)
                    <a href="{{ route('meetings.index') }}" class="nxl-link">
                        <span class="nxl-micon"><i class="fa-brands fa-meetup"></i></span>
                        <span class="nxl-mtext">Meetings</span><span class="nxl-arrow"></span>
                    </a>
                @endif

                @if (in_array($role, ['admin']))
                    <li class="nxl-item nxl-hasmenu">
                        <a href="#" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-user-plus"></i></span>
                            <span class="nxl-mtext">Employee</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('employee.create') }}">Add
                                    Employee</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('employee.index') }}">View
                                    Employee</a>
                            </li>
                        </ul>
                    </li>
                @endif
                @if (in_array($role, ['hr', 'employee', 'manager']))
                    <a href="{{ route('my-payroll.my-salary-slips') }}" class="nxl-link">
                        <span class="nxl-micon"><i class="fas fa-rupee-sign"></i></span>
                        <span class="nxl-mtext">Salary Slips</span><span class="nxl-arrow"></span>
                    </a>
                @endif


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
