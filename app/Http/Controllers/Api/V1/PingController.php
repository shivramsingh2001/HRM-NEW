<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;

class PingController extends Controller
{
    public function ping()
    {
        return ApiResponse::ok([
            'status' => 'ok',
            'service' => 'attendance-api',
            'version' => 'v1',
            'time' => now()->toIso8601String(),
        ]);
    }
}
