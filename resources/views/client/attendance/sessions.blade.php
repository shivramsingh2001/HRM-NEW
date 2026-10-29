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
        .sessions-container .ui-profile-header h4 { font-size: 18px; }

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

        /* Focus button sits over the map's top-right corner (above both Leaflet and Google controls). */
        .session-map-wrap { position: relative; }
        .session-map-focus { position: absolute; top: 10px; right: 10px; z-index: 1000; font-size: 11.5px; padding: 3px 9px;
            border: 1px solid #e2e8f0; box-shadow: 0 1px 4px rgba(15, 23, 42, .15); }

        /* Clock In (green) / Clock Out (red) pins; a clock-in with no clock-out yet is amber and blinks. */
        .punch-pin { position: relative; width: 28px; height: 36px; }
        .punch-pin svg { position: relative; display: block; filter: drop-shadow(0 1px 2px rgba(15, 23, 42, .35)); }
        .punch-pin.is-open svg { animation: punchBlink 1s ease-in-out infinite; }
        .punch-pin.is-open::before { content: ''; position: absolute; left: 50%; bottom: -8px; width: 22px; height: 22px; margin-left: -11px;
            border-radius: 50%; background: rgba(245, 158, 11, .55); animation: punchPulse 1.4s ease-out infinite; }
        @keyframes punchBlink { 50% { opacity: .35; } }
        @keyframes punchPulse { from { transform: scale(.4); opacity: 1; } to { transform: scale(2.4); opacity: 0; } }
        @media (prefers-reduced-motion: reduce) { .punch-pin.is-open svg, .punch-pin.is-open::before { animation: none; } }
        .pin-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 10.5px; color: #64748b; }
        .pin-legend i { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 4px; vertical-align: -1px; }

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
            <!-- Header: who + this day's status, check-in / check-out (with date) and tracking -->
            @php
                $punchPins = $punchPins ?? [];
                $hasTrackList = $hasTracks && count($locationTracks) > 0;
                $hasMap = $hasTrackList || count($punchPins) > 0;
                $profileHeader = app(\App\Services\Dashboard\ProfileHeaderData::class)->forUser($user, $date);
                $sessionStats = $hasAttendance ? [
                    ['label' => 'Locations', 'value' => $statistics['total_tracks'] ?? 0],
                    ['label' => 'Distance', 'value' => number_format($statistics['distance_km'] ?? 0, 2) . ' km'],
                    ['label' => 'Tracked', 'value' => isset($statistics['tracking_duration_minutes'])
                        ? intdiv($statistics['tracking_duration_minutes'], 60) . 'h ' . ($statistics['tracking_duration_minutes'] % 60) . 'm' : '--'],
                ] : [];
            @endphp
            <x-ui.profile-header :profile="$profileHeader" :date="$date" :stats="$sessionStats" />

            @if ($message)
                <div class="alert alert-info mb-4">
                    <i class="feather-info me-2"></i>
                    {{ $message }}
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
            @if ($hasMap)
                <div class="info-card">
                    <h6>
                        <i class="feather-map-pin me-2"></i>
                        Location Tracking Details
                        @if ($hasTrackList)
                            <span class="badge bg-info ms-2">{{ count($locationTracks) }} Records</span>
                        @endif
                    </h6>

                    <!-- Route Map (all points joined by a polyline, in chronological order) -->
                    <div class="session-map-wrap">
                        <div id="sessionLocationMap" class="location-map"></div>
                        <button type="button" class="btn btn-sm btn-light session-map-focus d-none" id="sessionMapFocusBtn"
                            title="Show the whole route" onclick="focusSessionMap && focusSessionMap()">
                            <i class="feather-crosshair me-1"></i>Focus
                        </button>
                    </div>
                    <div class="pin-legend mb-1">
                        <span><i style="background:#16A34A"></i>Clock In</span>
                        <span><i style="background:#DC2626"></i>Clock Out</span>
                        <span><i style="background:#F59E0B"></i>Clocked in, no clock-out yet (blinking)</span>
                        @if ($hasTrackList)<span><i style="background:#0D6EFD"></i>Route</span>@endif
                    </div>
                    <div class="mb-2" style="font-size: 10.5px; color: #64748b;">
                        Click a pin{{ $hasTrackList ? ' or the route line' : '' }} to see the time and location. Hold Ctrl and scroll to zoom.
                    </div>

                    <!-- Tracks List -->
                    @if ($hasTrackList)
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
                    @endif
                </div>
            @endif
            @if ($hasAttendance && ! $hasTrackList)
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

        // Clock In / Clock Out punches that carry GPS (PunchMapPins).
        function sessionPins() {
            return @json($punchPins);
        }

        const PIN_COLORS = { in: '#16A34A', out: '#DC2626', open: '#F59E0B' };

        function pinColor(pin) {
            return pin.open ? PIN_COLORS.open : PIN_COLORS[pin.type];
        }

        function pinLabel(pin) {
            if (pin.open) return 'Clock In (no clock-out yet)';

            return pin.type === 'out' ? 'Clock Out' : 'Clock In';
        }

        // Map pin (28 x 36, tip at the bottom centre) with IN / OUT written on it.
        function pinSvg(pin) {
            return `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="36" viewBox="-1 -1 28 36">
                <path d="M13 0C5.8 0 0 5.8 0 13c0 9.6 13 21 13 21s13-11.4 13-21C26 5.8 20.2 0 13 0z" fill="${pinColor(pin)}" stroke="#fff" stroke-width="2"/>
                <text x="13" y="16.5" text-anchor="middle" font-family="Arial, sans-serif" font-size="${pin.type === 'out' ? 7.5 : 9}" font-weight="700" fill="#fff">${pin.type === 'out' ? 'OUT' : 'IN'}</text>
            </svg>`;
        }

        function pinPopupHtml(pin) {
            const span = document.createElement('span');
            span.textContent = pin.address || '';

            return `
                <strong style="color:${pinColor(pin)}">${pinLabel(pin)}</strong><br>
                ${pin.time ? `Time: ${moment(pin.time).format('D MMM, h:mm A')}<br>` : ''}
                ${pin.address ? `Address: ${span.innerHTML}<br>` : ''}
                Lat/Long: ${pin.lat.toFixed(6)}, ${pin.long.toFixed(6)}
            `;
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

        // Set by whichever map gets drawn: brings the whole route back into view.
        let focusSessionMap = null;

        function enableSessionMapFocus(focus) {
            focusSessionMap = focus;
            const button = document.getElementById('sessionMapFocusBtn');
            if (button) button.classList.remove('d-none');
        }

        // Google Maps version of the route — used only when a key is configured.
        // Any failure (script blocked, key rejected) falls back to Leaflet below.
        function initGoogleSessionMap() {
            const mapContainer = document.getElementById('sessionLocationMap');
            if (!mapContainer || sessionMapDrawn) return;

            const validTracks = sessionTracks();
            const pins = sessionPins();
            if (validTracks.length === 0 && pins.length === 0) {
                initSessionMap();
                return;
            }

            sessionMapDrawn = true;

            const path = validTracks.map(track => ({
                lat: parseFloat(track.latitude),
                lng: parseFloat(track.longitude),
            }));
            const pinPoints = pins.map(pin => ({ lat: pin.lat, lng: pin.long }));
            const allPoints = path.concat(pinPoints);

            const map = new google.maps.Map(mapContainer, {
                center: allPoints[0],
                zoom: 15,
                mapTypeControl: false,
                // The page scrolls over the map; Ctrl + scroll (or two fingers) zooms it.
                gestureHandling: 'cooperative',
                streetViewControl: false,
            });

            if (path.length > 0) {
                new google.maps.Polyline({
                    path,
                    map,
                    strokeColor: '#0D6EFD',
                    strokeWeight: 4,
                    strokeOpacity: 0.85,
                });
            }

            const infoWindow = new google.maps.InfoWindow();
            const bounds = new google.maps.LatLngBounds();
            allPoints.forEach(point => bounds.extend(point));

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
            } else if (path.length === 1) {
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

            // Clock In / Clock Out pins; an open clock-in blinks.
            pins.forEach((pin, index) => {
                const marker = new google.maps.Marker({
                    position: pinPoints[index],
                    map,
                    title: pinLabel(pin),
                    zIndex: pin.open ? 1000 : 500,
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(pinSvg(pin)),
                        scaledSize: new google.maps.Size(28, 36),
                        anchor: new google.maps.Point(14, 36),
                    },
                });
                marker.addListener('click', () => {
                    infoWindow.setContent(pinPopupHtml(pin));
                    infoWindow.open({ anchor: marker, map });
                });
                if (pin.open) {
                    let dim = false;
                    setInterval(() => marker.setOpacity((dim = !dim) ? 0.35 : 1), 600);
                }
            });

            const fitAll = () => {
                if (allPoints.length > 1) {
                    map.fitBounds(bounds, 24);
                } else {
                    map.setCenter(allPoints[0]);
                    map.setZoom(15);
                }
            };
            fitAll();

            enableSessionMapFocus(function() {
                infoWindow.close();
                fitAll();
            });
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
        // on the line shows the nearest tracked point. Clock In / Clock Out
        // pins sit on top; an open clock-in blinks.
        function initSessionMap() {
            const mapContainer = document.getElementById('sessionLocationMap');
            if (!mapContainer || sessionMapDrawn) return;

            const validTracks = sessionTracks();
            const pins = sessionPins();

            if (validTracks.length === 0 && pins.length === 0) {
                mapContainer.innerHTML =
                    '<div class="alert alert-warning mb-0">No valid coordinates found for map display</div>';
                return;
            }

            sessionMapDrawn = true;

            // The page scrolls over the map; the wheel zooms it only while Ctrl / Cmd is held.
            const map = L.map('sessionLocationMap', { scrollWheelZoom: false });
            mapContainer.addEventListener('wheel', function(event) {
                if (event.ctrlKey || event.metaKey) {
                    event.preventDefault(); // not the browser's own page zoom
                    map.scrollWheelZoom.enable();
                } else {
                    map.scrollWheelZoom.disable();
                }
            }, { capture: true, passive: false });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            const latlngs = validTracks.map(track => [track.latitude, track.longitude]);

            // One continuous polyline through every point, in the order they
            // were recorded — the full path the employee's location switched
            // through during this session.
            if (latlngs.length > 0) {
                L.polyline(latlngs, {
                    color: '#0D6EFD',
                    weight: 4,
                    opacity: 0.85,
                    lineJoin: 'round',
                }).addTo(map);
            }

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
            } else if (latlngs.length === 1) {
                // A single point has no line to draw, so it gets one dot.
                L.circleMarker(latlngs[0], {
                    radius: 6,
                    color: '#fff',
                    weight: 2,
                    fillColor: '#0D6EFD',
                    fillOpacity: 1,
                }).addTo(map).on('click', () => showTrack(0));
            }

            pins.forEach(pin => {
                L.marker([pin.lat, pin.long], {
                    title: pinLabel(pin),
                    zIndexOffset: pin.open ? 1000 : 500,
                    icon: L.divIcon({
                        className: '',
                        html: `<div class="punch-pin${pin.open ? ' is-open' : ''}">${pinSvg(pin)}</div>`,
                        iconSize: [28, 36],
                        iconAnchor: [14, 36],
                        popupAnchor: [0, -32],
                    }),
                }).addTo(map).bindPopup(pinPopupHtml(pin));
            });

            const allPoints = latlngs.concat(pins.map(pin => [pin.lat, pin.long]));
            const fitAll = () => allPoints.length > 1
                ? map.fitBounds(L.latLngBounds(allPoints), { padding: [24, 24] })
                : map.setView(allPoints[0], 16);
            fitAll();

            enableSessionMapFocus(function() {
                map.closePopup();
                fitAll();
            });
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

        @if ($googleMapsKey && $hasMap)
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
