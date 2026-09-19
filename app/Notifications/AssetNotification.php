<?php

namespace App\Notifications;

use App\Models\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AssetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Asset $asset;
    protected string $action;
    protected ?string $remarks;

    public function __construct(Asset $asset, string $action, ?string $remarks = null)
    {
        $this->asset = $asset;
        $this->action = $action;
        $this->remarks = $remarks;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $baseData = [
            'type' => 'asset',
            'action' => $this->action,
            'asset_id' => $this->asset->id,
            'asset_code' => $this->asset->asset_code,
            'asset_name' => $this->asset->name,
            'status' => $this->asset->status,
            'created_at' => now()->toDateTimeString(),
        ];

        switch ($this->action) {
            case 'assigned':
                $baseData['title'] = '📦 Asset Assigned';
                $baseData['message'] = "You've been assigned {$this->asset->name} ({$this->asset->asset_code}). Please accept it.";
                break;
            case 'transferred':
                $baseData['title'] = '📦 Asset Transferred to You';
                $baseData['message'] = "{$this->asset->name} ({$this->asset->asset_code}) has been transferred to you. Please accept it.";
                break;
            case 'accepted':
                $baseData['title'] = '✅ Asset Accepted';
                $baseData['message'] = "{$this->asset->name} ({$this->asset->asset_code}) has been accepted by the assignee.";
                break;
            case 'return_requested':
                $baseData['title'] = '↩️ Asset Return Requested';
                $baseData['message'] = "A return has been requested for {$this->asset->name} ({$this->asset->asset_code}).";
                break;
            case 'repair_status_changed':
                $baseData['title'] = '🔧 Asset Repair Update';
                $baseData['message'] = "{$this->asset->name} ({$this->asset->asset_code}) repair status changed to {$this->asset->status}.";
                break;
            case 'disposed':
                $baseData['title'] = '🗑️ Asset Disposed';
                $baseData['message'] = "{$this->asset->name} ({$this->asset->asset_code}) has been disposed.";
                break;
            case 'warranty_expiring':
                $baseData['title'] = '⏰ Asset Warranty Expiring';
                $baseData['message'] = "The warranty for {$this->asset->name} ({$this->asset->asset_code}) is expiring soon.";
                break;
            default:
                $baseData['title'] = 'Asset Update';
                $baseData['message'] = "{$this->asset->name} ({$this->asset->asset_code}) was updated.";
        }

        if ($this->remarks) {
            $baseData['remarks'] = $this->remarks;
        }

        return $baseData;
    }
}
