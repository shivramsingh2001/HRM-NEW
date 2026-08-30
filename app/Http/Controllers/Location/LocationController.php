<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function getStates(Request $request)
    {
        $countryCode = $request->get('country_code');

        $states = DB::table('states')->where('country_code', $countryCode)
            ->where('status', 1)
            ->orderBy('name', 'asc')
            ->get(['state_code', 'name', 'short_name']);

        return response()->json($states);
    }

    /**
     * Get cities by state code
     */
    public function getCities(Request $request)
    {
        $stateCode = $request->get('state_code');

        $cities = DB::table('cities')->where('state_code', $stateCode)
            ->where('status', 1)
            ->orderBy('name', 'asc')
            ->get(['city_code', 'name', 'short_name']);

        return response()->json($cities);
    }
}
