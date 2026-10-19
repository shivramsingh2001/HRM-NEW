<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Holiday\HolidayController;
use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * GET /api/holiday returns the holiday's own day as "Y-m-d" (as the mobile app
 * has always received it). It used to come out as a UTC timestamp — a 21 Oct
 * holiday read "2026-10-20T18:30:00.000000Z" and the app showed it on 20 Oct.
 */
class HolidayApiDateTest extends TestCase
{
    private ?Holiday $holiday = null;

    protected function tearDown(): void
    {
        $this->holiday?->forceDelete();
        Auth::logout();
        parent::tearDown();
    }

    public function test_holiday_dates_are_plain_days_in_the_api(): void
    {
        $user = User::withoutGlobalScopes()->where('status', 1)->whereNotNull('tenant_id')->where('role', '!=', 'admin')->orderBy('id')->firstOrFail();
        $day = Carbon::create(now()->year, 12, 30)->toDateString();
        $this->holiday = Holiday::withoutGlobalScopes()->create(['tenant_id' => $user->tenant_id, 'name' => 'PHPUnit Holiday ' . uniqid(),
            'start_date' => $day, 'end_date' => $day, 'status' => 1]);

        app()->instance('current_tenant', \App\Models\Tenant::find($user->tenant_id));
        Auth::login($user);
        $data = collect(app(HolidayController::class)->index(Request::create('/'))->getData(true)['data'])->firstWhere('id', $this->holiday->id);

        $this->assertSame($day, $data['start_date']);
        $this->assertSame($day, $data['end_date']);
        $this->assertSame(['id', 'start_date', 'end_date', 'name', 'description'], array_keys($data), 'same keys as before');
        $this->assertInstanceOf(Carbon::class, $this->holiday->fresh()->start_date, 'code still gets a date');
    }
}
