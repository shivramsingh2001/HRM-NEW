<?php

namespace App\Listeners;

use App\Events\AttendanceDomainEvent;
use App\Jobs\SendWebhookDelivery;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

/**
 * Tier 2 / T2-C — fan a domain event out to the tenant's active webhook
 * endpoints, one queued delivery each.
 */
class DispatchWebhooks
{
    public function handle(AttendanceDomainEvent $event): void
    {
        $endpoints = WebhookEndpoint::where('tenant_id', $event->tenantId)
            ->where('is_active', true)
            ->get()
            ->filter(fn ($e) => $e->subscribesTo($event->name));

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'tenant_id' => $event->tenantId,
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $event->name,
                'payload' => $event->toDelivery(),
                'status' => 'pending',
                'created_at' => now(),
            ]);

            SendWebhookDelivery::dispatch($delivery->id)->afterCommit();
        }
    }
}
