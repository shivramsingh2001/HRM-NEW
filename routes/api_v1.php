<?php

use App\Http\Controllers\Api\V1\AnalyticsV1Controller;
use App\Http\Controllers\Api\V1\AttendanceV1Controller;
use App\Http\Controllers\Api\V1\BiometricFkWebController;
use App\Http\Controllers\Api\V1\BiometricV1Controller;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\RegularizationV1Controller;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API v1  (Tier 2)
|--------------------------------------------------------------------------
| Key-authenticated, envelope responses (App\Http\ApiResponse), idempotent
| writes. Mounted at /api/v1 by bootstrap/app.php. This surface is
| additive-only — breaking changes go to /api/v2.
*/

// Biometric terminals in "FkWeb" mode push punches straight here (plain HTTP,
// no headers we control). Outside `apiv1`/`apikey`: the device reads only the
// response_code header, and the per-device push_token in the path is the auth.
Route::post('/biometric/fkweb/{token}', [BiometricFkWebController::class, 'handle'])
    ->middleware('throttle:biometric-push')
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->name('biometric.fkweb');

Route::middleware('apiv1')->group(function () {
    // Unauthenticated liveness probe.
    Route::get('/ping', [PingController::class, 'ping'])->name('ping');

    Route::middleware(['apikey', 'throttle:api-public', 'idempotency'])->group(function () {
        Route::get('/attendance', [AttendanceV1Controller::class, 'index'])
            ->middleware('scope:attendance:read')->name('attendance.index');
        Route::get('/attendance/summary', [AttendanceV1Controller::class, 'summary'])
            ->middleware('scope:attendance:read')->name('attendance.summary');
        Route::post('/attendance/mark', [AttendanceV1Controller::class, 'mark'])
            ->middleware('scope:attendance:write')->name('attendance.mark');

        Route::get('/regularizations', [RegularizationV1Controller::class, 'index'])
            ->middleware('scope:regularization:read')->name('regularizations.index');
        Route::post('/regularizations/{id}/decision', [RegularizationV1Controller::class, 'decision'])
            ->middleware('scope:regularization:write')->name('regularizations.decision');

        Route::middleware('scope:analytics:read')->prefix('analytics')->name('analytics.')->group(function () {
            Route::get('/present-now', [AnalyticsV1Controller::class, 'presentNow'])->name('present-now');
            Route::get('/trends', [AnalyticsV1Controller::class, 'trends'])->name('trends');
            Route::get('/overtime-cost', [AnalyticsV1Controller::class, 'overtimeCost'])->name('overtime-cost');
            Route::get('/anomalies', [AnalyticsV1Controller::class, 'anomalies'])->name('anomalies');
        });

        // Biometric-terminal bridge (SBXPC). The Windows bridge service posts
        // punch batches here, keyed by an api_client with scope biometric:write.
        Route::middleware('scope:biometric:write')->prefix('biometric')->name('biometric.')->group(function () {
            Route::post('/punches', [BiometricV1Controller::class, 'punches'])->name('punches');
            Route::post('/devices/{serial}/heartbeat', [BiometricV1Controller::class, 'heartbeat'])->name('heartbeat');
            Route::post('/devices/{serial}/enrollments', [BiometricV1Controller::class, 'reportEnrollments'])->name('report-enrollments');
            Route::get('/devices/{serial}/enrollments', [BiometricV1Controller::class, 'enrollments'])
                ->middleware('scope:biometric:read')->name('enrollments');

            // Roster auto-provisioning: the bridge pulls the desired user set and
            // reports back what it wrote on the device.
            Route::get('/devices/{serial}/roster', [BiometricV1Controller::class, 'roster'])
                ->middleware('scope:biometric:read')->name('roster');
            Route::post('/devices/{serial}/roster/ack', [BiometricV1Controller::class, 'rosterAck'])->name('roster.ack');
        });
    });
});
