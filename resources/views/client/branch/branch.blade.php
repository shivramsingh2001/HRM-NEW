{{-- resources/views/client/branch/index.blade.php --}}
@extends('client.layout.master')

@section('style')
<style>
    /* Map Container Styles */
    #map {
        height: 300px;
        width: 100%;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        margin-bottom: 15px;
    }

    .location-status {
        font-size: 12px;
        margin-top: 5px;
    }

    .location-status i {
        font-size: 14px;
    }

    .location-status.text-success i {
        color: #28a745;
    }

    .location-status.text-warning i {
        color: #ffc107;
    }

    .location-status.text-danger i {
        color: #dc3545;
    }

    /* Coordinates Input Group */
    .coordinate-input-group {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .coordinate-input-group .form-control {
        flex: 1;
    }

    .coordinate-input-group .btn {
        white-space: nowrap;
    }

    /* Modal Styles */
    .modal-header {
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }

    .modal-title {
        font-size: 18px;
        font-weight: 600;
        color: #495057;
    }

    .modal-title i {
        color: #4b7bec;
        margin-right: 8px;
    }

    /* Form Styles */
    .form-group {
        margin-bottom: 1rem;
    }

    .form-label {
        font-weight: 500;
        color: #495057;
        margin-bottom: 0.5rem;
    }

    .form-label i {
        color: #6c757d;
        margin-right: 5px;
        font-size: 14px;
    }

    .form-label .required {
        color: #dc3545;
        margin-left: 3px;
    }

    .form-control, .form-select {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 0.5rem 0.75rem;
        font-size: 14px;
        transition: all 0.2s;
    }

    .form-control:focus, .form-select:focus {
        border-color: #4b7bec;
        box-shadow: 0 0 0 0.2rem rgba(75, 123, 236, 0.1);
        outline: none;
    }

    .form-control.is-invalid, .form-select.is-invalid {
        border-color: #dc3545;
    }

    .invalid-feedback {
        color: #dc3545;
        font-size: 12px;
        margin-top: 4px;
    }

   

    /* Table Styles */
    .table th {
        background-color: #f8f9fa;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        border-bottom-width: 1px;
    }

    .table td {
        vertical-align: middle;
        font-size: 14px;
    }

    .badge {
        padding: 6px 10px;
        font-weight: 500;
        font-size: 11px;
        border-radius: 20px;
    }

    .badge.bg-success {
        background-color: #28a745 !important;
    }

    .badge.bg-danger {
        background-color: #dc3545 !important;
    }

    .badge.bg-warning {
        background-color: #ffc107 !important;
        color: #212529;
    }

    .badge i {
        font-size: 11px;
        margin-right: 4px;
    }

    /* Action Buttons */
    .action-btn {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s;
        color: #6c757d;
    }

    .action-btn:hover {
        background: #e9ecef;
        color: #4b7bec;
    }

    .dropdown-menu {
        border: 1px solid #e9ecef;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        padding: 8px 0;
    }

    .dropdown-item {
        padding: 8px 16px;
        font-size: 13px;
        display: flex;
        align-items: center;
    }

    .dropdown-item i {
        margin-right: 10px;
        font-size: 14px;
        color: #6c757d;
    }

    .dropdown-item:hover {
        background-color: #f8f9fa;
    }

    .dropdown-item.text-danger i {
        color: #dc3545;
    }

    /* Stats Cards */
    .stats-card {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 20px;
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        transition: all 0.2s;
    }

    .card.stat-card-compact {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease;
            height: 100%;
            margin-bottom: 0;
        }

        .card.stat-card-compact:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12);
        }

        .card.stat-card-compact .card-body {
            padding: 0.75rem 1rem;
        }

        .stat-content-compact {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-icon-compact {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .stat-text-compact {
            text-align: right;
        }

        .stat-title-compact {
            font-size: 11px;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value-compact {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
            line-height: 1;
        }

    /* Custom colors for compact cards */
        .card-total .stat-icon-compact { background-color: #e0f2fe; color: #0369a1; }
        .card-total .stat-value-compact { color: #0369a1; }

        .card-pending .stat-icon-compact { background-color: #f3f4f6; color: #374151; }
        .card-pending .stat-value-compact { color: #374151; }

        .card-in_progress .stat-icon-compact { background-color: #dbeafe; color: #1e40af; }
        .card-in_progress .stat-value-compact { color: #1e40af; }

</style>
@endsection

@section('content-area')
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">Branch Management</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Branch</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                <div class="dropdown d-none d-sm-flex">
                    <a href="#" class="btn btn-light-brand btn-sm rounded-pill" data-bs-toggle="modal"
                        data-bs-target="#addBranchModal">
                        <i class="feather-plus me-2"></i>Add Branch
                    </a>
                </div>
            </div>
        </div>
    </div>
  <div class="main-content" style="padding: 20px !important;">
    
        <!-- Stats Cards -->
        <div class="row mb-3">
            <div class="col-xxl col-lg col-md-3 col-sm-6">
                <div class="card stat-card-compact card-total">
                    <div class="card-body">
                        <div class="stat-content-compact">
                            <div class="stat-icon-compact">
                                <i class="feather-list"></i>
                            </div>
                            <div class="stat-text-compact">
                                <div class="stat-title-compact">Total</div>
                                <div class="stat-value-compact">{{ $branches->total() ?? 0 }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl col-lg col-md-3 col-sm-6">
                <div class="card stat-card-compact card-pending">
                    <div class="card-body">
                        <div class="stat-content-compact">
                            <div class="stat-icon-compact">
                                <i class="feather-clock"></i>
                            </div>
                            <div class="stat-text-compact">
                                <div class="stat-title-compact">Active</div>
                                <div class="stat-value-compact">{{ $branches->where('status', 1)->count() ?? 0 }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl col-lg col-md-3 col-sm-6">
                <div class="card stat-card-compact card-in_progress">
                    <div class="card-body">
                        <div class="stat-content-compact">
                            <div class="stat-icon-compact">
                                <i class="feather-activity"></i>
                            </div>
                            <div class="stat-text-compact">
                                <div class="stat-title-compact">Inactive</div>
                                <div class="stat-value-compact">{{ $branches->where('status', 0)->count() ?? 0 }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
           
        </div>

        <!-- Branches Table -->
        <div class="row note-has-grid py-2">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Branches List</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info">
                                <i class="feather-list me-1"></i>Total: {{ $branches->total() }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover" id="branchList">
                                <thead>
                                    <tr class="text-center">
                                        <th width="50">#</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Latitude</th>
                                        <th>Longitude</th>
                                        <!--<th>Location Status</th>-->
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($branches as $branch)
                                        <tr class="text-center">
                                            <td class="text-center">{{ $loop->iteration + ($branches->currentPage() - 1) * $branches->perPage() }}</td>
                                            <td>
                                                <strong>{{ $branch->name }}</strong>
                                            </td>
                                            <td>{{ Str::limit($branch->description, 30) ?? '-' }}</td>
                                            <td>
                                                @if($branch->latitude)
                                                    <span class="badge bg-light text-dark">{{ number_format($branch->latitude, 6) }}</span>
                                                @else
                                                    <span class="badge bg-warning">Not Set</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($branch->longitude)
                                                    <span class="badge bg-light text-dark">{{ number_format($branch->longitude, 6) }}</span>
                                                @else
                                                    <span class="badge bg-warning">Not Set</span>
                                                @endif
                                            </td>
                                            <!--<td>-->
                                            <!--    @if($branch->latitude && $branch->longitude)-->
                                            <!--        <span class="badge bg-success">-->
                                            <!--            <i class="feather-map-pin me-1"></i>Located-->
                                            <!--        </span>-->
                                            <!--    @else-->
                                            <!--        <span class="badge bg-warning">-->
                                            <!--            <i class="feather-alert-circle me-1"></i>No Location-->
                                            <!--        </span>-->
                                            <!--    @endif-->
                                            <!--</td>-->
                                            <td>
                                                @if ($branch->status == 1)
                                                    <span class="badge bg-success">
                                                        <i class="feather-check me-1"></i>Active
                                                    </span>
                                                @else
                                                    <span class="badge bg-danger">
                                                        <i class="feather-x me-1"></i>Inactive
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="dropdown">
                                                    <a href="#" class="action-btn" data-bs-toggle="dropdown" data-bs-offset="0,10">
                                                        <i class="feather-more-vertical"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item edit-branch" href="#" 
                                                               data-id="{{ $branch->id }}"
                                                               data-name="{{ $branch->name }}"
                                                               data-description="{{ $branch->description }}"
                                                               data-latitude="{{ $branch->latitude }}"
                                                               data-longitude="{{ $branch->longitude }}"
                                                               data-status="{{ $branch->status }}">
                                                                <i class="feather-edit-3"></i>
                                                                <span>Edit Branch</span>
                                                            </a>
                                                        </li>
                                                        
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <i class="feather-home" style="font-size: 48px; color: #dee2e6;"></i>
                                                <p class="mt-3 text-muted">No branches found</p>
                                               
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if ($branches->hasPages())
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    Showing {{ $branches->firstItem() }} to {{ $branches->lastItem() }} of {{ $branches->total() }} entries
                                </div>
                                <div>
                                    {{ $branches->appends(request()->query())->links() }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Branch Modal -->
    <div class="modal fade-scale" id="addBranchModal" tabindex="-1" aria-labelledby="addBranchModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="feather-home"></i>
                        Add New Branch
                    </h5>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <div id="addFormError" class="alert alert-danger d-none"></div>
                            
                            <form action="{{ route('branch.store') }}" method="POST" id="addBranchForm">
                                @csrf
                                <div class="row">
                                    <!-- Name Field -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="add_name">
                                                <i class="feather-home"></i>
                                                Branch Name <span class="required">*</span>
                                            </label>
                                            <input type="text" class="form-control" name="name" id="add_name" 
                                                   placeholder="Enter branch name" required>
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>

                                    <!-- Status Field -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="add_status">
                                                <i class="feather-toggle-right"></i>
                                                Status <span class="required">*</span>
                                            </label>
                                            <select class="form-control" name="status" id="add_status" required>
                                                <option value="1" selected>Active</option>
                                                <option value="0">Inactive</option>
                                            </select>
                                            <small class="text-danger error-text status_error"></small>
                                        </div>
                                    </div>

                                    <!-- Description Field -->
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label" for="add_description">
                                                <i class="feather-file-text"></i>
                                                Description
                                            </label>
                                            <textarea class="form-control" name="description" id="add_description" 
                                                      rows="3" placeholder="Enter branch description..."></textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>

                                    <!-- Map Section -->
                                    <div class="col-12 mb-3">
                                        <label class="form-label">
                                            <i class="feather-map-pin"></i>
                                            Location Selection
                                        </label>
                                        <div id="addMap" style="height: 200px; width: 100%; border-radius: 8px; border: 1px solid #dee2e6;"></div>
                                        
                                        <!-- Location Status -->
                                        <div id="addLocationStatus" class="location-status text-warning mt-2">
                                            <i class="feather-alert-circle"></i>
                                            <span>Click on map to set branch location</span>
                                        </div>
                                    </div>

                                    <!-- Latitude Field -->
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="form-label" for="add_latitude">
                                                <i class="feather-globe"></i>
                                                Latitude
                                            </label>
                                            <div class="coordinate-input-group">
                                                <input type="number" step="any" class="form-control" 
                                                       name="latitude" id="add_latitude" 
                                                       placeholder="Click on map or enter manually" readonly>
                                                <button type="button" class="btn btn-outline-secondary" id="addDetectLocation">
                                                    <i class="feather-navigation"></i> Detect
                                                </button>
                                            </div>
                                            <small class="text-danger error-text latitude_error"></small>
                                        </div>
                                    </div>

                                    <!-- Longitude Field -->
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="form-label" for="add_longitude">
                                                <i class="feather-globe"></i>
                                                Longitude
                                            </label>
                                            <input type="number" step="any" class="form-control" 
                                                   name="longitude" id="add_longitude" 
                                                   placeholder="Click on map or enter manually" readonly>
                                            <small class="text-danger error-text longitude_error"></small>
                                        </div>
                                    </div>

                                    <!-- Manual Entry Checkbox -->
                                    <div class="col-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="addManualEntry">
                                            <label class="form-check-label" for="addManualEntry">
                                                Enter coordinates manually
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-2">
                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="feather-save me-2"></i>Save Branch
                                        </button>
                                    </div>
                                  
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Branch Modal -->
    <div class="modal fade-scale" id="editBranchModal" tabindex="-1" aria-labelledby="editBranchModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="feather-edit-3"></i>
                        Edit Branch
                    </h5>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <div id="editFormError" class="alert alert-danger d-none"></div>
                            
                            <form id="editBranchForm">
                                @csrf
                                <input type="hidden" name="id" id="edit_id">
                                
                                <div class="row">
                                    <!-- Name Field -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="edit_name">
                                                <i class="feather-home"></i>
                                                Branch Name <span class="required">*</span>
                                            </label>
                                            <input type="text" class="form-control" name="name" id="edit_name" 
                                                   placeholder="Enter branch name" required>
                                            <small class="text-danger error-text edit_name_error"></small>
                                        </div>
                                    </div>

                                    <!-- Status Field -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="edit_status">
                                                <i class="feather-toggle-right"></i>
                                                Status <span class="required">*</span>
                                            </label>
                                            <select class="form-control" name="status" id="edit_status" required>
                                                <option value="1">Active</option>
                                                <option value="0">Inactive</option>
                                            </select>
                                            <small class="text-danger error-text edit_status_error"></small>
                                        </div>
                                    </div>

                                    <!-- Description Field -->
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label" for="edit_description">
                                                <i class="feather-file-text"></i>
                                                Description
                                            </label>
                                            <textarea class="form-control" name="description" id="edit_description" 
                                                      rows="3" placeholder="Enter branch description..."></textarea>
                                            <small class="text-danger error-text edit_description_error"></small>
                                        </div>
                                    </div>

                                    <!-- Map Section -->
                                    <div class="col-12 mb-3">
                                        <label class="form-label">
                                            <i class="feather-map-pin"></i>
                                            Location Selection
                                        </label>
                                        <div id="editMap" style="height: 200px; width: 100%; border-radius: 8px; border: 1px solid #dee2e6;"></div>
                                        
                                        <!-- Location Status -->
                                        <div id="editLocationStatus" class="location-status text-info mt-2">
                                            <i class="feather-info"></i>
                                            <span>Click on map to update branch location</span>
                                        </div>
                                    </div>

                                    <!-- Latitude Field -->
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="form-label" for="edit_latitude">
                                                <i class="feather-globe"></i>
                                                Latitude
                                            </label>
                                            <div class="coordinate-input-group">
                                                <input type="number" step="any" class="form-control" 
                                                       name="latitude" id="edit_latitude" 
                                                       placeholder="Click on map or enter manually" readonly>
                                                <button type="button" class="btn btn-outline-secondary" id="editDetectLocation">
                                                    <i class="feather-navigation"></i> Detect
                                                </button>
                                            </div>
                                            <small class="text-danger error-text edit_latitude_error"></small>
                                        </div>
                                    </div>

                                    <!-- Longitude Field -->
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="form-label" for="edit_longitude">
                                                <i class="feather-globe"></i>
                                                Longitude
                                            </label>
                                            <input type="number" step="any" class="form-control" 
                                                   name="longitude" id="edit_longitude" 
                                                   placeholder="Click on map or enter manually" readonly>
                                            <small class="text-danger error-text edit_longitude_error"></small>
                                        </div>
                                    </div>

                                    <!-- Manual Entry Checkbox -->
                                    <div class="col-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="editManualEntry">
                                            <label class="form-check-label" for="editManualEntry">
                                                Edit coordinates manually
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="feather-save me-2"></i>Update Branch
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

   
@endsection

@section('script-area')
    <!-- Leaflet CSS and JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        $(document).ready(function() {
            // ==================== ADD BRANCH MAP ====================
            let addMap, addMarker, addManualMode = false;
            
            // Initialize Add Map
            function initAddMap() {
                if (!addMap) {
                    addMap = L.map('addMap').setView([28.6139, 77.2090], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(addMap);

                    // Click on map to set marker
                    addMap.on('click', function(e) {
                        if (!addManualMode) {
                            const lat = e.latlng.lat;
                            const lng = e.latlng.lng;
                            setAddMarker(lat, lng);
                        }
                    });
                }
            }

            // Set marker on add map
            function setAddMarker(lat, lng) {
                if (addMarker) {
                    addMap.removeLayer(addMarker);
                }
                addMarker = L.marker([lat, lng], {
                    draggable: true
                }).addTo(addMap);
                
                addMarker.on('dragend', function(e) {
                    const pos = e.target.getLatLng();
                    updateAddCoordinates(pos.lat, pos.lng);
                });

                updateAddCoordinates(lat, lng);
                addMap.setView([lat, lng], 15);
            }

            // Update add coordinates
            function updateAddCoordinates(lat, lng) {
                $('#add_latitude').val(lat.toFixed(6));
                $('#add_longitude').val(lng.toFixed(6));
                $('#addLocationStatus')
                    .removeClass('text-warning text-danger text-success')
                    .addClass('text-success')
                    .html('<i class="feather-check-circle"></i><span>Location set successfully</span>');
            }

            // Detect current location for add
            $('#addDetectLocation').on('click', function() {
                if (navigator.geolocation) {
                    $('#addLocationStatus')
                        .removeClass('text-warning text-danger text-success')
                        .addClass('text-info')
                        .html('<i class="feather-loader spin"></i><span>Detecting your location...</span>');
                    
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            setAddMarker(position.coords.latitude, position.coords.longitude);
                        },
                        function(error) {
                            $('#addLocationStatus')
                                .removeClass('text-warning text-info text-success')
                                .addClass('text-danger')
                                .html('<i class="feather-alert-circle"></i><span>Unable to detect location</span>');
                        }
                    );
                } else {
                    alert('Geolocation is not supported by your browser');
                }
            });

            // Manual entry toggle for add
            $('#addManualEntry').on('change', function() {
                addManualMode = $(this).is(':checked');
                if (addManualMode) {
                    $('#add_latitude').prop('readonly', false);
                    $('#add_longitude').prop('readonly', false);
                    $('#addDetectLocation').prop('disabled', true);
                    $('#addLocationStatus')
                        .removeClass('text-success text-warning text-danger')
                        .addClass('text-info')
                        .html('<i class="feather-edit"></i><span>Manual entry mode - Enter coordinates</span>');
                } else {
                    $('#add_latitude').prop('readonly', true).val('');
                    $('#add_longitude').prop('readonly', true).val('');
                    $('#addDetectLocation').prop('disabled', false);
                    if (addMarker) {
                        addMap.removeLayer(addMarker);
                        addMarker = null;
                    }
                    $('#addLocationStatus')
                        .removeClass('text-success text-info text-danger')
                        .addClass('text-warning')
                        .html('<i class="feather-alert-circle"></i><span>Click on map to set branch location</span>');
                }
            });

            // ==================== EDIT BRANCH MAP ====================
            let editMap, editMarker, editManualMode = false;
            let currentEditId = null;

            // Initialize Edit Map
            function initEditMap() {
                if (!editMap) {
                    editMap = L.map('editMap').setView([28.6139, 77.2090], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(editMap);

                    // Click on map to set marker
                    editMap.on('click', function(e) {
                        if (!editManualMode) {
                            const lat = e.latlng.lat;
                            const lng = e.latlng.lng;
                            setEditMarker(lat, lng);
                        }
                    });
                }
            }

            // Set marker on edit map
            function setEditMarker(lat, lng) {
                if (editMarker) {
                    editMap.removeLayer(editMarker);
                }
                editMarker = L.marker([lat, lng], {
                    draggable: true
                }).addTo(editMap);
                
                editMarker.on('dragend', function(e) {
                    const pos = e.target.getLatLng();
                    updateEditCoordinates(pos.lat, pos.lng);
                });

                updateEditCoordinates(lat, lng);
                editMap.setView([lat, lng], 15);
            }

            // Update edit coordinates
            function updateEditCoordinates(lat, lng) {
                $('#edit_latitude').val(lat.toFixed(6));
                $('#edit_longitude').val(lng.toFixed(6));
                $('#editLocationStatus')
                    .removeClass('text-warning text-danger text-success')
                    .addClass('text-success')
                    .html('<i class="feather-check-circle"></i><span>Location updated successfully</span>');
            }

            // Detect current location for edit
            $('#editDetectLocation').on('click', function() {
                if (navigator.geolocation) {
                    $('#editLocationStatus')
                        .removeClass('text-warning text-danger text-success')
                        .addClass('text-info')
                        .html('<i class="feather-loader spin"></i><span>Detecting your location...</span>');
                    
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            setEditMarker(position.coords.latitude, position.coords.longitude);
                        },
                        function(error) {
                            $('#editLocationStatus')
                                .removeClass('text-warning text-info text-success')
                                .addClass('text-danger')
                                .html('<i class="feather-alert-circle"></i><span>Unable to detect location</span>');
                        }
                    );
                } else {
                    alert('Geolocation is not supported by your browser');
                }
            });

            // Manual entry toggle for edit
            $('#editManualEntry').on('change', function() {
                editManualMode = $(this).is(':checked');
                if (editManualMode) {
                    $('#edit_latitude').prop('readonly', false);
                    $('#edit_longitude').prop('readonly', false);
                    $('#editDetectLocation').prop('disabled', true);
                    $('#editLocationStatus')
                        .removeClass('text-success text-warning text-danger')
                        .addClass('text-info')
                        .html('<i class="feather-edit"></i><span>Manual entry mode - Edit coordinates</span>');
                } else {
                    $('#edit_latitude').prop('readonly', true);
                    $('#edit_longitude').prop('readonly', true);
                    $('#editDetectLocation').prop('disabled', false);
                    if (editMarker) {
                        const latLng = editMarker.getLatLng();
                        $('#edit_latitude').val(latLng.lat.toFixed(6));
                        $('#edit_longitude').val(latLng.lng.toFixed(6));
                    }
                    $('#editLocationStatus')
                        .removeClass('text-success text-info text-danger')
                        .addClass('text-info')
                        .html('<i class="feather-info"></i><span>Click on map to update branch location</span>');
                }
            });

            // ==================== FORM SUBMISSIONS ====================

            // Add Branch Form Submission
            $('#addBranchForm').on('submit', function(e) {
                e.preventDefault();
                
                // Reset errors
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#addBranchModal').modal('hide');
                            location.reload();
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.' + key + '_error').text(value[0]);
                            });
                        } else {
                            $('#addFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });
            });

            // Edit Branch - Open Modal with Data
            $(document).on('click', '.edit-branch', function(e) {
                e.preventDefault();
                
                // Get data
                const id = $(this).data('id');
                const name = $(this).data('name');
                const description = $(this).data('description');
                const latitude = $(this).data('latitude');
                const longitude = $(this).data('longitude');
                const status = $(this).data('status');

                // Set form values
                $('#edit_id').val(id);
                $('#edit_name').val(name);
                $('#edit_description').val(description);
                $('#edit_status').val(status);
                
                // Set coordinates
                if (latitude && longitude) {
                    $('#edit_latitude').val(latitude);
                    $('#edit_longitude').val(longitude);
                }

                // Initialize map if not already done
                setTimeout(() => {
                    initEditMap();
                    
                    // Set marker if coordinates exist
                    if (latitude && longitude) {
                        setEditMarker(parseFloat(latitude), parseFloat(longitude));
                    }
                }, 500);

                // Clear previous errors
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                // Show modal
                $('#editBranchModal').modal('show');
            });

            // Edit Branch Form Submission
            $('#editBranchForm').on('submit', function(e) {
                e.preventDefault();
                
                // Reset errors
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();
                
                $.ajax({
                    url: "{{ route('branch.update', '') }}/" + id,
                    type: "PUT",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#editBranchModal').modal('hide');
                            location.reload();
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.edit_' + key + '_error').text(value[0]);
                            });
                        } else {
                            $('#editFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });
            });

            // Delete Branch
            $(document).on('click', '.delete-branch', function(e) {
                e.preventDefault();
                
                const id = $(this).data('id');
                const name = $(this).data('name');
                
                $('#deleteBranchName').text(name);
                $('#deleteBranchForm').attr('action', "{{ route('branch.delete', '') }}/" + id);
                $('#deleteBranchModal').modal('show');
            });

            // Initialize add map when modal opens
            $('#addBranchModal').on('shown.bs.modal', function() {
                initAddMap();
                setTimeout(() => {
                    addMap.invalidateSize();
                }, 100);
            });

            // Clear form when modal is closed
            $('#addBranchModal').on('hidden.bs.modal', function() {
                $('#addBranchForm')[0].reset();
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');
                if (addMarker) {
                    addMap.removeLayer(addMarker);
                    addMarker = null;
                }
                $('#addManualEntry').prop('checked', false).trigger('change');
            });

            $('#editBranchModal').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
                if (editMarker) {
                    editMap.removeLayer(editMarker);
                    editMarker = null;
                }
                $('#editManualEntry').prop('checked', false).trigger('change');
            });
        });
    </script>
@endsection