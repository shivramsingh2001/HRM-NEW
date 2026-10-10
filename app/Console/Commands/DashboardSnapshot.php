<?php

namespace App\Console\Commands;

use App\Http\Controllers\Dashboard\DashboardController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Golden-master snapshot of every dashboard's data (code-quality plan,
 * Phase 2 — the safety net for splitting DashboardController). Opens
 * /dashboard as admins (every period filter), managers and employees of the
 * chosen companies and stores the view name + all view data, normalised to
 * plain JSON. Read-only (rolled back).
 *
 *   php artisan dashboard:snapshot --label=before
 *   … refactor …
 *   php artisan dashboard:snapshot --label=after --compare=before
 */
class DashboardSnapshot extends Command
{
    protected $signature = 'dashboard:snapshot
        {--label=snapshot : File name under storage/app/dashboard-snapshots}
        {--tenants=7,8,10 : Company ids}
        {--per-role=3 : Managers / employees per company}
        {--compare= : Compare --label against this earlier label instead of taking a snapshot}';

    protected $description = 'Read-only golden-master snapshot of the admin / manager / employee dashboards';

    private const ADMIN_RANGES = [
        'none' => [],
        'today' => ['range' => 'today'],
        'last_7_days' => ['range' => 'last_7_days'],
        'this_month' => ['range' => 'this_month'],
        'previous_month' => ['range' => 'previous_month'],
        'custom' => ['range' => 'custom', 'from' => '2026-08-01', 'to' => '2026-09-15'],
    ];

    public function handle(): int
    {
        $dir = storage_path('app/dashboard-snapshots');
        if ($this->option('compare')) {
            return $this->compare($dir, (string) $this->option('compare'), (string) $this->option('label'));
        }

        $out = [];
        foreach (array_filter(array_map('intval', explode(',', (string) $this->option('tenants')))) as $tenantId) {
            $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
            if (! $tenant) {
                continue;
            }
            $users = fn (string $role, int $limit) => User::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('role', $role)
                ->where('status', 1)->orderBy('id')->limit($limit)->get();

            foreach ($users('admin', 1) as $admin) {
                foreach (self::ADMIN_RANGES as $name => $query) {
                    $out["{$tenantId}|admin|{$admin->id}|{$name}"] = $this->open($tenant, $admin, $query);
                }
            }
            foreach (['hr', 'manager', 'employee'] as $role) {
                foreach ($users($role, (int) $this->option('per-role')) as $u) {
                    $out["{$tenantId}|{$role}|{$u->id}"] = $this->open($tenant, $u, []);
                }
            }
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $dir.DIRECTORY_SEPARATOR.$this->option('label').'.json';
        file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info('Snapshot of '.count($out).' dashboards written to '.$file);

        return self::SUCCESS;
    }

    private function open(Tenant $tenant, User $user, array $query): array
    {
        app()->instance('current_tenant', $tenant);
        auth()->guard('web')->setUser($user);
        auth()->shouldUse('web');

        $request = Request::create('/dashboard', 'GET', $query);
        $session = app('session.store');
        $session->flush();
        $session->put('tenant_id', (int) $tenant->id);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);
        app('url')->setRequest($request);

        DB::beginTransaction();
        try {
            $response = app(DashboardController::class)->index();
            if (! $response instanceof View) {
                return ['response' => get_debug_type($response)];
            }

            return ['view' => $response->name(), 'data' => $this->normalise($response->getData())];
        } catch (\Throwable $e) {
            return ['error' => get_class($e).': '.$e->getMessage()];
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    /** Plain JSON-able values: models / collections to arrays, dates to ISO strings, floats rounded. */
    private function normalise($v)
    {
        if ($v instanceof \Closure) {
            return 'closure';
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d H:i:s');
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
        $diffs = [];
        $walk = function ($x, $y, string $path) use (&$walk, &$diffs) {
            if (is_array($x) && is_array($y)) {
                foreach (array_unique(array_merge(array_keys($x), array_keys($y))) as $k) {
                    $p = $path === '' ? (string) $k : $path.'.'.$k;
                    if (! array_key_exists($k, $x) || ! array_key_exists($k, $y)) {
                        $diffs[] = (array_key_exists($k, $x) ? '- ' : '+ ').$p;
                    } else {
                        $walk($x[$k], $y[$k], $p);
                    }
                }

                return;
            }
            if ($x !== $y) {
                $diffs[] = "~ {$path}: ".json_encode($x).' → '.json_encode($y);
            }
        };
        $walk($a, $b, '');
        foreach (array_slice($diffs, 0, 100) as $d) {
            $this->line(mb_substr($d, 0, 300));
        }
        $diffs ? $this->error(count($diffs).' difference(s).') : $this->info('Identical: '.count($a).' dashboards compared.');

        return $diffs ? self::FAILURE : self::SUCCESS;
    }
}
