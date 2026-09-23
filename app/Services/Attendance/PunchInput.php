<?php

namespace App\Services\Attendance;

use Carbon\CarbonInterface;

/**
 * Everything AttendancePunchService::capture() needs to record one raw
 * Clock In/Out event, regardless of source (mobile GPS, web, manual/admin,
 * biometric, kiosk).
 */
final class PunchInput
{
    public function __construct(
        public readonly int $userId,
        public readonly int $tenantId,
        /** 'in' | 'out' */
        public readonly string $direction,
        public readonly CarbonInterface $punchedAt,
        /** 'mobile_app' | 'web' | 'manual' | 'biometric' | 'kiosk' | 'api' */
        public readonly string $source,
        public readonly ?string $method = null,
        public readonly ?float $lat = null,
        public readonly ?float $long = null,
        public readonly ?string $address = null,
        public readonly ?string $locationVerification = null,
        public readonly ?int $attendanceLocationId = null,
        public readonly ?float $distanceMeters = null,
        public readonly ?float $accuracyMeters = null,
        public readonly ?string $deviceId = null,
        public readonly ?string $networkType = null,
        public readonly ?string $wifiSsid = null,
        public readonly ?string $ipAddress = null,
        public readonly ?int $batteryPercent = null,
        public readonly ?int $biometricDeviceId = null,
        public readonly ?string $clientRef = null,
        public readonly ?AuditContext $audit = null,
        public readonly array $metadata = [],
    ) {
    }
}
