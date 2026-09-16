<?php

namespace Tests\Feature;

use App\Events\AttendanceDomainEvent;
use App\Jobs\SendWebhookDelivery;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tier 2 / T2-C — a domain event fans out to a matching, active endpoint as a
 * signed, queued delivery; non-matching endpoints are skipped.
 */
class WebhookDispatchTest extends TestCase
{
    public function test_matching_endpoint_gets_a_signed_delivery(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $tenantId = (int) (\DB::table('tenants')->value('id') ?: 1);

        $match = WebhookEndpoint::create([
            'tenant_id' => $tenantId,
            'url' => 'https://example.test/hook',
            'secret' => 'whsec_' . uniqid(),
            'events' => ['attendance.marked'],
            'is_active' => true,
        ]);
        $other = WebhookEndpoint::create([
            'tenant_id' => $tenantId,
            'url' => 'https://example.test/other',
            'secret' => 'whsec_' . uniqid(),
            'events' => ['regularization.decided'],
            'is_active' => true,
        ]);

        $before = WebhookDelivery::where('webhook_endpoint_id', $match->id)->count();

        event(new AttendanceDomainEvent('attendance.marked', $tenantId, ['attendance_id' => 1, 'user_id' => 1]));

        $this->assertSame(
            $before + 1,
            WebhookDelivery::where('webhook_endpoint_id', $match->id)->count(),
            'matching endpoint should get one delivery'
        );
        $this->assertSame(
            0,
            WebhookDelivery::where('webhook_endpoint_id', $other->id)->count(),
            'non-subscribed endpoint should get none'
        );

        // sync queue ran SendWebhookDelivery -> the request was signed
        Http::assertSent(function ($request) {
            return $request->hasHeader('X-HRM-Event', 'attendance.marked')
                && str_starts_with($request->header('X-HRM-Signature')[0] ?? '', 'sha256=');
        });

        $delivery = WebhookDelivery::where('webhook_endpoint_id', $match->id)->latest('id')->first();
        $this->assertSame('success', $delivery->status);

        // cleanup
        WebhookDelivery::where('webhook_endpoint_id', $match->id)->delete();
        $match->delete();
        $other->delete();
    }

    public function test_class_is_a_should_queue_job(): void
    {
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, new SendWebhookDelivery(1));
    }
}
