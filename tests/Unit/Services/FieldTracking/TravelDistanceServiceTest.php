<?php

namespace Tests\Unit\Services\FieldTracking;

use App\Services\FieldTracking\TravelDistanceService;
use PHPUnit\Framework\TestCase;

class TravelDistanceServiceTest extends TestCase
{
    public function test_route_km_sums_hops_along_the_route(): void
    {
        // 0.01° of latitude ≈ 1.112 km; out and back = ≈ 2.22 km.
        $km = (new TravelDistanceService)->routeKm([[26.9, 75.8], [26.91, 75.8], [26.9, 75.8]]);

        $this->assertEqualsWithDelta(2.22, $km, 0.01);
    }

    public function test_jitter_and_bad_fixes_are_ignored(): void
    {
        $km = (new TravelDistanceService)->routeKm([
            [26.9, 75.8],
            [26.90003, 75.8],  // ~3 m drift — not travel
            [0, 0],            // no GPS fix
            [null, 75.8],
            [26.9, 75.8],
        ]);

        $this->assertSame(0.0, $km);
    }

    public function test_no_points_is_zero(): void
    {
        $this->assertSame(0.0, (new TravelDistanceService)->routeKm([]));
    }
}
