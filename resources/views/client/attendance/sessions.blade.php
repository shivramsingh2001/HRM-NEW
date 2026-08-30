@extends('client.layout.master')

@section('style')
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <style>
        .sessions-container {
            padding: 0;
        }

        .sessions-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px 30px;
            margin-bottom: 30px;
            color: white;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #667eea;
            transition: transform 0.2s ease;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .info-card h6 {
            margin-bottom: 15px;
            color: #4a5568;
            font-weight: 600;
        }

        .track-list {
            /* max-height: 500px; */
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .track-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.2s ease;
        }

        .track-item:hover {
            background: #f8fafd;
        }

        .track-time {
            min-width: 100px;
            font-weight: 600;
            color: #2d3748;
        }

        .track-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e2e8f0;
            color: #667eea;
        }

        .track-details {
            flex: 1;
        }

        .track-address {
            color: #2d3748;
            margin-bottom: 5px;
        }

        .track-coords {
            font-size: 0.7rem;
            color: #9ca3af;
            font-family: monospace;
        }

        .battery-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: #e2e8f0;
            border-radius: 12px;
            font-size: 0.7rem;
        }

        .location-map {
            height: 400px;
            border-radius: 8px;
            margin-top: 15px;
            background: #f1f5f9;
        }

        .task-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .task-header {
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fefce8;
            border-left: 4px solid #f59e0b;
        }

        .task-header:hover {
            background: #fef9c3;
        }

        .task-body {
            padding: 20px;
            display: none;
            border-top: 1px solid #e2e8f0;
        }

        .task-body.show {
            display: block;
        }

        .priority-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .priority-critical {
            background: #dc2626;
            color: white;
        }

        .priority-high {
            background: #f97316;
            color: white;
        }

        .priority-medium {
            background: #eab308;
            color: white;
        }

        .priority-low {
            background: #10b981;
            color: white;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-present {
            background: #10b981;
            color: white;
        }

        .status-absent {
            background: #ef4444;
            color: white;
        }

        .status-late {
            background: #f59e0b;
            color: white;
        }

        .btn-view-map {
            margin-top: 15px;
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-view-map:hover {
            background: #5a67d8;
        }

        .no-data {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 12px;
        }

        @media (max-width: 768px) {
            .track-item {
                flex-direction: column;
            }

            .track-time {
                min-width: auto;
            }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Attendance Sessions</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('team.index') }}">Team</a></li>
                <li class="breadcrumb-item active">Attendance Sessions</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <a href="{{ url()->previous() }}" class="btn btn-light btn-sm">
                <i class="feather-arrow-left me-1"></i>Back
            </a>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">
        <div class="sessions-container">
            <!-- Header -->
            <div class="sessions-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4 class="mb-2 text-white">
                            <i class="feather-map-pin me-2"></i>
                            Attendance Session Details
                        </h4>
                        <p class="mb-0 opacity-75">
                            <i class="feather-calendar me-1"></i>
                            {{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }}
                        </p>
                        <p class="mb-0 mt-2 opacity-75">
                            <i class="feather-user me-1"></i>
                            Employee: {{ $user->name }} ({{ $user->employee_id ?? 'N/A' }})
                        </p>
                    </div>
                </div>
            </div>

            @if ($message)
                <div class="alert alert-info mb-4">
                    <i class="feather-info me-2"></i>
                    {{ $message }}
                </div>
            @endif

            <!-- Summary Cards -->
            @if ($hasAttendance)
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted mb-1">Attendance Status</div>
                                <h5 class="mb-0">
                                    @if ($attendance && $attendance->clock_in)
                                        <span class="status-badge status-present">Present</span>
                                    @else
                                        <span class="status-badge status-absent">Absent</span>
                                    @endif
                                </h5>
                            </div>
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                <i class="feather-check-circle text-primary" style="font-size: 24px;"></i>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted mb-1">Total Hours</div>
                                <h5 class="mb-0">{{ $attendance->total_hours ?? '0:00:00' }}</h5>
                            </div>
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="feather-clock text-success" style="font-size: 24px;"></i>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted mb-1">Locations Tracked</div>
                                <h5 class="mb-0">{{ $statistics['total_tracks'] ?? 0 }}</h5>
                            </div>
                            <div class="bg-info bg-opacity-10 rounded-circle p-3">
                                <i class="feather-map-pin text-info" style="font-size: 24px;"></i>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted mb-1">Check In / Out</div>
                                <h6 class="mb-0">
                                    @if ($attendance && $attendance->clock_in)
                                        {{ \Carbon\Carbon::parse($attendance->clock_in)->format('h:i A') }}
                                        @if ($attendance->clock_out)
                                            → {{ \Carbon\Carbon::parse($attendance->clock_out)->format('h:i A') }}
                                        @endif
                                    @else
                                        --:--
                                    @endif
                                </h6>
                            </div>
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                <i class="feather-clock text-warning" style="font-size: 24px;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tasks Section -->
            @if (count($tasks) > 0)
                <div class="info-card">
                    <h6>
                        <i class="feather-list me-2"></i>
                        Tasks for {{ \Carbon\Carbon::parse($date)->format('F j, Y') }}
                        <span class="badge bg-warning text-dark ms-2">{{ $taskCounts['total'] }} Tasks</span>
                    </h6>

                    <!-- Task Summary -->
                    <div class="row g-2 mb-3">
                        @if ($taskCounts['critical'] > 0)
                            <div class="col-auto"><span class="badge bg-danger">Critical:
                                    {{ $taskCounts['critical'] }}</span></div>
                        @endif
                        @if ($taskCounts['high'] > 0)
                            <div class="col-auto"><span class="badge bg-warning">High: {{ $taskCounts['high'] }}</span>
                            </div>
                        @endif
                        @if ($taskCounts['medium'] > 0)
                            <div class="col-auto"><span class="badge bg-info">Medium: {{ $taskCounts['medium'] }}</span>
                            </div>
                        @endif
                        @if ($taskCounts['low'] > 0)
                            <div class="col-auto"><span class="badge bg-success">Low: {{ $taskCounts['low'] }}</span></div>
                        @endif
                        {{-- @if ($taskCounts['due_today'] > 0)
                            <div class="col-auto"><span class="badge bg-danger">Due Today:
                                    {{ $taskCounts['due_today'] }}</span></div>
                        @endif
                        @if ($taskCounts['overdue'] > 0)
                            <div class="col-auto"><span class="badge bg-dark">Overdue: {{ $taskCounts['overdue'] }}</span>
                            </div>
                        @endif --}}
                    </div>

                    <!-- Tasks List -->
                    @foreach ($tasks as $index => $task)
                        <div class="task-card">
                            <div class="task-header" onclick="toggleTask({{ $index }})">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <i class="feather-check-circle text-warning"></i>
                                        <strong>{{ $task->task_code ?? 'N/A' }}</strong>
                                        <span class="priority-badge priority-{{ $task->priority }}">
                                            {{ strtoupper($task->priority) }}
                                        </span>
                                        <span class="status-badge status-{{ $task->status }}">
                                            {{ strtoupper(str_replace('_', ' ', $task->status)) }}
                                        </span>
                                        {{-- @if ($task->deadline_status == 'Due Today')
                                            <span class="badge bg-danger">Due Today</span>
                                        @elseif($task->deadline_status == 'Overdue')
                                            <span class="badge bg-dark">Overdue</span>
                                        @endif --}}
                                    </div>
                                    <i class="feather-chevron-down" id="task-chevron-{{ $index }}"></i>
                                </div>
                                <div class="mt-2">
                                    <div class="fw-semibold">{{ $task->title }}</div>
                                    @if ($task->project_name)
                                        <small class="text-muted">
                                            <i class="feather-folder me-1"></i>{{ $task->project_name }}
                                        </small>
                                    @endif
                                </div>
                            </div>
                            <div class="task-body" id="task-body-{{ $index }}">
                                @if ($task->description)
                                    <div class="mb-3">
                                        <strong>Description:</strong>
                                        <p class="mb-0 text-muted">{{ $task->description }}</p>
                                    </div>
                                @endif
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <strong>Start Date:</strong>
                                            <span>{{ \Carbon\Carbon::parse($task->task_date)->format('d M Y') }}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <strong>Deadline:</strong>
                                            <span
                                                class="{{ $task->deadline_status == 'Overdue' ? 'text-danger' : ($task->deadline_status == 'Due Today' ? 'text-warning' : '') }}">
                                                {{ \Carbon\Carbon::parse($task->deadline_date)->format('d M Y') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                {{-- @if ($task->days_remaining !== null && $task->status != 'completed')
                                    <div class="alert alert-info mt-2 mb-0">
                                        <i class="feather-clock me-2"></i>
                                        @if ($task->days_remaining < 0)
                                            Overdue by {{ abs($task->days_remaining) }} days
                                        @elseif($task->days_remaining == 0)
                                            Due today
                                        @else
                                            {{ $task->days_remaining }} days remaining
                                        @endif
                                    </div>
                                @endif --}}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Location Tracks Section -->
            @if ($hasTracks && count($locationTracks) > 0)
                <div class="info-card">
                    <h6>
                        <i class="feather-map-pin me-2"></i>
                        Location Tracking Details
                        <span class="badge bg-info ms-2">{{ count($locationTracks) }} Records</span>
                    </h6>

                    {{-- @if ($statistics)
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="stat-item text-center p-3 bg-light rounded">
                                    <div class="stat-value">{{ $statistics['total_tracks'] }}</div>
                                    <div class="stat-label text-muted">Total Tracks</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-item text-center p-3 bg-light rounded">
                                    <div class="stat-value">{{ $statistics['tracking_duration_hours'] }}h</div>
                                    <div class="stat-label text-muted">Tracking Duration</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-item text-center p-3 bg-light rounded">
                                    <div class="stat-value">
                                        {{ \Carbon\Carbon::parse($statistics['first_track_time'])->format('h:i A') }}
                                    </div>
                                    <div class="stat-label text-muted">First Track</div>
                                </div>
                            </div>
                        </div>
                    @endif --}}

                    {{-- <!-- Map View Button -->
                    <button class="btn-view-map" onclick="showLocationMap()">
                        <i class="feather-map me-2"></i>View on Map
                    </button> --}}

                    <!-- Tracks List -->
                    <div class="track-list mt-3">
                        @foreach ($locationTracks as $track)
                            <div class="track-item">
                                <div class="track-time">
                                    <strong>{{ \Carbon\Carbon::parse($track['track_time'])->format('h:i A') }}</strong>
                                </div>
                                <div class="track-icon">
                                    <i class="feather-map-pin"></i>
                                </div>
                                <div class="track-details">
                                    <div class="track-address">
                                        {{ $track['address'] ?? 'Location captured' }}
                                    </div>
                                    @if ($track['latitude'] && $track['longitude'])
                                        <div class="track-coords">
                                            📍 {{ number_format($track['latitude'], 6) }},
                                            {{ number_format($track['longitude'], 6) }}
                                        </div>
                                    @endif
                                    @if ($track['battery_per'])
                                        <div class="battery-indicator mt-1">
                                            <i class="feather-battery"></i> Battery: {{ $track['battery_per'] }}%
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif($hasAttendance && !$hasTracks)
                <div class="alert alert-warning">
                    <i class="feather-alert-triangle me-2"></i>
                    No location tracks found for this date. The user may have disabled location tracking or didn't use the
                    mobile app.
                </div>
            @endif
        </div>
    </div>


@endsection
@section('create-modal')
    <!-- Map Modal -->
    <div class="modal fade" id="mapModal" tabindex="-1" aria-labelledby="mapModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mapModalLabel">
                        <i class="feather-map-pin me-2"></i>
                        Location Tracking Map - {{ \Carbon\Carbon::parse($date)->format('F j, Y') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="locationMap" style="height: 500px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let currentMap = null;
        let mapInitialized = false;

        function toggleTask(index) {
            const body = document.getElementById(`task-body-${index}`);
            const chevron = document.getElementById(`task-chevron-${index}`);

            if (body.classList.contains('show')) {
                body.classList.remove('show');
                chevron.classList.remove('feather-chevron-up');
                chevron.classList.add('feather-chevron-down');
            } else {
                body.classList.add('show');
                chevron.classList.remove('feather-chevron-down');
                chevron.classList.add('feather-chevron-up');
            }
        }

        function showLocationMap() {
            const tracks = @json($locationTracks);

            if (!tracks || tracks.length === 0) {
                showToast('No location tracks to display on map', 'warning');
                return;
            }

            const modal = new bootstrap.Modal(document.getElementById('mapModal'));
            modal.show();

            setTimeout(() => {
                initializeMap(tracks);
            }, 500);
        }

        function initializeMap(tracks) {
            const mapContainer = document.getElementById('locationMap');

            if (!mapContainer) return;

            if (currentMap) {
                currentMap.remove();
                currentMap = null;
            }

            // Filter out tracks with valid coordinates
            const validTracks = tracks.filter(track =>
                track.latitude && track.longitude &&
                !isNaN(track.latitude) && !isNaN(track.longitude)
            );

            if (validTracks.length === 0) {
                mapContainer.innerHTML =
                '<div class="alert alert-warning">No valid coordinates found for map display</div>';
                return;
            }

            // Calculate bounds
            const bounds = L.latLngBounds(validTracks.map(track => [track.latitude, track.longitude]));

            currentMap = L.map('locationMap').fitBounds(bounds);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(currentMap);

            // Add markers for each track
            validTracks.forEach((track, index) => {
                const marker = L.marker([track.latitude, track.longitude]).addTo(currentMap);

                const popupContent = `
                <strong>Track #${index + 1}</strong><br>
                Time: ${moment(track.track_time).format('h:mm A')}<br>
                ${track.address ? `Address: ${track.address}<br>` : ''}
                ${track.battery_per ? `Battery: ${track.battery_per}%` : ''}
            `;

                marker.bindPopup(popupContent);
            });

            // Add polyline to show the path
            if (validTracks.length > 1) {
                const latlngs = validTracks.map(track => [track.latitude, track.longitude]);
                const polyline = L.polyline(latlngs, {
                    color: '#667eea',
                    weight: 3
                }).addTo(currentMap);
            }
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            const bgClass = type === 'success' ? 'text-bg-success' : (type === 'error' ? 'text-bg-danger' :
                'text-bg-warning');
            toast.className = `toast align-items-center ${bgClass} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML =
                `<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;

            const toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '1050';
            toastContainer.appendChild(toast);
            document.body.appendChild(toastContainer);

            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();

            toast.addEventListener('hidden.bs.toast', function() {
                toastContainer.remove();
            });
        }
    </script>
@endsection
