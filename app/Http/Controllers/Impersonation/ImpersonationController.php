<?php

namespace App\Http\Controllers\Impersonation;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Super Admin Panel Phase 6.2 — consume a one-time impersonation token from the
 * panel and log in AS the tenant user, with a persistent banner. The bearer is
 * a row in the shared `impersonation_sessions` table (no signing needed).
 */
class ImpersonationController extends Controller
{
    public function consume(Request $request)
    {
        $token = (string) $request->query('token');
        if ($token === '' || Cache::has("impersonation:consumed:{$token}")) {
            abort(403, 'Invalid or already-used impersonation link.');
        }

        $session = DB::table('impersonation_sessions')->where('session_token', $token)->first();
        // The Super Admin Panel writes timestamps in UTC — parse them as such.
        if (! $session
            || $session->ended_at !== null
            || ! $session->expires_at
            || Carbon::parse($session->expires_at, 'UTC')->isPast()) {
            abort(403, 'This impersonation link is expired or has ended.');
        }

        $target = User::withoutGlobalScope('tenant')->find($session->tenant_user_id);
        if (! $target || (int) $target->tenant_id !== (int) $session->tenant_id) {
            abort(404, 'Impersonation target not found.');
        }

        Cache::put("impersonation:consumed:{$token}", true, now()->addHours(2));

        Auth::login($target);
        $request->session()->regenerate();
        $request->session()->put('tenant_id', $target->tenant_id);
        if ($target->tenant) {
            $request->session()->put('tenant', $target->tenant);
        }
        $request->session()->put('impersonation', [
            'token' => $token,
            'by' => $session->super_admin_id,
            'session_id' => $session->id,
            'expires_at' => Carbon::parse($session->expires_at, 'UTC')->toIso8601String(),
        ]);

        $this->platformAudit('impersonation.consumed', $session);

        return redirect()->route('dashboard')->with('success', 'Impersonation session active.');
    }

    public function end(Request $request)
    {
        $imp = $request->session()->get('impersonation');

        if ($imp) {
            DB::table('impersonation_sessions')
                ->where('id', $imp['session_id'] ?? 0)
                ->whereNull('ended_at')
                ->update(['ended_at' => now(), 'end_reason' => 'manual']);
            $this->platformAudit('impersonation.ended', (object) [
                'id' => $imp['session_id'] ?? null,
                'super_admin_id' => $imp['by'] ?? null,
                'tenant_id' => $request->session()->get('tenant_id'),
                'tenant_user_id' => Auth::id(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $panel = rtrim(config('services.superadmin.panel_url', ''), '/');

        return $panel
            ? redirect()->away($panel . '/impersonation')
            : redirect()->route('login')->with('success', 'Impersonation ended.');
    }

    /** Write to the shared platform audit_logs as super_admin_impersonating (+ HMAC). */
    private function platformAudit(string $action, object $session): void
    {
        try {
            $id = DB::table('audit_logs')->insertGetId([
                'actor_type' => 'super_admin_impersonating',
                'actor_id' => $session->super_admin_id ?? null,
                'impersonating_user_id' => $session->tenant_user_id ?? Auth::id(),
                'tenant_id' => $session->tenant_id ?? null,
                'action' => $action,
                'entity_type' => 'impersonation_sessions',
                'entity_id' => $session->id ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            $key = (string) config('audit.hmac_key', '');
            if ($key !== '' && $id) {
                $r = DB::table('audit_logs')->find($id);
                $canonical = implode('|', [
                    $r->id, $r->actor_type, $r->actor_id ?? '', $r->impersonating_user_id ?? '',
                    $r->tenant_id ?? '', $r->action, $r->entity_type ?? '', $r->entity_id ?? '',
                    $r->old_values ?? '', $r->new_values ?? '', (string) $r->created_at,
                ]);
                DB::table('sa_audit_signatures')->updateOrInsert(
                    ['audit_log_id' => $id],
                    ['hmac' => hash_hmac('sha256', $canonical, $key), 'created_at' => now()],
                );
            }
        } catch (\Throwable $e) {
            report($e);
            // never block the flow on an audit write
        }
    }
}
