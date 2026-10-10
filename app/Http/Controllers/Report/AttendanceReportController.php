<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\SanitizesCsv;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceReportController extends Controller
{
    use SanitizesCsv;
    use \App\Http\Controllers\Concerns\FiltersReportEmployees;

    /**
     * Display reports index page
     */
    public function index(Request $request)
    {
        try {
            $tenantId = session('tenant_id');

            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            return view('client.report.index');
        } catch (Exception $e) {
            Log::error('Report Index Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to load reports: ' . $e->getMessage());
        }
    }

    
    

}
