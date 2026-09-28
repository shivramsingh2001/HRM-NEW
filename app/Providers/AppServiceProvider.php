<?php

namespace App\Providers;

use App\Models\Task;
use App\Models\User;
use App\Observers\BiometricRosterObserver;
use App\Observers\DatabaseNotificationObserver;
use App\Observers\TaskProgressObserver;
use App\Observers\UserRoleObserver;
use App\Services\FeatureService;
use App\Services\RbacService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Auto-provision employees onto biometric terminals on lifecycle changes.
        User::observe(BiometricRosterObserver::class);

        // RBAC transition (Phase 5.4): keep users.role_id in step with the varchar role.
        User::observe(UserRoleObserver::class);

        // Keep projects.progress_percentage in sync as tasks change.
        Task::observe(TaskProgressObserver::class);

        // Broadcast module: propagate a mobile-driven notifications.read_at
        // change back onto broadcast_recipients.read_at.
        DatabaseNotification::observe(DatabaseNotificationObserver::class);

        // @feature('payroll') ... @endfeature — platform feature gating in views.
        Blade::if('feature', fn (string $key) => app(FeatureService::class)->enabledForCurrentTenant($key));

        // @permission('payroll', 'view') ... @endpermission — tenant RBAC (not yet enforced).
        Blade::if('permission', fn (string $module, string $action) => app(RbacService::class)->currentCan($module, $action));

        // Tier 2 / T2-B — per-client public-API rate limit.
        RateLimiter::for('api-public', function ($request) {
            $client = app()->bound('current_api_client') ? app('current_api_client') : null;
            $perMin = $client->rate_limit_per_min ?? 60;
            $key = $client ? 'apiclient:' . $client->id : 'apiip:' . $request->ip();

            return Limit::perMinute($perMin)->by($key);
        });

        // GPS ingest safety cap — set well above the real cadence; catches
        // runaway clients only, never legitimate traffic.
        RateLimiter::for('location-ingest', function ($request) {
            $uid = optional($request->user())->id ?: $request->ip();

            return Limit::perMinute((int) config('location.throttle_per_minute', 40))
                ->by('loc:' . $uid)
                ->response(fn () => response()->json([
                    'status' => false,
                    'message' => 'Too many location updates. Slow down.',
                    'tracking_enabled' => true,
                    'next_ping_seconds' => (int) config('location.default_ping_seconds', 60),
                ], 429));
        });

        $authThrottleResponse = fn () => response()->json([
            'success' => false,
            'message' => 'Too many attempts. Please wait a moment and try again.',
        ], 429);

        // /login and /web login — keyed by the submitted identifier AND ip
        // (not ip alone), so an attacker can't dodge the limit by rotating
        // ip while hammering one account, and one ip can't lock a real user
        // out by spamming their identifier from elsewhere. A slower daily
        // per-ip ceiling catches distributed low-and-slow attempts too.
        RateLimiter::for('login', function ($request) use ($authThrottleResponse) {
            $identifier = $request->input('employee_id') ?? $request->input('email') ?? 'unknown';

            return [
                Limit::perMinute(5)->by('login:' . $identifier . '|' . $request->ip())->response($authThrottleResponse),
                Limit::perDay(100)->by('login-ip:' . $request->ip())->response($authThrottleResponse),
            ];
        });

        // /send-otp — SMS costs money per send, so this is a cost/DoS
        // control as much as a security one.
        RateLimiter::for('otp-request', function ($request) use ($authThrottleResponse) {
            $mobile = $request->input('mobile_no') ?? $request->ip();

            return [
                Limit::perMinute(3)->by('otp-req:' . $mobile)->response($authThrottleResponse),
                Limit::perDay(10)->by('otp-req-day:' . $mobile)->response($authThrottleResponse),
            ];
        });

        // /login-otp — a 6-digit OTP is only 1,000,000 combinations; without
        // this, it's brute-forceable well within its expiry window.
        RateLimiter::for('otp-verify', function ($request) use ($authThrottleResponse) {
            $mobile = $request->input('mobile_no') ?? $request->ip();

            return Limit::perMinute(5)->by('otp-verify:' . $mobile)->response($authThrottleResponse);
        });
    }
}
