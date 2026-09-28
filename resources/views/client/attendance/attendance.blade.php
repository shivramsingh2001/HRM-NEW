@extends('client.layout.master')

@section('style')
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <style>
         .fc-event .task-count-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 2px 6px;
            font-size: 9px;
            margin-left: 6px;
            font-weight: 600;
        }
        .fc .fc-toolbar.fc-header-toolbar {
            margin-bottom: 0px !important;
        }

        .fc .fc-col-header-cell {
            background: #f8fafd;
            border-bottom: 2px solid #eef2f7;
            padding: 8px 0;
        }

        .fc .fc-daygrid-day-frame {

            padding: 0px 5px;
        }

        /* Enhanced Calendar Styling */
        #calendar {
            max-width: 100%;
            margin: 0 auto;
            /*height: 680px;*/
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid #eef2f7;
        }

        /* Calendar Header Enhancements */
        .fc .fc-toolbar {
            padding: 24px 24px 16px;
            margin-bottom: 0;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: white;
            border-radius: 10px 12px 0 0;
        }

        .fc .fc-toolbar-title {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: white;
            text-transform: capitalize;
        }

        /* Enhanced Calendar Buttons */
        .fc .fc-button {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            text-transform: capitalize;
        }

        .fc .fc-button:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .fc .fc-button:active,
        .fc .fc-button:focus {
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
        }

        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background: white;
            border-color: white;
            color: #1e3a8a;
        }

        /* Calendar Grid Enhancements */

        .fc .fc-col-header-cell-cushion {
            color: #4a5568;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Calendar Days Enhancements */
        .fc .fc-daygrid-day {
            border: 1px solid #dee2e6;
            transition: all 0.2s ease;
        }

        .fc .fc-daygrid-day:hover {
            background: #f9fafb;
        }

        .fc .fc-daygrid-day.fc-day-today {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
            position: relative;
        }

        .fc .fc-daygrid-day.fc-day-today::before {
            content: '';
            position: absolute;
            top: 4px;
            right: 4px;
            width: 6px;
            height: 6px;
            background: #1e3a8a;
            border-radius: 50%;
        }

        .fc .fc-daygrid-day-number {
            color: #2d3748;
            font-weight: 600;
            padding: 10px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
            color: #1e3a8a;
            font-weight: 700;
            font-size: 1rem;
        }

        /* Enhanced Calendar Events */
        .fc-event {
            border: none;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 500;
            padding: 6px 8px;
            margin: 2px 0;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        /*.fc-event::before {*/
        /*    content: '';*/
        /*    position: absolute;*/
        /*    left: 0;*/
        /*    top: 0;*/
        /*    height: 100%;*/
        /*    width: 4px;*/
        /*    background: rgba(255, 255, 255, 0.5);*/
        /*}*/

        .fc-event:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        /* Enhanced Status Colors — single-blue theme: every status is a
           shade of the app's primary blue instead of green/red/amber,
           matching the hex values returned by getEventColor(). */
        .fc-event[style*="#172554"] {
            background: linear-gradient(135deg, #172554 0%, #0f172a 100%) !important;
            border: none !important;
        }

        .fc-event[style*="#1e3a8a"] {
            background: linear-gradient(135deg, #1e3a8a 0%, #172554 100%) !important;
            border: none !important;
        }

        .fc-event[style*="#1e40af"] {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%) !important;
            border: none !important;
        }

        .fc-event[style*="#1d4ed8"] {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%) !important;
            border: none !important;
        }

        .fc-event[style*="#2563eb"] {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
            border: none !important;
        }

        .fc-event[style*="#3b82f6"] {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            border: none !important;
        }

        .fc-event[style*="#60a5fa"] {
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%) !important;
            border: none !important;
        }

        /* Enhanced Stats Badges */
        .stats-badge {
            background: white;
            border-radius: 12px;
            padding: 12px 20px;
            border: 1px solid #eef2f7;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .stats-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            border-color: #1e3a8a;
        }

        .stats-badge .badge-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .stats-badge.present .badge-icon {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.15) 0%, rgba(30, 58, 138, 0.05) 100%);
            color: #1e3a8a;
        }

        .stats-badge.absent .badge-icon {
            background: linear-gradient(135deg, rgba(23, 37, 84, 0.15) 0%, rgba(23, 37, 84, 0.05) 100%);
            color: #172554;
        }

        .stats-badge.leave .badge-icon {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.15) 0%, rgba(37, 99, 235, 0.05) 100%);
            color: #2563eb;
        }

        .stats-badge.holiday .badge-icon {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(59, 130, 246, 0.05) 100%);
            color: #3b82f6;
        }

        .stats-badge .badge-content {
            display: flex;
            flex-direction: column;
        }

        .stats-badge .badge-count {
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1;
            color: #1f2937;
        }

        .stats-badge .badge-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 600;
        }

        /* Enhanced Date Range Display */
        .date-range-display {
            /*background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);*/
            border-radius: 12px;
            padding: 14px 24px;
            color: black;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
            border: none;
        }

        .date-range-display i {
            font-size: 1.2rem;
        }

        /* Enhanced Navigation Buttons */
        /*.nav-button {*/
        /*    background: white;*/
        /*    border: 2px solid #eef2f7;*/
        /*    color: #4a5568;*/
        /*    font-weight: 600;*/
        /*    padding: 10px 20px;*/
        /*    border-radius: 10px;*/
        /*    transition: all 0.3s ease;*/
        /*    display: inline-flex;*/
        /*    align-items: center;*/
        /*    gap: 8px;*/
        /*    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);*/
        /*}*/

        /*.nav-button:hover {*/
        /*    background: #667eea;*/
        /*    border-color: #667eea;*/
        /*    color: white;*/
        /*    transform: translateY(-2px);*/
        /*    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);*/
        /*}*/

        /*.nav-button.active {*/
        /*background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);*/
        /*    border-color: transparent;*/
        /*    color: black;*/
        /*    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);*/
        /*}*/

        /* Enhanced Dropdown */
        .custom-dropdown .dropdown-toggle {
            background: white;
            border: 1px solid #eef2f7;
            color: #4a5568;
            font-weight: 400;
            padding: 6px 10px;
            border-radius: 6px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .custom-dropdown .dropdown-toggle:hover {
            background: #f9fafb;
            border-color: #1e3a8a;
        }

        .custom-dropdown .dropdown-menu {
            border: 2px solid #eef2f7;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            margin-top: 8px;
        }

        .custom-dropdown .dropdown-item:hover {
            background: #1e3a8a;
            color: white;
        }

        /* Enhanced Modal */
        .attendance-details-modal .modal-content {
            border-radius: 16px;
            overflow: hidden;
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .attendance-details-modal .modal-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: white;
            padding: 24px;
            border-bottom: none;
        }

        .attendance-details-modal .modal-title {
            font-weight: 700;
            font-size: 1.4rem;
        }

        .attendance-details-modal .modal-body {
            padding: 30px;
            background: #f9fafb;
        }

        .detail-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #eef2f7;
            transition: all 0.3s ease;
            height: 100%;
        }

        .detail-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .detail-card .card-title {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6b7280;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #eef2f7;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #6b7280;
            font-weight: 600;
            font-size: 0.7rem;
        }

        .detail-value {
            color: #1f2937;
            font-weight: 700;
            font-size: 0.7rem;
        }

        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Loading Animation */
        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }

            100% {
                background-position: 1000px 0;
            }
        }

        .calendar-loading {
            position: relative;
        }

        .calendar-loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg,
                    rgba(255, 255, 255, 0) 0%,
                    rgba(255, 255, 255, 0.6) 50%,
                    rgba(255, 255, 255, 0) 100%);
            background-size: 1000px 100%;
            animation: shimmer 2s infinite;
        }

        /* Header Enhancements */
        /*.content-area-header {*/
        /*    background: white;*/
        /*    border-radius: 16px;*/
        /*    padding: 20px 24px;*/
        /*    margin-bottom: 24px;*/
        /*    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);*/
        /*    border: 1px solid #eef2f7;*/
        /*}*/

        /* Smooth Scrollbar */
        .content-area-body {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
        }

        .content-area-body::-webkit-scrollbar {
            width: 6px;
        }

        .content-area-body::-webkit-scrollbar-track {
            background: #f7fafc;
            border-radius: 10px;
        }

        .content-area-body::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 10px;
        }

        .content-area-body::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            #calendar {
                height: 500px;
            }

            .stats-badge {
                padding: 10px 16px;
            }

            .stats-badge .badge-count {
                font-size: 1.2rem;
            }

            .nav-button,
            .custom-dropdown .dropdown-toggle {
                padding: 8px 16px;
                font-size: 0.85rem;
            }

            .date-range-display {
                padding: 10px 16px;
                font-size: 0.85rem;
            }
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Attendance" />

    <div class="main-content d-flex" style="padding: 20px !important;">

        <!-- Calendar Card -->
        <div class="card border-0 shadow-lg" style="border-radius: 10px;">
            <div class="card-body p-4">
                <!-- Calendar Header -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="previousMonth()">
                            <i class="feather-chevron-left"></i> Prev
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="nextMonth()">
                            Next <i class="feather-chevron-right"></i>
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="goToToday()">
                            <i class="feather-clock me-1"></i> Today
                        </button>
                    </div>
                    <h3 class="m-0 fw-bold" id="currentMonthName"
                        style="color: #1e3a8a; text-transform: uppercase; letter-spacing: 1px;"></h3>

                    <div class="d-flex align-items-center gap-3">
                        <!--<div id="calendarDateRange" class="date-range-display d-none d-lg-flex">-->
                        <!--    <i class="feather-calendar"></i>-->
                        <!--    <span>Loading...</span>-->
                        <!--</div>-->

                        <div class="custom-dropdown">
                            <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="feather-grid me-2"></i>
                                <span id="currentView">Month View</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="changeView('dayGridMonth')">
                                        <i class="feather-calendar me-2"></i> Month View
                                    </a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="changeView('timeGridWeek')">
                                        <i class="feather-calendar me-2"></i> Week View
                                    </a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="changeView('timeGridDay')">
                                        <i class="feather-calendar me-2"></i> Day View
                                    </a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="changeView('listMonth')">
                                        <i class="feather-list me-2"></i> List View
                                    </a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Calendar Container -->
                <div id="calendar"></div>

                <!-- Calendar Legend (single-blue theme — darker = needs attention, lighter = less urgent) -->
                <div class="d-flex justify-content-center gap-4 mt-4 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <div
                            style="width: 16px; height: 16px; border-radius: 4px; background: linear-gradient(135deg, #172554 0%, #0f172a 100%);">
                        </div>
                        <span class="text-muted small fw-500">Absent</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div
                            style="width: 16px; height: 16px; border-radius: 4px; background: linear-gradient(135deg, #1e3a8a 0%, #172554 100%);">
                        </div>
                        <span class="text-muted small fw-500">Present</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div
                            style="width: 16px; height: 16px; border-radius: 4px; background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);">
                        </div>
                        <span class="text-muted small fw-500">Checked In Only</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div
                            style="width: 16px; height: 16px; border-radius: 4px; background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);">
                        </div>
                        <span class="text-muted small fw-500">Holiday</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div
                            style="width: 16px; height: 16px; border-radius: 4px; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);">
                        </div>
                        <span class="text-muted small fw-500">Leave</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div
                            style="width: 16px; height: 16px; border-radius: 4px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                        </div>
                        <span class="text-muted small fw-500">Week Off</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div
                            style="width: 16px; height: 16px; border-radius: 4px; background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);">
                        </div>
                        <span class="text-muted small fw-500">Upcoming</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Enhanced Attendance Details Modal -->
    <!-- Attendance Details Modal -->
    <div class="modal fade" id="attendanceDetailsModal" tabindex="-1" aria-labelledby="attendanceDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attendanceDetailsModalLabel">Attendance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body attendance-details-modal">
                    <div id="attendanceDetailsContent">
                        <!-- Content will be loaded here -->
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/bootstrap5@5.11.3/index.global.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Prepare calendar events from PHP data
            var calendarEvents = [];

            @if (isset($calendarData) && !empty($calendarData))
                @foreach ($calendarData as $event)
                    calendarEvents.push({
                        id: '{{ $event['id'] }}',
                        title: '{{ addslashes($event['title']) }}',
                        start: '{{ $event['start'] }}',
                        end: '{{ $event['end'] }}',
                        backgroundColor: '{{ $event['bgColor'] }}',
                        borderColor: '{{ $event['bgColor'] }}',
                        textColor: '#ffffff',
                        allDay: true,
                        extendedProps: {
                            day_status: '{{ $event['day_status'] }}',
                            clock_in: '{{ $event['clock_in'] }}',
                            clock_out: '{{ $event['clock_out'] }}',
                            total_hours: '{{ $event['total_hours'] }}',
                            leave_type: '{{ $event['leave_type'] ?? '' }}',
                            holiday_name: '{{ $event['holiday_name'] ?? '' }}',
                            leave_reason: '{{ $event['leave_reason'] ?? '' }}',
                            date: '{{ $event['start'] }}',
                            task_count: {{ $event['task_count'] ?? 0 }},
                            encrypted_user_id: '{{ $event['encrypted_user_id'] ?? '' }}'
                        }
                    });
                @endforeach
            @endif

            // Initialize calendar
            var calendarEl = document.getElementById('calendar');
            window.calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: '',
                    center: '',
                    right: ''
                },
                themeSystem: 'bootstrap5',
                events: calendarEvents,
                eventClick: function(info) {
                    // Redirect to attendance sessions page
                    const formattedDate = info.event.startStr.split('T')[0];
                    const encryptedUserId = info.event.extendedProps.encrypted_user_id || '{{ encrypt(Auth::id()) }}';
                    const sessionsUrl = "{{ route('attendance.sessions') }}";
                    window.location.href = sessionsUrl + "?date=" + encodeURIComponent(formattedDate) + 
                                          "&user_id=" + encodeURIComponent(encryptedUserId);
                },
                eventContent: function(arg) {
                    let taskCount = arg.event.extendedProps.task_count || 0;
                    let taskBadge = '';
                    
                    if (taskCount > 0) {
                        taskBadge = `<span class="task-count-badge">Task ${taskCount}</span>`;
                    }
                    
                    // Clean title (remove any existing task count to avoid duplication)
                    let cleanTitle = arg.event.title.replace(/Task \d+/, '').trim();
                    
                    return {
                        html: `<div class="fc-event-main" style="display: flex; justify-content: space-between; align-items: center; padding: 2px 4px;">
                                    <span style="font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${cleanTitle}</span>
                                    ${taskBadge}
                               </div>`
                    };
                },
                datesSet: function(info) {
                    updateCalendarDateRange(info.start, info.end);
                    document.getElementById('currentMonthName').textContent =
                        window.calendar.getDate().toLocaleString('default', {
                            month: 'long',
                            year: 'numeric'
                        });
                },
                eventDisplay: 'block',
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    meridiem: true
                },
                dayMaxEvents: 3,
                height: 580,
                contentHeight: 'auto',
                dayCellContent: function(arg) {
                    return {
                        html: '<div class="fc-daygrid-day-number">' + arg.dayNumberText + '</div>'
                    };
                },
                eventDidMount: function(info) {
                    // Add hover effect
                    info.el.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-2px)';
                        this.style.boxShadow = '0 6px 16px rgba(0, 0, 0, 0.15)';
                    });

                    info.el.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = '0 2px 8px rgba(0, 0, 0, 0.1)';
                    });
                }
            });

            calendar.render();

            // Set initial date range
            updateCalendarDateRange(
                new Date('{{ $startDate }}'),
                new Date('{{ $endDate }}')
            );

            // Add smooth loading on date change
            calendar.on('datesSet', function(info) {
                $('#calendar').addClass('calendar-loading');
                setTimeout(function() {
                    $('#calendar').removeClass('calendar-loading');
                }, 500);
            });
        });

        // Enhanced update date range display
        function updateCalendarDateRange(start, end) {
            var options = {
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            };
            var startStr = start.toLocaleDateString('en-US', options);
            var endStr = end.toLocaleDateString('en-US', options);

            var dateRangeElement = document.getElementById('calendarDateRange');
            if (dateRangeElement) {
                dateRangeElement.innerHTML = `<i class="feather-calendar"></i><span>${startStr} - ${endStr}</span>`;
            }
        }

        // Enhanced navigation functions
        function previousMonth() {
            window.calendar.prev();
            animateNavButton('.nav-button:first-child');
        }

        function nextMonth() {
            window.calendar.next();
            animateNavButton('.nav-button:last-child');
        }

        function goToToday() {
            window.calendar.today();
            animateNavButton('.nav-button.active');
        }

        function changeView(viewType) {
            window.calendar.changeView(viewType);
            var viewNames = {
                'dayGridMonth': 'Month View',
                'timeGridWeek': 'Week View',
                'timeGridDay': 'Day View',
                'listMonth': 'List View'
            };
            var currentViewElement = document.getElementById('currentView');
            if (currentViewElement) {
                currentViewElement.textContent = viewNames[viewType] || viewType;
            }
        }

        // Button animation
        function animateNavButton(selector) {
            var button = document.querySelector(selector);
            if (button) {
                button.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    button.style.transform = 'scale(1)';
                }, 150);
            }
        }

        // Enhanced show attendance details (now redirects to sessions page)
        function showAttendanceDetails(event) {
            // Redirect to sessions page instead of showing modal
            const formattedDate = event.startStr.split('T')[0];
            const encryptedUserId = event.extendedProps.encrypted_user_id || '{{ encrypt(Auth::id()) }}';
            const sessionsUrl = "{{ route('attendance.sessions') }}";
            window.location.href = sessionsUrl + "?date=" + encodeURIComponent(formattedDate) + 
                                  "&user_id=" + encodeURIComponent(encryptedUserId);
        }

        // Helper functions (kept for compatibility)
        function getStatusClass(status) {
            var classes = {
                'Present': 'success',
                'Checked In Only': 'warning',
                'Holiday': 'primary',
                'First Half Leave': 'warning',
                'Second Half Leave': 'warning',
                'Full Day Leave': 'warning',
                'Absent': 'danger',
                'Weekend': 'secondary'
            };
            return classes[status] || 'secondary';
        }

        function getStatusColor(status) {
            var colors = {
                'Present': '#10b981',
                'Checked In Only': '#f59e0b',
                'Holiday': '#3b82f6',
                'First Half Leave': '#f59e0b',
                'Second Half Leave': '#f59e0b',
                'Full Day Leave': '#f59e0b',
                'Absent': '#ef4444',
                'Weekend': '#6b7280'
            };
            return colors[status] || '#6b7280';
        }

        function getStatusIcon(status) {
            var icons = {
                'Present': 'check-circle',
                'Checked In Only': 'clock',
                'Holiday': 'home',
                'First Half Leave': 'calendar',
                'Second Half Leave': 'calendar',
                'Full Day Leave': 'calendar',
                'Absent': 'x-circle',
                'Weekend': 'sun'
            };
            return icons[status] || 'info';
        }

        // Enhanced AJAX data loading
        function loadAttendanceData(startDate, endDate) {
            $('#calendar').addClass('calendar-loading');

            $.ajax({
                url: "{{ route('attendance.calendar.data') }}",
                type: "GET",
                data: {
                    start_date: startDate.toISOString().split('T')[0],
                    end_date: endDate.toISOString().split('T')[0]
                },
                success: function(response) {
                    if (response.status) {
                        window.calendar.removeAllEvents();

                        var events = response.events.map(function(event) {
                            return {
                                id: event.id,
                                title: event.title,
                                start: event.start,
                                end: event.end,
                                backgroundColor: event.bgColor,
                                borderColor: event.bgColor,
                                textColor: '#ffffff',
                                allDay: true,
                                extendedProps: {
                                    day_status: event.day_status,
                                    clock_in: event.clock_in,
                                    clock_out: event.clock_out,
                                    total_hours: event.total_hours,
                                    leave_type: event.leave_type,
                                    holiday_name: event.holiday_name,
                                    task_count: event.task_count || 0,
                                    encrypted_user_id: event.encrypted_user_id || '{{ encrypt(Auth::id()) }}'
                                }
                            };
                        });

                        window.calendar.addEventSource(events);

                        if (typeof toastr !== 'undefined') {
                            toastr.success('Calendar updated successfully!', '', {
                                timeOut: 2000,
                                positionClass: 'toast-top-right',
                                progressBar: true
                            });
                        }
                    }
                },
                error: function(xhr) {
                    console.error('Error loading attendance data:', xhr.responseText);
                    if (typeof toastr !== 'undefined') {
                        toastr.error('Failed to load attendance data. Please try again.', '', {
                            timeOut: 3000
                        });
                    }
                },
                complete: function() {
                    setTimeout(() => {
                        $('#calendar').removeClass('calendar-loading');
                    }, 500);
                }
            });
        }

        // Auto-refresh when calendar view changes with debounce
        var loadTimeout;
        if (window.calendar) {
            window.calendar.on('datesSet', function(info) {
                clearTimeout(loadTimeout);
                loadTimeout = setTimeout(function() {
                    loadAttendanceData(info.start, info.end);
                }, 300);
            });
        }
    </script>

    <!-- Toastr for notifications -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <style>
        .toast {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            font-weight: 500;
        }

        .toast-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .toast-error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .toast-info {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
    </style>
@endsection
