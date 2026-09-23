<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\User;
use App\Notifications\AssetNotification;
use Illuminate\Support\Facades\Log;

/**
 * Dual-channel (FCM push + DB `notifications` table) notifications for the
 * Asset module, mirroring RequestNotificationService's exact structure so
 * behavior/conventions stay consistent across modules. Best-effort and
 * non-blocking — a notification failure never breaks the asset action that
 * triggered it (every public method wraps in try/catch and just logs).
 */
class AssetNotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function notifyAssetAssigned(Asset $asset, User $assignee): bool
    {
        try {
            $title = '📦 Asset Assigned';
            $body = "You've been assigned {$asset->name} ({$asset->asset_code}). Please accept it.";
            $data = ['asset_id' => $asset->id, 'asset_code' => $asset->asset_code, 'type' => 'asset_assigned'];

            $this->sendNotification($assignee, $title, $body, $data);
            $assignee->notify(new AssetNotification($asset, 'assigned'));

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send asset-assigned notification', ['error' => $e->getMessage(), 'asset_id' => $asset->id]);
            return false;
        }
    }

    public function notifyAssetTransferred(Asset $asset, User $newAssignee): bool
    {
        try {
            $title = '📦 Asset Transferred to You';
            $body = "{$asset->name} ({$asset->asset_code}) has been transferred to you. Please accept it.";
            $data = ['asset_id' => $asset->id, 'asset_code' => $asset->asset_code, 'type' => 'asset_transferred'];

            $this->sendNotification($newAssignee, $title, $body, $data);
            $newAssignee->notify(new AssetNotification($asset, 'transferred'));

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send asset-transferred notification', ['error' => $e->getMessage(), 'asset_id' => $asset->id]);
            return false;
        }
    }

    /**
     * Notify the person who assigned the asset (and admins/HR) once the
     * assignee accepts it.
     */
    public function notifyAssetAccepted(Asset $asset, User $assignedBy): bool
    {
        try {
            $recipients = $this->getAdminHrUsers()->push($assignedBy)->unique('id');

            foreach ($recipients as $recipient) {
                $title = '✅ Asset Accepted';
                $body = "{$asset->name} ({$asset->asset_code}) has been accepted by the assignee.";
                $this->sendNotification($recipient, $title, $body, ['asset_id' => $asset->id, 'type' => 'asset_accepted']);
                $recipient->notify(new AssetNotification($asset, 'accepted'));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send asset-accepted notification', ['error' => $e->getMessage(), 'asset_id' => $asset->id]);
            return false;
        }
    }

    public function notifyReturnRequested(Asset $asset): bool
    {
        try {
            foreach ($this->getAdminHrUsers() as $recipient) {
                $title = '↩️ Asset Return Requested';
                $body = "A return has been requested for {$asset->name} ({$asset->asset_code}).";
                $this->sendNotification($recipient, $title, $body, ['asset_id' => $asset->id, 'type' => 'asset_return_requested']);
                $recipient->notify(new AssetNotification($asset, 'return_requested'));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send return-requested notification', ['error' => $e->getMessage(), 'asset_id' => $asset->id]);
            return false;
        }
    }

    public function notifyRepairStatusChanged(Asset $asset, ?User $custodian = null): bool
    {
        try {
            $recipients = $this->getAdminHrUsers();
            if ($custodian) {
                $recipients = $recipients->push($custodian)->unique('id');
            }

            foreach ($recipients as $recipient) {
                $title = '🔧 Asset Repair Update';
                $body = "{$asset->name} ({$asset->asset_code}) repair status changed to " . str_replace('_', ' ', $asset->status) . '.';
                $this->sendNotification($recipient, $title, $body, ['asset_id' => $asset->id, 'type' => 'asset_repair_status']);
                $recipient->notify(new AssetNotification($asset, 'repair_status_changed'));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send repair-status notification', ['error' => $e->getMessage(), 'asset_id' => $asset->id]);
            return false;
        }
    }

    public function notifyDisposalRecorded(Asset $asset): bool
    {
        try {
            foreach ($this->getAdminHrUsers() as $recipient) {
                $title = '🗑️ Asset Disposed';
                $body = "{$asset->name} ({$asset->asset_code}) has been disposed.";
                $this->sendNotification($recipient, $title, $body, ['asset_id' => $asset->id, 'type' => 'asset_disposed']);
                $recipient->notify(new AssetNotification($asset, 'disposed'));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send disposal notification', ['error' => $e->getMessage(), 'asset_id' => $asset->id]);
            return false;
        }
    }

    public function notifyWarrantyExpiring(Asset $asset): bool
    {
        try {
            $recipients = $this->getAdminHrUsers();
            if ($asset->current_assignee_id) {
                $custodian = User::find($asset->current_assignee_id);
                if ($custodian) {
                    $recipients = $recipients->push($custodian)->unique('id');
                }
            }

            foreach ($recipients as $recipient) {
                $title = '⏰ Asset Warranty Expiring';
                $body = "The warranty for {$asset->name} ({$asset->asset_code}) is expiring soon.";
                $this->sendNotification($recipient, $title, $body, ['asset_id' => $asset->id, 'type' => 'asset_warranty_expiring']);
                $recipient->notify(new AssetNotification($asset, 'warranty_expiring'));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send warranty-expiring notification', ['error' => $e->getMessage(), 'asset_id' => $asset->id]);
            return false;
        }
    }

    /**
     * Core method to send an FCM push notification — identical token
     * handling to RequestNotificationService::sendNotification().
     */
    private function sendNotification($user, $title, $body, $data = [])
    {
        $tokens = $user->fcm_tokens ?? [];

        if (is_string($tokens)) {
            $decoded = json_decode($tokens, true);
            $tokens = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($tokens) || empty($tokens)) {
            return false;
        }

        $successCount = 0;

        foreach ($tokens as $tokenData) {
            $token = is_array($tokenData) ? ($tokenData['token'] ?? '') : $tokenData;

            if (empty($token)) {
                continue;
            }

            $result = $this->firebaseService->sendToDevice($token, $title, $body, $data);

            if ($result['success'] ?? false) {
                $successCount++;
            }
        }

        return $successCount > 0;
    }

    private function getAdminHrUsers()
    {
        return User::whereIn('role', ['admin', 'hr'])->where('status', 1)->get();
    }
}
