<?php
// app/Channels/SmsChannel.php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wraps the same Airtel IQ SMS gateway OTP login already uses
 * (App\Http\Controllers\Api\Auth\AuthController), but with its OWN
 * `broadcast_dlt_template_id` config key — Indian DLT rules require every
 * SMS to match a pre-registered template, so a free-text broadcast body
 * cannot reuse the OTP template. Gracefully no-ops (logs, never throws)
 * until an operator registers a broadcast-specific DLT template and sets
 * `DLT_TEMPLATE_ID_BROADCAST` — mirrors App\Services\FirebaseService::
 * isReady()'s degrade-gracefully-on-missing-config pattern.
 *
 * A Notification class opts in via `via()` returning `[..., SmsChannel::class]`
 * and implementing `toSms($notifiable): ?string`.
 */
class SmsChannel
{
    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $templateId = config('sms.airtel.broadcast_dlt_template_id');
        if (! $templateId) {
            Log::info('SmsChannel: no broadcast DLT template configured, skipping.', [
                'notifiable_id' => $notifiable->id ?? null,
            ]);

            return;
        }

        $username = config('sms.airtel.username');
        $password = config('sms.airtel.password');
        if (! $username || ! $password) {
            return;
        }

        $mobile = $notifiable->contact ?? null;
        if (! $mobile) {
            return;
        }

        $message = $notification->toSms($notifiable);
        if (! $message) {
            return;
        }

        try {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
            ])->post('https://iqsms.airtel.in/api/v1/send-prepaid-sms', [
                'customerId' => config('sms.airtel.customer_id'),
                'destinationAddress' => [$mobile],
                'dltTemplateId' => $templateId,
                'entityId' => config('sms.airtel.entity_id'),
                'message' => $message,
                'messageType' => config('sms.airtel.message_type'),
                'sourceAddress' => config('sms.airtel.source_address'),
            ]);

            if (! $response->successful()) {
                Log::warning('SmsChannel: Airtel send failed', [
                    'notifiable_id' => $notifiable->id ?? null,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SmsChannel: exception sending SMS: ' . $e->getMessage(), [
                'notifiable_id' => $notifiable->id ?? null,
            ]);
        }
    }
}
