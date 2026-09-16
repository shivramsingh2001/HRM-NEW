<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tier 2 / T2-C — POST one webhook_deliveries row to its endpoint, signed, with
 * bounded retries. The subscriber is never in the request path.
 */
class SendWebhookDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Minutes to wait before attempt N (1-indexed). */
    private const SCHEDULE = [1, 5, 30, 120, 360];

    public int $tries = 1; // ret/scheduling handled explicitly below

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);
        if (! $delivery || in_array($delivery->status, ['success', 'dead'], true)) {
            return;
        }

        $endpoint = WebhookEndpoint::find($delivery->webhook_endpoint_id);
        if (! $endpoint || ! $endpoint->is_active) {
            $delivery->update(['status' => 'dead', 'delivered_at' => now()]);

            return;
        }

        $attempt = $delivery->attempt + 1;
        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $endpoint->secret);

        $startedAt = microtime(true);
        try {
            $res = Http::timeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-HRM-Event' => $delivery->event,
                    'X-HRM-Delivery' => (string) $delivery->id,
                    'X-HRM-Timestamp' => $timestamp,
                    'X-HRM-Signature' => 'sha256=' . $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $ms = (int) ((microtime(true) - $startedAt) * 1000);

            if ($res->successful()) {
                $delivery->update([
                    'status' => 'success',
                    'attempt' => $attempt,
                    'response_status' => $res->status(),
                    'response_ms' => $ms,
                    'delivered_at' => now(),
                ]);
                if ($endpoint->failure_count > 0) {
                    $endpoint->update(['failure_count' => 0]);
                }

                return;
            }

            $this->fail($delivery, $endpoint, $attempt, $res->status(), $ms);
        } catch (\Throwable $e) {
            $ms = (int) ((microtime(true) - $startedAt) * 1000);
            Log::warning('Webhook delivery error', ['delivery' => $delivery->id, 'error' => $e->getMessage()]);
            $this->fail($delivery, $endpoint, $attempt, null, $ms);
        }
    }

    private function fail(WebhookDelivery $delivery, WebhookEndpoint $endpoint, int $attempt, ?int $status, int $ms): void
    {
        $maxAttempts = count(self::SCHEDULE);

        if ($attempt >= $maxAttempts) {
            $delivery->update([
                'status' => 'dead',
                'attempt' => $attempt,
                'response_status' => $status,
                'response_ms' => $ms,
            ]);
            $fails = $endpoint->failure_count + 1;
            $endpoint->update([
                'failure_count' => $fails,
                'is_active' => $fails >= 15 ? false : $endpoint->is_active,
                'disabled_at' => $fails >= 15 ? now() : $endpoint->disabled_at,
            ]);

            return;
        }

        $delayMin = self::SCHEDULE[$attempt] ?? 360;
        $delivery->update([
            'status' => 'failed',
            'attempt' => $attempt,
            'response_status' => $status,
            'response_ms' => $ms,
            'next_retry_at' => now()->addMinutes($delayMin),
        ]);

        self::dispatch($delivery->id)->delay(now()->addMinutes($delayMin));
    }
}
