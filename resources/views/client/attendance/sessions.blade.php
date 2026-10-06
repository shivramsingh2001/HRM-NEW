@extends('client.layout.master')

@section('style')
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <style>
        .sessions-container {
            padding: 0;
            font-size: 12px;
        }

        .sessions-container h4 { font-size: 15px; }
        .sessions-container h5 { font-size: 12.5px; }
        .sessions-container h6 { font-size: 12px; }

        .sessions-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-mid) 100%);
            border-radius: 8px;
            padding: 8px 12px;
            margin-bottom: 10px;
            color: white;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .summary-card {
            background: white;
            border-radius: 8px;
            padding: 8px 10px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
            border-left: 3px solid var(--primary-mid);
            transition: transform 0.2s ease;
        }

        .summary-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .summary-card h5, .summary-card h6 { font-size: 12.5px; }

        .summary-card .icon-circle {
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            flex: none;
        }

        .summary-card .icon-circle i { font-size: 16px; }

        .info-card {
            background: white;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        }

        .info-card h6 {
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
        }

        .track-list {
            max-height: 320px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }

        .track-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.2s ease;
            font-size: 11.5px;
        }

        .track-item:hover {
            background: #f8fafd;
        }

        .track-time {
            min-width: 66px;
            font-weight: 600;
            color: #2d3748;
            font-size: 11px;
        }

        .track-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-light);
            color: var(--icon-color, #0D6EFD);
            font-size: 10.5px;
            flex: none;
        }

        .track-details {
            flex: 1;
        }

        .track-address {
            color: #2d3748;
            margin-bottom: 2px;
        }

        .track-coords {
            font-size: 0.65rem;
            color: #9ca3af;
            font-family: monospace;
        }

        .battery-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 1px 7px;
            background: #e2e8f0;
            border-radius: 10px;
            font-size: 0.65rem;
        }

        .location-map {
            height: 300px;
            border-radius: 6px;
            margin-bottom: 10px;
            background: #f1f5f9;
        }

        .task-card {
            background: white;
            border-radius: 8px;
            margin-bottom: 8px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }

        .task-header {
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fefce8;
            border-left: 3px solid #f59e0b;
            font-size: 12px;
        }

        .task-header:hover {
            background: #fef9c3;
        }

        .task-body {
            padding: 12px;
            display: none;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
        }

        .task-body.show {
            display: block;
        }

        .priority-badge {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
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
    <x-ui.page-header title="Attendance Sessions" back :parent="['label' => 'Team', 'route' => 'team.index']" />

    <div class="main-content" style="padding: 20px !important;">
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
                                        <x-ui.status-badge status="present" label="Present" />
                                    @else
                                        <x-ui.status-badge status="absent" label="Absent" />
                                    @endif
                                </h5>
                            </div>
                            <div class="icon-circle" style="background: var(--primary-light);">
                                <i class="feather-check-circle" style="color: var(--icon-color, #0D6EFD);"></i>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted mb-1">Total Hours</div>
                                <h5 class="mb-0">{{ $attendance->total_hours ?? '0:00:00' }}</h5>
                            </div>
                            <div class="icon-circle" style="background: var(--primary-light);">
                                <i class="feather-clock" style="color: var(--icon-color, #0D6EFD);"></i>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted mb-1">Locations Tracked</div>
                                <h5 class="mb-0">{{ $statistics['total_tracks'] ?? 0 }}</h5>
                            </div>
                            <div class="icon-circle" style="background: var(--primary-light);">
                                <i class="feather-map-pin" style="color: var(--primary-dark);"></i>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted mb-1">Distance Travelled</div>
                                <h5 class="mb-0">{{ number_format($statistics['distance_km'] ?? 0, 2) }} km</h5>
                            </div>
                            <div class="icon-circle" style="background: var(--primary-light);">
                                <i class="feather-navigation" style="color: var(--icon-color, #0D6EFD);"></i>
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
                            <div class="icon-circle" style="background: var(--primary-light);">
                                <i class="feather-clock" style="color: var(--icon-color, #0D6EFD);"></i>
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
                                        <x-ui.status-badge :status="$task->status" :label="strtoupper(str_replace('_', ' ', $task->status))" />
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

                    <!-- Route Map (all points joined by a polyline, in chronological order) -->
                    <div id="sessionLocationMap" class="location-map"></div>
                    <div class="mb-2" style="font-size: 10.5px; color: #64748b;">
                        Click the route line to see the time and location at that point.
                    </div>

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
                                            <a href="https://www.google.com/maps?q={{ $track['latitude'] }},{{ $track['longitude'] }}"
                                                target="_blank" rel="noopener" class="ms-2">Open in Google Maps</a>
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

@section('script-area')
    @php $googleMapsKey = config('services.google_maps.key'); @endphp
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
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

        // Tracked points that carry usable coordinates, in recorded order.
        function sessionTracks() {
            const tracks = @json($locationTracks);

            return (tracks || []).filter(track =>
                track.latitude && track.longitude &&
                !isNaN(track.latitude) && !isNaN(track.longitude)
            );
        }

        function trackLabel(index, total) {
            if (index === 0) return 'Start';

            return index === total - 1 ? 'End' : `Point #${index + 1}`;
        }

        // The map shows the route line only (no dots). A click on the line
        // opens the details of the tracked point closest to the click.
        function nearestTrackIndex(tracks, lat, lng) {
            let best = 0;
            let bestDistance = Infinity;

            tracks.forEach((track, index) => {
                const dLat = parseFloat(track.latitude) - lat;
                const dLng = parseFloat(track.longitude) - lng;
                const distance = dLat * dLat + dLng * dLng;

                if (distance < bestDistance) {
                    bestDistance = distance;
                    best = index;
                }
            });

            return best;
        }

        function trackPopupHtml(track, label) {
            const safe = value => {
                const span = document.createElement('span');
                span.textContent = value;
                return span.innerHTML;
            };

            return `
                <strong>${label}</strong><br>
                Time: ${moment(track.track_time).format('h:mm A')}<br>
                ${track.address ? `Address: ${safe(track.address)}<br>` : ''}
                Lat/Long: ${parseFloat(track.latitude).toFixed(6)}, ${parseFloat(track.longitude).toFixed(6)}<br>
                ${track.battery_per ? `Battery: ${safe(track.battery_per)}%` : ''}
            `;
        }

        // Set once either map has been drawn, so a late Google callback or a
        // fallback never draws a second map in the same box.
        let sessionMapDrawn = false;

        // Google Maps version of the route — used only when a key is configured.
        // Any failure (script blocked, key rejected) falls back to Leaflet below.
        function initGoogleSessionMap() {
            const mapContainer = document.getElementById('sessionLocationMap');
            if (!mapContainer || sessionMapDrawn) return;

            const validTracks = sessionTracks();
            if (validTracks.length === 0) {
                initSessionMap();
                return;
            }

            sessionMapDrawn = true;

            const path = validTracks.map(track => ({
                lat: parseFloat(track.latitude),
                lng: parseFloat(track.longitude),
            }));

            const map = new google.maps.Map(mapContainer, {
                center: path[0],
                zoom: 15,
                mapTypeControl: false,
                streetViewControl: false,
            });

            new google.maps.Polyline({
                path,
                map,
                strokeColor: '#0D6EFD',
                strokeWeight: 4,
                strokeOpacity: 0.85,
            });

            const infoWindow = new google.maps.InfoWindow();
            const bounds = new google.maps.LatLngBounds();
            path.forEach(point => bounds.extend(point));

            const showTrack = index => {
                infoWindow.setContent(trackPopupHtml(validTracks[index], trackLabel(index, validTracks.length)));
                infoWindow.setPosition(path[index]);
                infoWindow.open(map);
            };

            if (path.length > 1) {
                // Wide invisible line on top, so the thin route is easy to click.
                new google.maps.Polyline({
                    path,
                    map,
                    strokeOpacity: 0,
                    strokeWeight: 20,
                    zIndex: 2,
                }).addListener('click', event => {
                    showTrack(nearestTrackIndex(validTracks, event.latLng.lat(), event.latLng.lng()));
                });
            } else {
                // A single point has no line to draw, so it gets one dot.
                new google.maps.Marker({
                    position: path[0],
                    map,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 6,
                        fillColor: '#0D6EFD',
                        fillOpacity: 1,
                        strokeColor: '#fff',
                        strokeWeight: 2,
                    },
                }).addListener('click', () => showTrack(0));
            }

            if (path.length > 1) {
                map.fitBounds(bounds, 24);
            }
        }

        // Google calls this when it refuses the key (wrong key, billing off,
        // domain not allowed) — swap the broken map for the Leaflet one.
        window.gm_authFailure = function() {
            const mapContainer = document.getElementById('sessionLocationMap');
            if (!mapContainer) return;

            const fresh = mapContainer.cloneNode(false);
            fresh.removeAttribute('style');
            mapContainer.replaceWith(fresh);

            sessionMapDrawn = false;
            initSessionMap();
        };

        // Renders the full route for this session in one shot: every tracked
        // point joined chronologically by a single polyline. No dots — a click
        // on the line shows the nearest tracked point.
        function initSessionMap() {
            const mapContainer = document.getElementById('sessionLocationMap');
            if (!mapContainer || sessionMapDrawn) return;

            const validTracks = sessionTracks();

            if (validTracks.length === 0) {
                mapContainer.innerHTML =
                    '<div class="alert alert-warning mb-0">No valid coordinates found for map display</div>';
                return;
            }

            sessionMapDrawn = true;

            const map = L.map('sessionLocationMap');

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            const latlngs = validTracks.map(track => [track.latitude, track.longitude]);

            // One continuous polyline through every point, in the order they
            // were recorded — the full path the employee's location switched
            // through during this session.
            L.polyline(latlngs, {
                color: '#0D6EFD',
                weight: 4,
                opacity: 0.85,
                lineJoin: 'round',
            }).addTo(map);

            const showTrack = index => {
                L.popup()
                    .setLatLng(latlngs[index])
                    .setContent(trackPopupHtml(validTracks[index], trackLabel(index, validTracks.length)))
                    .openOn(map);
            };

            if (latlngs.length > 1) {
                // Wide invisible line on top, so the thin route is easy to click.
                L.polyline(latlngs, { weight: 20, opacity: 0 })
                    .addTo(map)
                    .on('click', event => {
                        showTrack(nearestTrackIndex(validTracks, event.latlng.lat, event.latlng.lng));
                    });
            } else {
                // A single point has no line to draw, so it gets one dot.
                L.circleMarker(latlngs[0], {
                    radius: 6,
                    color: '#fff',
                    weight: 2,
                    fillColor: '#0D6EFD',
                    fillOpacity: 1,
                }).addTo(map).on('click', () => showTrack(0));
            }

            map.fitBounds(L.latLngBounds(latlngs), { padding: [24, 24] });
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

        @if ($googleMapsKey && $hasTracks && count($locationTracks) > 0)
            // Google draws the map through its callback; Leaflet only steps in
            // if the script cannot be loaded at all.
            document.addEventListener('DOMContentLoaded', function() {
                const script = document.createElement('script');
                script.src = 'https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsKey) }}&loading=async&callback=initGoogleSessionMap';
                script.async = true;
                script.onerror = initSessionMap;
                document.head.appendChild(script);
            });
        @else
            document.addEventListener('DOMContentLoaded', initSessionMap);
        @endif
    </script>
@endsection
