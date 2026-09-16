<?php

namespace App\Http\Controllers\Settings;

use App\Events\AttendanceDomainEvent;
use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Tier 2 / T2-C — manage webhook endpoints + inspect deliveries.
 */
class WebhookController extends Controller
{
    public function index()
    {
        $tenantId = (int) Auth::user()->tenant_id;

        $endpoints = WebhookEndpoint::where('tenant_id', $tenantId)->orderByDesc('id')->get();
        $deliveries = WebhookDelivery::where('tenant_id', $tenantId)
            ->orderByDesc('id')->limit(40)->get();

        return view('client.settings.webhooks', [
            'endpoints' => $endpoints,
            'deliveries' => $deliveries,
            'eventNames' => AttendanceDomainEvent::NAMES,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = (int) Auth::user()->tenant_id;

        $data = $request->validate([
            'url' => ['required', 'url', 'max:1000'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string'],
        ]);

        WebhookEndpoint::create([
            'tenant_id' => $tenantId,
            'url' => $data['url'],
            'secret' => 'whsec_' . Str::random(32),
            'events' => in_array('*', $data['events'], true) ? ['*'] : $data['events'],
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Webhook endpoint added.');
    }

    public function test($id)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $endpoint = WebhookEndpoint::where('tenant_id', $tenantId)->findOrFail($id);

        event(new AttendanceDomainEvent('attendance.marked', $tenantId, [
            'test' => true,
            'endpoint_id' => $endpoint->id,
            'note' => 'Test event from settings',
        ]));

        return back()->with('success', 'Test event queued for delivery.');
    }

    public function destroy($id)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        WebhookEndpoint::where('tenant_id', $tenantId)->findOrFail($id)->delete();

        return back()->with('success', 'Webhook endpoint removed.');
    }
}
