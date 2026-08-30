<?php
// app/Http/Middleware/VerifyFingerprintAuthToken.php

namespace App\Http\Middleware;

use App\Models\FingerprintDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyFingerprintAuthToken
{
    public function handle(Request $request, Closure $next)
    {
        // Extract from Cams payload (body)
        $payload = $request->input('RealTime');
        $serial = $payload['SerialNumber'] ?? null;
        $token = $payload['AuthToken'] ?? null;

        // Also support header-based tokens (for flexibility)
        if (!$token) {
            $token = $request->header('X-Auth-Token') ?? $request->header('Authorization');
        }

        if (!$serial || !$token) {
            Log::warning('Fingerprint callback missing serial or token', [
                'ip' => $request->ip(),
                'has_serial' => !empty($serial),
                'has_token' => !empty($token),
            ]);
            return response()->json(['status' => 'done']);
        }

        // Find device by serial number
        $device = FingerprintDevice::where('serial_number', $serial)
            ->where('status', 1)
            ->first();

        // Validate token using hash_equals (timing-safe comparison)
        if (!$device || !hash_equals((string) $device->auth_token, (string) $token)) {
            Log::warning('Fingerprint auth failed', [
                'serial' => $serial,
                'device_exists' => (bool) $device,
                'ip' => $request->ip(),
            ]);
            return response()->json(['status' => 'done']);
        }

        // Update last seen
        $device->update(['last_seen_at' => now()]);

        // Attach device to request
        $request->attributes->set('fingerprint_device', $device);
        $request->attributes->set('fingerprint_tenant_id', $device->tenant_id);

        return $next($request);
    }
}