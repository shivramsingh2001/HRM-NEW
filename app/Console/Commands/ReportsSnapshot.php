<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Golden-master snapshot of the attendance reports and the Team pages
 * (code-quality plan, Phases 0 / 3 — the safety net for splitting
 * AttendanceReportController and TeamController). For one company and month
 * it opens, as the company admin and as a manager:
 *  - every attendance report CSV export (body, byte for byte);
 *  - every attendance report page and the Team pages (view name + all view
 *    data, normalised to JSON);
 *  - the Team JSON endpoints (member calendar / table / stats, quick summary,
 *    a user's shift, the attendance log).
 * Read-only (everything runs in a rolled-back transaction); plan-feature
 * middleware is skipped on purpose so every report is captured. Encrypted ids
 * (random per call) are masked.
 *
 *   php artisan reports:snapshot --label=before
 *   … refactor …
 *   php artisan reports:snapshot --label=after --compare=before
 */
class ReportsSnapshot extends Command
{
    protected $signature = 'reports:snapshot
        {--label=snapshot : File name under storage/app/report-snapshots}
        {--tenant=7 : Company id}
        {--month= : Month Y-m (default: last month)}
        {--compare= : Instead of taking a snapshot, compare --label against this earlier label}';

    protected $description = 'Read-only golden-master snapshot of the attendance reports + Team pages';

    /** route name => extra query / route params (the shared month / date range are added to every call) */
    private const EXPORTS = [
        'report.attendance.overall.export' => [],
        'report.attendance.day.export' => [],
        'report.attendance.hourly.export' => [],
        'report.attendance.detail.export' => [],
        'report.attendance.detailed.export' => ['employee_id' => 'first'],
        'report.attendance.monthly.summary.export' => [],
        'report.attendance.summary.export' => [],
        'report.attendance.branch-wise.detail.export' => ['branchId' => 'first_location'],
        'report.attendance.punches.export' => [],
        'report.attendance.shift-monthly.export' => [],
        'report.attendance.shift-changes.export' => [],
        'report.attendance.shift-requests.export' => [],
    ];

    private const PAGES = [
        'report.attendance.index' => [],
        'report.attendance.detail.index' => [],
        'report.attendance.day.index' => [],
        'report.attendance.hourly.index' => [],
        'report.attendance.overall.index' => [],
        'report.attendance.detailed.index' => ['employee_id' => 'first'],
        'report.attendance.monthly.summary.index' => [],
        'report.attendance.summary.index' => [],
        'report.attendance.branch-wise' => [],
        'report.attendance.branch-wise.detail' => ['branchId' => 'first_location'],
        'report.overtime.monthly.index' => [],
        'team.index' => [],
        'team.member-detail' => ['id' => 'first_encrypted'],
        'team.user.attendance.calendar' => ['id' => 'first_encrypted'],
        'team.user.attendance.table' => ['id' => 'first_encrypted'],
        'team.user.attendance.stats' => ['id' => 'first_encrypted'],
        'team.attendance-summary.quick' => [],
        'team.get-user-shift' => ['user_id' => 'first', '_method' => 'POST'],
        'team.attendance-log' => ['user_id' => 'first'],
    ];

    public function handle(): int
    {
        $dir = storage_path('app/report-snapshots');
        if ($this->option('compare')) {
            return $this->compare($dir, (string) $this->option('compare'), (string) $this->option('label'));
        }

        $tenant = Tenant::withoutGlobalScopes()->find((int) $this->option('tenant'));
        $admin = $tenant ? User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', 'admin')->where('status', 1)->orderBy('id')->first() : null;
        if (! $admin) {
            $this->error('Company or its admin not found.');

            return self::FAILURE;
        }
        $manager = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', 'manager')->where('status', 1)->orderBy('id')->first();

        $month = $this->option('month') ?: now()->subMonthNoOverflow()->format('Y-m');
        $start = $month.'-01';
        $end = \Carbon\Carbon::parse($start)->endOfMonth()->toDateString();
        $shared = ['month' => $month, 'start_date' => $start, 'end_date' => $end, 'date' => $start];
        app()->instance('current_tenant', $tenant);

        $out = ['tenant' => $tenant->id, 'month' => $month, 'exports' => [], 'pages' => []];
        foreach (array_filter(['admin' => $admin, 'manager' => $manager]) as $who => $user) {
            auth()->guard('web')->setUser($user);
            auth()->shouldUse('web');
            foreach (self::EXPORTS as $name => $extra) {
                $out['exports']["{$who}|{$name}"] = $this->hit($name, $extra, $shared, $tenant);
            }
            foreach (self::PAGES as $name => $extra) {
                $out['pages']["{$who}|{$name}"] = $this->hit($name, $extra, $shared, $tenant);
            }
        }
        $errors = count(array_filter(array_merge($out['exports'], $out['pages']), fn ($r) => is_array($r) && isset($r['error'])));
        $this->line(count($out['exports']).' exports, '.count($out['pages']).' pages ('.$errors.' answered with an error — recorded too)');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir.DIRECTORY_SEPARATOR.$this->option('label').'.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info('Written '.$dir.DIRECTORY_SEPARATOR.$this->option('label').'.json');

        return self::SUCCESS;
    }

    /** One route as the current user: CSV body, normalised view data / JSON, or ['error' => …]. */
    private function hit(string $routeName, array $extra, array $shared, Tenant $tenant)
    {
        $query = $shared;
        $params = [];
        $method = 'GET';
        $firstId = (int) User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', '!=', 'admin')->where('status', 1)->orderBy('id')->value('id');
        foreach ($extra as $k => $v) {
            if ($k === '_method') {
                $method = $v;

                continue;
            }
            $v = match ($v) {
                'first' => $firstId,
                'first_encrypted' => encrypt($firstId),
                'first_location' => (int) DB::table('attendance_locations')->where('tenant_id', $tenant->id)->orderBy('id')->value('id'),
                default => $v,
            };
            in_array($k, ['branchId', 'id'], true) ? $params[$k] = $v : $query[$k] = $v;
        }

        $url = route($routeName, $params);
        $request = $method === 'GET' ? Request::create($url.'?'.http_build_query($query), 'GET') : Request::create($url, $method, $query);
        $session = app('session.store');
        $session->flush();
        // Some reports read the company from the session, as in a real login.
        $session->put('tenant_id', (int) $tenant->id);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => auth()->user());
        app()->instance('request', $request);
        app('url')->setRequest($request);

        DB::beginTransaction();
        try {
            $route = app('router')->getRoutes()->match($request);
            $route->bind($request);
            $request->setRouteResolver(fn () => $route);
            [$class, $action] = explode('@', $route->getActionName());
            $response = app()->call([app($class), $action], $route->parameters());

            if ($response instanceof View) {
                return ['view' => $response->name(), 'data' => $this->normalise($response->getData())];
            }
            if ($response instanceof JsonResponse) {
                return ['json' => $this->normalise($response->getData(true))];
            }
            if ($response instanceof StreamedResponse) {
                ob_start();
                $response->sendContent();

                return (string) ob_get_clean();
            }
            if ($response instanceof \Illuminate\Http\RedirectResponse) {
                return ['error' => 'redirect: '.$session->get('error')];
            }

            return (string) $response->getContent();
        } catch (\Throwable $e) {
            return ['error' => get_class($e).': '.$e->getMessage()];
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    /** Plain JSON-able values; encrypted ids (random per call) masked. */
    private function normalise($v)
    {
        if ($v instanceof \Closure) {
            return 'closure';
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d'); // dates built from a month carry the current clock time
        }
        if ($v instanceof Model || $v instanceof Arrayable) {
            return $this->normalise($v->toArray());
        }
        if ($v instanceof \JsonSerializable) {
            return $this->normalise($v->jsonSerialize());
        }
        if (is_object($v)) {
            return $this->normalise(get_object_vars($v));
        }
        if (is_array($v)) {
            return array_map(fn ($x) => $this->normalise($x), $v);
        }
        if (is_float($v)) {
            return round($v, 6);
        }
        if (is_string($v)) {
            return preg_replace('#eyJpdiI6[A-Za-z0-9+/=%]+#', '<encrypted>', $v);
        }

        return $v;
    }

    private function compare(string $dir, string $before, string $after): int
    {
        $a = json_decode((string) @file_get_contents("{$dir}/{$before}.json"), true);
        $b = json_decode((string) @file_get_contents("{$dir}/{$after}.json"), true);
        if (! $a || ! $b) {
            $this->error('Snapshot file missing.');

            return self::FAILURE;
        }
        $diff = 0;
        foreach (['exports', 'pages'] as $section) {
            foreach (array_unique(array_merge(array_keys($a[$section] ?? []), array_keys($b[$section] ?? []))) as $name) {
                if (($a[$section][$name] ?? null) !== ($b[$section][$name] ?? null)) {
                    $diff++;
                    $this->line("~ {$section}: {$name} differs");
                }
            }
        }
        $total = count($a['exports'] ?? []) + count($a['pages'] ?? []);
        $diff ? $this->error("{$diff} of {$total} differ.") : $this->info("Identical: {$total} exports + pages.");

        return $diff ? self::FAILURE : self::SUCCESS;
    }
}
