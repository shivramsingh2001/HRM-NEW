<?php

namespace App\Services\Reports;

use App\Exceptions\ReportScheduleException;
use App\Models\ReportSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ReportCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Produces a scheduled report's file by replaying the report's own Export
 * route as the schedule owner — same controller, same filters, same file the
 * Export button downloads (technique shared with `reports:snapshot`).
 *
 * The route's `role:` / `feature:` / `permission:` middleware is run in front
 * of the controller, so the owner gets exactly the access they have on screen.
 * The session/CSRF/auth/tenant web middleware is replaced by setting the same
 * state by hand (logged-in user, `current_tenant`, `session('tenant_id')`).
 *
 * generate() is meant for a dedicated CLI process (`reports:run-schedule`): it
 * switches the process's logged-in user and tenant and does not switch them back.
 * generateFor() is the in-request variant (mobile admin reports API) and restores
 * the outer request's state afterwards.
 */
class ReportFileGenerator
{
    private const GATE_MIDDLEWARE = ['role', 'feature', 'permission'];

    /**
     * @return array{content: string, filename: string, mime: string, period_label: string}
     */
    public function generate(ReportSchedule $schedule, User $owner, Tenant $tenant, CarbonImmutable $now): array
    {
        if (ReportCatalog::get($schedule->report_key) === null) {
            throw new ReportScheduleException('This report is no longer available.', true);
        }

        $allowed = ReportCatalog::periodsFor($schedule->report_key);
        $period = in_array($schedule->period, $allowed, true) ? $schedule->period : ($allowed[0] ?? null);
        $resolved = ReportCatalog::periodQuery($period, $now);

        $this->actAs($owner, $tenant);

        return $this->produce($schedule->report_key, $resolved, ReportCatalog::filterQuery($schedule->filters), $tenant);
    }

    /**
     * The same file for a live web/API request (mobile admin reports): replays the
     * export as $user, then puts the outer request, session, URL generator, guard
     * and tenant back exactly as they were.
     *
     * @param  array{query: array<string, string>, label: string}  $period  from ReportCatalog::periodQuery() / pickedQuery()
     * @param  array<string, string>  $extraQuery  filters etc. (ReportCatalog::filterQuery() + anything the report reads)
     * @return array{content: string, filename: string, mime: string, period_label: string}
     */
    public function generateFor(string $key, array $period, array $extraQuery, User $user, Tenant $tenant): array
    {
        if (ReportCatalog::get($key) === null) {
            throw new ReportScheduleException('This report is no longer available.', true);
        }

        $sessions = app('session');
        $saved = [
            'request' => app('request'),
            'session_driver' => $sessions->getDefaultDriver(),
            'session_store' => app()->bound('session.store') ? app('session.store') : null,
            'guard' => auth()->getDefaultDriver(),
            'web_user' => auth()->guard('web')->hasUser() ? auth()->guard('web')->user() : null,
            'tenant' => app()->bound('current_tenant') ? app('current_tenant') : null,
        ];

        try {
            $this->actAs($user, $tenant);

            return $this->produce($key, $period, $extraQuery, $tenant);
        } finally {
            app()->instance('request', $saved['request']);
            RequestFacade::clearResolvedInstance('request');
            app('url')->setRequest($saved['request']);
            $sessions->setDefaultDriver($saved['session_driver']);
            $saved['session_store'] ? app()->instance('session.store', $saved['session_store']) : app()->forgetInstance('session.store');
            $saved['web_user'] ? auth()->guard('web')->setUser($saved['web_user']) : auth()->guard('web')->forgetUser();
            auth()->shouldUse($saved['guard']);
            $saved['tenant'] ? app()->instance('current_tenant', $saved['tenant']) : app()->forgetInstance('current_tenant');
        }
    }

    /**
     * @param  array{query: array<string, string>, label: string}  $period
     * @param  array<string, string>  $extraQuery
     * @return array{content: string, filename: string, mime: string, period_label: string}
     */
    private function produce(string $key, array $period, array $extraQuery, Tenant $tenant): array
    {
        $entry = ReportCatalog::get($key);
        $query = $entry['query'] + $period['query'] + $extraQuery;
        $request = $this->makeRequest(route($entry['route'], $entry['params']), $query, $tenant);

        $response = $this->dispatch($request);
        [$content, $filename, $mime] = $this->capture($response, $entry['label'], $period['label']);

        return ['content' => $content, 'filename' => $filename, 'mime' => $mime, 'period_label' => $period['label']];
    }

    private function actAs(User $owner, Tenant $tenant): void
    {
        app()->instance('current_tenant', $tenant);
        // Per-request memos (FeatureService is `scoped`) must not carry another tenant's answers.
        app()->forgetScopedInstances();
        auth()->guard('web')->setUser($owner);
        auth()->shouldUse('web');
    }

    /** @param array<string, string> $query */
    private function makeRequest(string $url, array $query, Tenant $tenant): Request
    {
        $request = Request::create($url.(str_contains($url, '?') ? '&' : '?').http_build_query($query), 'GET');
        // In-memory session: some reports read the company from the session, as in a real login.
        // Default driver too: the session() helper reads the manager's default driver, not the request's store.
        app('session')->setDefaultDriver('array');
        $session = app('session')->driver();
        $session->flush();
        $session->put('tenant_id', (int) $tenant->id);
        $session->put('tenant', $tenant);
        $request->setLaravelSession($session);
        // back()->with('error', …) flashes into `session.store` — make it this same store so the reason is readable.
        app()->instance('session.store', $session);
        $request->setUserResolver(fn () => auth()->user());

        app()->instance('request', $request);
        RequestFacade::clearResolvedInstance('request');
        app('url')->setRequest($request);

        return $request;
    }

    private function dispatch(Request $request): mixed
    {
        // In a console process the router only learns the `role` / `feature` / `permission`
        // aliases when the HTTP kernel is built (it syncs bootstrap/app.php's aliases to the router).
        app(\Illuminate\Contracts\Http\Kernel::class);

        $route = app('router')->getRoutes()->match($request);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $gates = array_values(array_filter(
            $route->gatherMiddleware(),
            fn ($mw) => is_string($mw) && in_array(explode(':', $mw, 2)[0], self::GATE_MIDDLEWARE, true)
        ));

        return (new Pipeline(app()))
            ->send($request)
            ->through(app('router')->resolveMiddleware($gates))
            ->then(function () use ($route) {
                [$class, $action] = explode('@', $route->getActionName());

                return app()->call([app($class), $action], $route->parameters());
            });
    }

    /** @return array{0: string, 1: string, 2: string} content, file name, mime type */
    private function capture(mixed $response, string $reportLabel, string $periodLabel): array
    {
        if ($response instanceof RedirectResponse) {
            $session = request()->session();
            $why = $session->get('error') ?: collect($session->get('errors')?->all() ?? [])->first();

            throw new ReportScheduleException('The report was refused: '.($why ?: 'access denied or no data for the period.'), true);
        }
        if ($response instanceof View) {
            throw new ReportScheduleException('The report returned a page instead of a file.');
        }
        if (! $response instanceof Response) {
            throw new ReportScheduleException('The report did not return a file.');
        }
        if ($response->getStatusCode() >= 400) {
            throw new ReportScheduleException('The report failed (HTTP '.$response->getStatusCode().').', $response->getStatusCode() === 403);
        }

        if ($response instanceof StreamedResponse) {
            ob_start();
            try {
                $response->sendContent();
            } finally {
                $content = (string) ob_get_clean();
            }
        } elseif ($response instanceof BinaryFileResponse) {
            $path = $response->getFile()->getPathname();
            $content = (string) file_get_contents($path);
            if ($this->deletesAfterSend($response)) {
                @unlink($path);
            }
        } else {
            $content = (string) $response->getContent();
        }

        $mime = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type', 'text/csv'))[0]));
        $filename = $this->filename($response, $reportLabel, $periodLabel, $mime);
        // A file download only gets its Content-Type when sent — go by the extension instead.
        $mime = match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'pdf' => 'application/pdf',
            default => $mime ?: 'application/octet-stream',
        };

        return [$content, $filename, $mime];
    }

    private function deletesAfterSend(BinaryFileResponse $response): bool
    {
        $prop = new \ReflectionProperty(BinaryFileResponse::class, 'deleteFileAfterSend');

        return (bool) $prop->getValue($response);
    }

    private function filename(Response $response, string $reportLabel, string $periodLabel, string $mime): string
    {
        $disposition = (string) $response->headers->get('Content-Disposition', '');
        if (preg_match('/filename\*=UTF-8\'\'([^;]+)/i', $disposition, $m) || preg_match('/filename="?([^";]+)"?/i', $disposition, $m)) {
            $name = basename(rawurldecode(trim($m[1])));
            if ($name !== '') {
                return $name;
            }
        }
        $ext = str_contains($mime, 'spreadsheet') ? 'xlsx' : 'csv';

        return Str::slug($reportLabel.' '.$periodLabel).'.'.$ext;
    }
}
