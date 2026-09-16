<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Tier 2 / T2-B — issue / rotate / revoke public-API credentials.
 * The plaintext secret is shown once (flashed to the session).
 */
class ApiClientController extends Controller
{
    private const SCOPES = [
        'attendance:read', 'attendance:write',
        'regularization:read', 'regularization:write',
        'analytics:read',
        'biometric:read', 'biometric:write',
    ];

    public function index()
    {
        $tenantId = (int) Auth::user()->tenant_id;

        $clients = ApiClient::where('tenant_id', $tenantId)->orderByDesc('id')->get();
        $recent = ApiRequestLog::where('tenant_id', $tenantId)
            ->orderByDesc('id')->limit(30)->get();

        return view('client.settings.api-clients', [
            'clients' => $clients,
            'recent' => $recent,
            'allScopes' => self::SCOPES,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = (int) Auth::user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['in:' . implode(',', self::SCOPES)],
            'rate_limit_per_min' => ['nullable', 'integer', 'min:1', 'max:6000'],
        ]);

        $issued = ApiClient::issue(
            $tenantId,
            $data['name'],
            $data['scopes'],
            Auth::id(),
            (int) ($data['rate_limit_per_min'] ?? 120),
        );

        return back()
            ->with('success', 'API client created. Copy the secret now — it will not be shown again.')
            ->with('new_api_secret', $issued['secret']);
    }

    public function rotate($id)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $client = ApiClient::where('tenant_id', $tenantId)->findOrFail($id);

        $issued = ApiClient::issue(
            $tenantId,
            $client->name . ' (rotated)',
            $client->scopes ?? [],
            Auth::id(),
            $client->rate_limit_per_min,
        );
        $client->update(['is_active' => false]);

        return back()
            ->with('success', 'Secret rotated. The old key is now disabled.')
            ->with('new_api_secret', $issued['secret']);
    }

    public function destroy($id)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        ApiClient::where('tenant_id', $tenantId)->findOrFail($id)->update(['is_active' => false]);

        return back()->with('success', 'API client revoked.');
    }
}
