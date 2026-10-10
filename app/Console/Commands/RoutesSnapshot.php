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
 * Golden-master snapshot of the read endpoints of the employee, shift and
 * mobile-attendance controllers (code-quality plan, Phase 4 — the safety net
 * for splitting UserController, ShiftController and the mobile
 * Api\Attendance\AttendanceController; their write endpoints are covered by
 * the feature tests). Each route is called as the given user (web pages as the
 * admin, mobile API as an employee and a manager), the view data / JSON / CSV
 * is normalised and stored. Read-only (rolled back); encrypted ids masked.
 *
 *   php artisan routes:snapshot --label=before
 *   … refactor …
 *   php artisan routes:snapshot --label=after --compare=before
 */
class RoutesSnapshot extends Command
{
    protected $signature = 'routes:snapshot
        {--label=snapshot : File name under storage/app/route-snapshots}
        {--tenant=7 : Company id}
        {--compare= : Compare --label against this earlier label instead of taking a snapshot}';

    protected $description = 'Read-only golden-master snapshot of employee / shift / mobile attendance read endpoints';

    /** [as, method, route name or URI, query, route params] */
    private function calls(int $firstId, string $month): array
    {
        $enc = '__ENC__';

        return [
            ['admin', 'GET', 'employee.index', [], []],
            ['admin', 'GET', 'employee.create', [], []],
            ['admin', 'GET', 'employee.show', [], ['id' => $enc]],
            ['admin', 'GET', 'employee.edit', [], ['id' => $enc]],
            ['admin', 'GET', 'employee.load.data', ['user_id' => $firstId], []],
            ['admin', 'GET', 'employee.import.template', [], []],
            ['admin', 'GET', 'shift.index', [], []],
            ['admin', 'GET', 'shift.roster', ['view' => 'month', 'month' => $month], []],
            ['admin', 'GET', 'shift.roster', ['view' => 'week', 'date' => $month.'-10'], []],
            ['admin', 'GET', 'shift.get-users-by-type', ['type' => 'all'], []],
            ['admin', 'GET', 'shift.user-shifts.data', ['from_date' => $month.'-01', 'to_date' => $month.'-28'], []],
            ['admin', 'GET', 'shift.user-shifts.export', ['from_date' => $month.'-01', 'to_date' => $month.'-28'], []],
            ['admin', 'GET', 'shift.assignments.history', ['user_id' => $firstId], []],
            ['admin', 'GET', 'shift.change-log', ['user_id' => $firstId], []],
            ['admin', 'POST', 'shift.assignment-conflicts', ['user_ids' => [$firstId], 'type' => 'permanent', 'start_date' => $month.'-01'], []],
            ['employee', 'GET', '/api/user/attendance/history', ['start_date' => $month.'-01', 'end_date' => $month.'-28'], []],
            ['employee', 'GET', '/api/user/attendance/today', [], []],
            ['employee', 'GET', '/api/user/attendance/today-locations', [], []],
            ['employee', 'GET', '/api/user/attendance/punches', ['date' => $month.'-10'], []],
            ['employee', 'GET', '/api/user/attendance/current-session', [], []],
            ['employee', 'GET', '/api/user/attendance/view-regularization', [], []],
            ['manager', 'GET', '/api/manager/attendance/view-regularization', [], []],
        ];
    }

    public function handle(): int
    {
        $dir = storage_path('app/route-snapshots');
        if ($this->option('compare')) {
            return $this->compare($dir, (string) $this->option('compare'), (string) $this->option('label'));
        }

        $tenant = Tenant::withoutGlobalScopes()->find((int) $this->option('tenant'));
        $pick = fn (string $role) => User::withoutGlobalScopes()->where('tenant_id', $tenant?->id)->where('role', $role)->where('status', 1)->orderBy('id')->first();
        $users = array_filter(['admin' => $pick('admin'), 'employee' => $pick('employee'), 'manager' => $pick('manager')]);
        if (! $tenant || ! isset($users['admin'])) {
            $this->error('Company or its admin not found.');

            return self::FAILURE;
        }
        $firstId = (int) User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', '!=', 'admin')->where('status', 1)->orderBy('id')->value('id');
        $month = now()->subMonthNoOverflow()->format('Y-m');
        app()->instance('current_tenant', $tenant);

        $out = [];
        foreach ($this->calls($firstId, $month) as $i => [$as, $method, $target, $query, $params]) {
            if (! isset($users[$as])) {
                continue;
            }
            foreach ($params as $k => $v) {
                $params[$k] = $v === '__ENC__' ? encrypt($firstId) : $v;
            }
            $out[sprintf('%02d|%s|%s %s', $i, $as, $method, $target)] = $this->hit($users[$as], $method, $target, $query, $params, $tenant);
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir.DIRECTORY_SEPARATOR.$this->option('label').'.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $errors = count(array_filter($out, fn ($r) => is_array($r) && isset($r['error'])));
        $this->info(count($out)." endpoints ({$errors} answered with an error — recorded too) written to {$dir}/".$this->option('label').'.json');

        return self::SUCCESS;
    }

    private function hit(User $user, string $method, string $target, array $query, array $params, Tenant $tenant)
    {
        foreach (['web', 'api'] as $guard) {
            auth()->guard($guard)->setUser($user);
        }
        auth()->shouldUse(str_starts_with($target, '/api/') ? 'api' : 'web');

        $url = str_starts_with($target, '/') ? url($target) : route($target, $params);
        $request = $method === 'GET' ? Request::create($url.($query ? '?'.http_build_query($query) : ''), 'GET') : Request::create($url, $method, $query);
        $session = app('session.store');
        $session->flush();
        $session->put('tenant_id', (int) $tenant->id);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);
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
                return ['status' => $response->getStatusCode(), 'json' => $this->normalise($response->getData(true))];
            }
            if ($response instanceof StreamedResponse) {
                ob_start();
                $response->sendContent();

                return (string) ob_get_clean();
            }
            if ($response instanceof \Illuminate\Http\RedirectResponse) {
                return ['error' => 'redirect: '.$session->get('error')];
            }
            if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
                return ['file' => md5_file($response->getFile()->getPathname())];
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

    private function normalise($v)
    {
        if ($v instanceof \Closure) {
            return 'closure';
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
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
            // Seconds since an open clock-in grow on every call.
            foreach (['open_since_seconds'] as $k) {
                if (array_key_exists($k, $v)) {
                    $v[$k] = 'elapsed';
                }
            }

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
        if (! is_array($a) || ! is_array($b)) {
            $this->error('Snapshot file missing.');

            return self::FAILURE;
        }
        $diff = 0;
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $k) {
            if (($a[$k] ?? null) !== ($b[$k] ?? null)) {
                $diff++;
                $this->line("~ {$k} differs");
            }
        }
        $diff ? $this->error("{$diff} of ".count($a).' differ.') : $this->info('Identical: '.count($a).' endpoints.');

        return $diff ? self::FAILURE : self::SUCCESS;
    }
}
