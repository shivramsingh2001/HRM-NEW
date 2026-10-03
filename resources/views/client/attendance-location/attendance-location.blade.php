{{-- resources/views/client/attendance-location/attendance-location.blade.php
     Renamed from client/branch/branch.blade.php (2026_09_20) — purely the
     attendance-geofencing entity. See client/branch/branch.blade.php for
     the separate, organizational Branch module. --}}
@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ATTENDANCE LOCATION CARDS — all-blue theme, compact spacing (mirrors client/department/department.blade.php) ==================== */
    .location-card {
        border: 1px solid #eaeef5;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
        transition: all .2s ease;
    }

    .location-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px -6px rgba(30, 50, 110, .14);
        border-color: #dfe5f0;
    }

    .location-card .side-stick {
        background-color: #0D6EFD;
    }

    .loc-title {
        font-size: 11.5px !important;
        font-weight: 700;
        color: #1a2236;
        max-width: 62%;
    }

    .loc-badges {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .loc-badge {
        padding: 2px 7px;
        border-radius: 30px;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: .2px;
        white-space: nowrap;
    }

    .badge-active { background: #3b82f6; color: #fff; }
    .badge-inactive { background: #0D6EFD; color: #fff; }
    .badge-geofence-off { background: #93c5fd; color: #0D6EFD; }

    .loc-info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f4f6fb;
        border-radius: 8px;
        padding: 5px 8px;
    }

    .loc-info-label {
        font-size: 8px;
        color: #6b7385;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .loc-info-value {
        font-size: 9.5px;
        font-weight: 600;
        color: #1a2236;
    }

    .loc-info-value i { color: var(--icon-color, #0D6EFD); }

    .loc-description {
        color: #475569;
        font-size: 9.5px;
        line-height: 1.4;
    }

    .loc-card-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 5px;
        padding-top: 6px;
        margin-top: 4px;
        border-top: 1px solid #eaeef5;
    }




    /* ==================== EMPTY STATE ==================== */
    .empty-state {
        padding: 36px 20px;
        text-align: center;
        background: linear-gradient(145deg, #ffffff 0%, #f4f6fb 100%);
        border-radius: 14px;
    }

    .empty-state i { font-size: 48px; color: #93c5fd; margin-bottom: 12px; }
    .empty-state h4 { color: #1a2236; font-size: 14px; font-weight: 600; margin-bottom: 6px; }
    .empty-state p { color: #6b7385; font-size: 11.5px; margin-bottom: 0; }

    /* Map + coordinate picker (kept from the original Branch page) */
    #addMap, #editMap { height: 200px; width: 100%; border-radius: 8px; border: 1px solid #dfe5f0; margin-bottom: 10px; }
    .location-status { font-size: 10.5px; margin-top: 5px; }
    .location-status i { font-size: 12px; }
    .location-status.text-success { color: #0D6EFD; }
    .location-status.text-warning { color: #93c5fd; }
    .location-status.text-danger { color: #dc2626; }
    .coordinate-input-group { display: flex; gap: 8px; align-items: center; }
    .coordinate-input-group .form-control { flex: 1; }
    .coordinate-input-group .btn { white-space: nowrap; font-size: 10.5px; padding: 6px 10px; }

    /* ==================== COMPACT MODAL — core chrome centralized in
       public/assets/css/theme-custom.css; only this page's own extras here. ==================== */
    .compact-modal .modal-header .fs-18 { font-size: 13px !important; }
    .compact-modal .form-group { margin-bottom: 0; }
    .compact-modal .form-control,
    .compact-modal .form-check-label { font-size: 11.5px; }
</style>
@endsection

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Attendance Location Management" current="Attendance Locations">
        <x-slot:actions>
            <div class="hstack gap-2">
                <a href="javascript:void(0)" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#addLocationModal">
                        <i class="feather-plus me-2"></i>Add Attendance Location
                    </a>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid">
            @forelse ($locations as $location)
                <div class="col-xxl-3 col-xl-3 col-lg-3 col-md-6 col-sm-6 single-note-item">
                    <div class="card location-card card-body mb-2 stretch stretch-full position-relative border-0">

                        <span class="side-stick"></span>

                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h5 class="loc-title note-title text-truncate mb-0">
                                {{ $location->name }}
                            </h5>
                            <div class="loc-badges">
                                @if (!$location->geofence_enabled)
                                    <span class="loc-badge badge-geofence-off" title="Geofencing disabled — any distance is accepted">
                                        <i class="feather-map-pin"></i> Geofence off
                                    </span>
                                @endif
                                <span class="loc-badge {{ $location->status ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $location->status ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        <div class="loc-info-row mb-1">
                            <div>
                                <small class="loc-info-label d-block">Coordinates</small>
                                <span class="loc-info-value">
                                    <i class="bi bi-geo-alt me-1"></i>
                                    @if($location->latitude && $location->longitude)
                                        {{ number_format($location->latitude, 4) }}, {{ number_format($location->longitude, 4) }}
                                    @else
                                        Not set
                                    @endif
                                </span>
                            </div>
                            <div class="text-end">
                                <small class="loc-info-label d-block">Radius</small>
                                <span class="loc-info-value">
                                    <i class="bi bi-record-circle me-1"></i>{{ $location->radius ?? 50 }}m
                                </span>
                            </div>
                        </div>

                        <div class="note-content flex-grow-1">
                            <p class="loc-description text-truncate-3-line mb-0">
                                {{ $location->description ?? 'No description provided.' }}
                            </p>
                        </div>

                        <div class="loc-card-footer">
                            <a href="javascript:void(0)" class="action-btn edit-location" title="Edit Attendance Location"
                                data-id="{{ $location->id }}"
                                data-name="{{ $location->name }}"
                                data-description="{{ $location->description }}"
                                data-latitude="{{ $location->latitude }}"
                                data-longitude="{{ $location->longitude }}"
                                data-radius="{{ $location->radius }}"
                                data-geofence-enabled="{{ $location->geofence_enabled ? 1 : 0 }}"
                                data-status="{{ $location->status }}">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <a href="javascript:void(0)" class="action-btn danger delete-location" title="Delete Attendance Location"
                                data-id="{{ $location->id }}"
                                data-name="{{ $location->name }}">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>

                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="empty-state">
                        <i class="bi bi-geo-alt"></i>
                        <h4>No Attendance Locations Found</h4>
                        <p>Get started by adding your first attendance location</p>
                    </div>
                </div>
            @endforelse
        </div>

        @if ($locations->hasPages())
            <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
                <div class="text-muted small">
                    Showing {{ $locations->firstItem() }} to {{ $locations->lastItem() }} of {{ $locations->total() }} entries
                </div>
                <div>{{ $locations->appends(request()->query())->links() }}</div>
            </div>
        @endif
    </div>
@endsection

@section('create-modal')
    <!-- Add Attendance Location Modal -->
    <div class="modal fade-scale" id="addLocationModal" tabindex="-1" aria-labelledby="addLocationModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add Attendance Location</span>
                    </h2>
                    <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <div id="addFormError" class="alert alert-danger d-none"></div>
                            <form action="{{ route('attendance-location.store') }}" method="POST" id="addLocationForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="add_name">Name *</label>
                                            <input type="text" class="form-control" name="name" id="add_name"
                                                placeholder="Enter location name" required>
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="add_status">Status *</label>
                                            <select class="form-control" name="status" id="add_status" required>
                                                <option value="1" selected>Active</option>
                                                <option value="0">Inactive</option>
                                            </select>
                                            <small class="text-danger error-text status_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="add_description">Description</label>
                                            <textarea class="form-control" name="description" id="add_description"
                                                rows="2" placeholder="Enter description..."></textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-2">
                                        <label class="fw-semibold"><i class="feather-map-pin me-1"></i>Location Selection</label>
                                        <div id="addMap"></div>
                                        <div id="addLocationStatus" class="location-status text-warning">
                                            <i class="feather-alert-circle"></i>
                                            <span>Click on map to set the location</span>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="add_latitude">Latitude</label>
                                            <div class="coordinate-input-group">
                                                <input type="number" step="any" class="form-control" name="latitude"
                                                    id="add_latitude" placeholder="Click map or enter manually" readonly>
                                                <button type="button" class="btn btn-outline-secondary" id="addDetectLocation">
                                                    <i class="feather-navigation"></i> Detect
                                                </button>
                                            </div>
                                            <small class="text-danger error-text latitude_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="add_longitude">Longitude</label>
                                            <input type="number" step="any" class="form-control" name="longitude"
                                                id="add_longitude" placeholder="Click map or enter manually" readonly>
                                            <small class="text-danger error-text longitude_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="addManualEntry">
                                            <label class="form-check-label" for="addManualEntry">Enter coordinates manually</label>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="add_radius">Geofence Radius (meters)</label>
                                            <input type="number" class="form-control" name="radius" id="add_radius"
                                                value="50" min="10" max="5000">
                                            <small class="text-danger error-text radius_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3 d-flex align-items-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="geofence_enabled"
                                                id="add_geofence_enabled" value="1" checked>
                                            <label class="form-check-label" for="add_geofence_enabled">
                                                Geofencing enabled
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="feather-save me-2"></i>Save
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0)" class="btn btn-modal-cancel float-end" data-bs-dismiss="modal">
                                            <i class="feather-x me-2"></i>Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Attendance Location Modal -->
    <div class="modal fade-scale" id="editLocationModal" tabindex="-1" aria-labelledby="editLocationModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Attendance Location</span>
                    </h2>
                    <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <div id="editFormError" class="alert alert-danger d-none"></div>
                            <form id="editLocationForm">
                                @csrf
                                <input type="hidden" name="id" id="edit_id">
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_name">Name *</label>
                                            <input type="text" class="form-control" name="name" id="edit_name"
                                                placeholder="Enter location name" required>
                                            <small class="text-danger error-text edit_name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_status">Status *</label>
                                            <select class="form-control" name="status" id="edit_status" required>
                                                <option value="1">Active</option>
                                                <option value="0">Inactive</option>
                                            </select>
                                            <small class="text-danger error-text edit_status_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_description">Description</label>
                                            <textarea class="form-control" name="description" id="edit_description"
                                                rows="2" placeholder="Enter description..."></textarea>
                                            <small class="text-danger error-text edit_description_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-2">
                                        <label class="fw-semibold"><i class="feather-map-pin me-1"></i>Location Selection</label>
                                        <div id="editMap"></div>
                                        <div id="editLocationStatus" class="location-status text-warning">
                                            <i class="feather-info"></i>
                                            <span>Click on map to update the location</span>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_latitude">Latitude</label>
                                            <div class="coordinate-input-group">
                                                <input type="number" step="any" class="form-control" name="latitude"
                                                    id="edit_latitude" placeholder="Click map or enter manually" readonly>
                                                <button type="button" class="btn btn-outline-secondary" id="editDetectLocation">
                                                    <i class="feather-navigation"></i> Detect
                                                </button>
                                            </div>
                                            <small class="text-danger error-text edit_latitude_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_longitude">Longitude</label>
                                            <input type="number" step="any" class="form-control" name="longitude"
                                                id="edit_longitude" placeholder="Click map or enter manually" readonly>
                                            <small class="text-danger error-text edit_longitude_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="editManualEntry">
                                            <label class="form-check-label" for="editManualEntry">Edit coordinates manually</label>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_radius">Geofence Radius (meters)</label>
                                            <input type="number" class="form-control" name="radius" id="edit_radius"
                                                min="10" max="5000">
                                            <small class="text-danger error-text edit_radius_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3 d-flex align-items-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="geofence_enabled"
                                                id="edit_geofence_enabled" value="1">
                                            <label class="form-check-label" for="edit_geofence_enabled">
                                                Geofencing enabled
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="feather-save me-2"></i>Update
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0)" class="btn btn-modal-cancel float-end" data-bs-dismiss="modal">
                                            <i class="feather-x me-2"></i>Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <x-ui.modal id="deleteLocationModal" title="Delete Attendance Location">
        <div id="deleteFormError" class="alert alert-danger d-none"></div>
        <p class="fs-12 mb-3">Are you sure you want to delete <strong id="deleteLocationName"></strong>? This cannot be undone.</p>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteLocation">
                <i class="feather-trash-2 me-2"></i>Delete
            </button>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="modal">Cancel</button>
        </div>
    </x-ui.modal>
@endsection

@section('script-area')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        $(document).ready(function() {
            // ==================== ADD MAP ====================
            let addMap, addMarker, addManualMode = false;

            function initAddMap() {
                if (!addMap) {
                    addMap = L.map('addMap').setView([28.6139, 77.2090], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(addMap);
                    addMap.on('click', function(e) {
                        if (!addManualMode) setAddMarker(e.latlng.lat, e.latlng.lng);
                    });
                }
            }

            function setAddMarker(lat, lng) {
                if (addMarker) addMap.removeLayer(addMarker);
                addMarker = L.marker([lat, lng], { draggable: true }).addTo(addMap);
                addMarker.on('dragend', function(e) {
                    const pos = e.target.getLatLng();
                    updateAddCoordinates(pos.lat, pos.lng);
                });
                updateAddCoordinates(lat, lng);
                addMap.setView([lat, lng], 15);
            }

            function updateAddCoordinates(lat, lng) {
                $('#add_latitude').val(lat.toFixed(6));
                $('#add_longitude').val(lng.toFixed(6));
                $('#addLocationStatus').removeClass('text-warning text-danger text-success').addClass('text-success')
                    .html('<i class="feather-check-circle"></i><span>Location set successfully</span>');
            }

            $('#addDetectLocation').on('click', function() {
                if (navigator.geolocation) {
                    $('#addLocationStatus').removeClass('text-warning text-danger text-success').addClass('text-info')
                        .html('<i class="feather-loader"></i><span>Detecting your location...</span>');
                    navigator.geolocation.getCurrentPosition(
                        (pos) => setAddMarker(pos.coords.latitude, pos.coords.longitude),
                        () => $('#addLocationStatus').removeClass('text-warning text-info text-success').addClass('text-danger')
                            .html('<i class="feather-alert-circle"></i><span>Unable to detect location</span>')
                    );
                } else {
                    alert('Geolocation is not supported by your browser');
                }
            });

            $('#addManualEntry').on('change', function() {
                addManualMode = $(this).is(':checked');
                if (addManualMode) {
                    $('#add_latitude, #add_longitude').prop('readonly', false);
                    $('#addDetectLocation').prop('disabled', true);
                    $('#addLocationStatus').removeClass('text-success text-warning text-danger').addClass('text-info')
                        .html('<i class="feather-edit"></i><span>Manual entry mode</span>');
                } else {
                    $('#add_latitude, #add_longitude').prop('readonly', true).val('');
                    $('#addDetectLocation').prop('disabled', false);
                    if (addMarker) { addMap.removeLayer(addMarker); addMarker = null; }
                    $('#addLocationStatus').removeClass('text-success text-info text-danger').addClass('text-warning')
                        .html('<i class="feather-alert-circle"></i><span>Click on map to set the location</span>');
                }
            });

            // ==================== EDIT MAP ====================
            let editMap, editMarker, editManualMode = false;

            function initEditMap() {
                if (!editMap) {
                    editMap = L.map('editMap').setView([28.6139, 77.2090], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(editMap);
                    editMap.on('click', function(e) {
                        if (!editManualMode) setEditMarker(e.latlng.lat, e.latlng.lng);
                    });
                }
            }

            function setEditMarker(lat, lng) {
                if (editMarker) editMap.removeLayer(editMarker);
                editMarker = L.marker([lat, lng], { draggable: true }).addTo(editMap);
                editMarker.on('dragend', function(e) {
                    const pos = e.target.getLatLng();
                    updateEditCoordinates(pos.lat, pos.lng);
                });
                updateEditCoordinates(lat, lng);
                editMap.setView([lat, lng], 15);
            }

            function updateEditCoordinates(lat, lng) {
                $('#edit_latitude').val(lat.toFixed(6));
                $('#edit_longitude').val(lng.toFixed(6));
                $('#editLocationStatus').removeClass('text-warning text-danger text-success').addClass('text-success')
                    .html('<i class="feather-check-circle"></i><span>Location updated</span>');
            }

            $('#editDetectLocation').on('click', function() {
                if (navigator.geolocation) {
                    $('#editLocationStatus').removeClass('text-warning text-danger text-success').addClass('text-info')
                        .html('<i class="feather-loader"></i><span>Detecting your location...</span>');
                    navigator.geolocation.getCurrentPosition(
                        (pos) => setEditMarker(pos.coords.latitude, pos.coords.longitude),
                        () => $('#editLocationStatus').removeClass('text-warning text-info text-success').addClass('text-danger')
                            .html('<i class="feather-alert-circle"></i><span>Unable to detect location</span>')
                    );
                } else {
                    alert('Geolocation is not supported by your browser');
                }
            });

            $('#editManualEntry').on('change', function() {
                editManualMode = $(this).is(':checked');
                if (editManualMode) {
                    $('#edit_latitude, #edit_longitude').prop('readonly', false);
                    $('#editDetectLocation').prop('disabled', true);
                    $('#editLocationStatus').removeClass('text-success text-warning text-danger').addClass('text-info')
                        .html('<i class="feather-edit"></i><span>Manual entry mode</span>');
                } else {
                    $('#edit_latitude, #edit_longitude').prop('readonly', true);
                    $('#editDetectLocation').prop('disabled', false);
                    if (editMarker) {
                        const ll = editMarker.getLatLng();
                        $('#edit_latitude').val(ll.lat.toFixed(6));
                        $('#edit_longitude').val(ll.lng.toFixed(6));
                    }
                    $('#editLocationStatus').removeClass('text-success text-info text-danger').addClass('text-info')
                        .html('<i class="feather-info"></i><span>Click on map to update the location</span>');
                }
            });

            // ==================== FORM SUBMISSIONS ====================
            $('#addLocationForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: $(this).serialize(),
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(response) {
                        if (response.success) {
                            $('#addLocationModal').modal('hide');
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = (xhr.responseJSON.errors || {});
                            $.each(errors, function(key, value) { $('.' + key + '_error').text(value[0]); });
                            toastr.error('Please fix the validation errors');
                        } else {
                            $('#addFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            $(document).on('click', '.edit-location', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const name = $(this).data('name');
                const description = $(this).data('description');
                const latitude = $(this).data('latitude');
                const longitude = $(this).data('longitude');
                const radius = $(this).data('radius');
                const geofenceEnabled = $(this).data('geofence-enabled');
                const status = $(this).data('status');

                $('#edit_id').val(id);
                $('#edit_name').val(name);
                $('#edit_description').val(description);
                $('#edit_status').val(status);
                $('#edit_radius').val(radius || 50);
                $('#edit_geofence_enabled').prop('checked', String(geofenceEnabled) === '1');

                if (latitude && longitude) {
                    $('#edit_latitude').val(latitude);
                    $('#edit_longitude').val(longitude);
                }

                setTimeout(() => {
                    initEditMap();
                    if (latitude && longitude) setEditMarker(parseFloat(latitude), parseFloat(longitude));
                }, 400);

                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
                $('#editLocationModal').modal('show');
            });

            $('#editLocationForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
                const id = $('#edit_id').val();

                $.ajax({
                    url: "{{ route('attendance-location.update', '') }}/" + id,
                    type: 'PUT',
                    data: $(this).serialize(),
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(response) {
                        if (response.success) {
                            $('#editLocationModal').modal('hide');
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = (xhr.responseJSON.errors || {});
                            $.each(errors, function(key, value) { $('.edit_' + key + '_error').text(value[0]); });
                            toastr.error('Please fix the validation errors');
                        } else {
                            $('#editFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // Delete
            let deleteLocationId = null;
            $(document).on('click', '.delete-location', function(e) {
                e.preventDefault();
                deleteLocationId = $(this).data('id');
                $('#deleteLocationName').text($(this).data('name'));
                $('#deleteFormError').addClass('d-none').text('');
                $('#deleteLocationModal').modal('show');
            });

            $('#confirmDeleteLocation').on('click', function() {
                if (!deleteLocationId) return;
                $.ajax({
                    url: "{{ route('attendance-location.delete', '') }}/" + deleteLocationId,
                    type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(response) {
                        if (response.success) {
                            $('#deleteLocationModal').modal('hide');
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        $('#deleteFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                    }
                });
            });

            $('#addLocationModal').on('shown.bs.modal', function() {
                initAddMap();
                setTimeout(() => addMap.invalidateSize(), 100);
            });

            $('#addLocationModal').on('hidden.bs.modal', function() {
                $('#addLocationForm')[0].reset();
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');
                if (addMarker) { addMap.removeLayer(addMarker); addMarker = null; }
                $('#addManualEntry').prop('checked', false).trigger('change');
            });

            $('#editLocationModal').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
                if (editMarker) { editMap.removeLayer(editMarker); editMarker = null; }
                $('#editManualEntry').prop('checked', false).trigger('change');
            });

            if (typeof toastr !== 'undefined') {
                toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 3000 };
            }
        });
    </script>
@endsection
