<?php

namespace App\Services\Asset;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetDamageReport;
use App\Models\AssetDisposal;
use App\Models\AssetHistory;
use App\Models\AssetRepair;
use App\Models\AssetTransfer;
use App\Models\User;
use App\Services\AssetNotificationService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Single choke point for every Asset lifecycle transition (register, assign,
 * accept, return, transfer, repair, damage/loss, retire, dispose). Every
 * method: runs inside a DB transaction, validates the transition is legal
 * from the asset's current status, writes one asset_histories row, calls
 * the tenant-wide AuditLogger, and fires the relevant notification — the
 * same "domain history + audit log + notification" triple-write pattern
 * the Requests module already uses.
 *
 * Deliberately NOT built on the generic multi-level ApprovalService: that
 * engine is purpose-built for hierarchical manager/HR approval chains with
 * a single yes/no outcome, not a multi-branch custody state machine. Per
 * this module's confirmed design, transfer/disposal are direct actions
 * (gated by RBAC permission, not a second approver) and are fully recorded
 * here instead.
 */
class AssetLifecycleService
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected AssetNotificationService $notifications,
    ) {
    }

    /* ============================================================
     |  REGISTRATION
     ============================================================ */

    public function register(array $data, User $actor): Asset
    {
        return DB::transaction(function () use ($data, $actor) {
            $tenantId = (int) $actor->tenant_id;
            $categoryCode = $data['asset_category_id'] ?? null
                ? \App\Models\AssetCategory::find($data['asset_category_id'])?->code
                : null;

            $data['tenant_id'] = $tenantId;
            $data['asset_code'] = $this->generateAssetCode($tenantId, $categoryCode);
            $data['status'] = $data['status'] ?? 'available';
            $data['created_by'] = $actor->id;

            $asset = Asset::create($data);

            $this->writeHistory($asset, AssetHistory::ACTION_REGISTERED, $asset->status, $actor, null, 'Asset registered', [], $asset->toArray());
            $this->auditLogger->record('tenant_user', $actor->id, $tenantId, 'asset.registered', 'Asset', $asset->id, [], $asset->toArray());

            return $asset;
        });
    }

    private function generateAssetCode(int $tenantId, ?string $prefix = null): string
    {
        $prefix = $prefix ?: 'AST';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $count = Asset::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->count();
            $candidate = sprintf('%s-%05d', $prefix, $count + 1 + $attempt);

            $exists = Asset::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('asset_code', $candidate)
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        // Extremely unlikely fallback — guarantees uniqueness even under contention.
        return $prefix . '-' . strtoupper(uniqid());
    }

    /* ============================================================
     |  ASSIGNMENT / ACCEPTANCE / RETURN / TRANSFER
     ============================================================ */

    public function assign(Asset $asset, int $userId, User $actor, array $meta = []): AssetAssignment
    {
        $this->assertTransitionAllowed($asset, ['available'], 'assign');

        return DB::transaction(function () use ($asset, $userId, $actor, $meta) {
            $assignment = AssetAssignment::create([
                'tenant_id' => $asset->tenant_id,
                'asset_id' => $asset->id,
                'user_id' => $userId,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'status' => 'pending_acceptance',
                'expected_return_date' => $meta['expected_return_date'] ?? null,
                'remarks' => $meta['remarks'] ?? null,
            ]);

            $old = ['status' => $asset->status];
            $asset->update(['status' => 'pending_acceptance']);

            $this->writeHistory($asset, AssetHistory::ACTION_ASSIGNED, 'pending_acceptance', $actor, $userId, $meta['remarks'] ?? null, $old, ['status' => 'pending_acceptance', 'assigned_to' => $userId]);
            $this->auditLogger->record('tenant_user', $actor->id, (int) $asset->tenant_id, 'asset.assigned', 'Asset', $asset->id, $old, ['assigned_to' => $userId]);

            if ($assignee = User::find($userId)) {
                $this->notifications->notifyAssetAssigned($asset, $assignee);
            }

            return $assignment;
        });
    }

    public function accept(AssetAssignment $assignment, User $actor, ?string $note = null): AssetAssignment
    {
        $asset = $assignment->asset;
        $this->assertTransitionAllowed($asset, ['pending_acceptance'], 'accept');

        $isSelf = $actor->id === $assignment->user_id;
        $forced = !$isSelf;

        return DB::transaction(function () use ($assignment, $asset, $actor, $note, $forced) {
            $assignment->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_by' => $actor->id,
                'acknowledgement_note' => $note,
            ]);

            $old = ['status' => $asset->status, 'current_assignee_id' => $asset->current_assignee_id];
            $asset->update(['status' => 'assigned', 'current_assignee_id' => $assignment->user_id]);

            $action = $forced ? AssetHistory::ACTION_ACCEPTANCE_FORCED : AssetHistory::ACTION_ACCEPTED;
            $this->writeHistory($asset, $action, 'assigned', $actor, $assignment->user_id, $note, $old, ['status' => 'assigned']);
            $this->auditLogger->record('tenant_user', $actor->id, (int) $asset->tenant_id, 'asset.accepted', 'Asset', $asset->id, $old, ['status' => 'assigned']);

            if ($assignment->assignedBy) {
                $this->notifications->notifyAssetAccepted($asset, $assignment->assignedBy);
            }

            return $assignment;
        });
    }

    public function returnAsset(AssetAssignment $assignment, User $actor, array $meta = []): AssetAssignment
    {
        $asset = $assignment->asset;
        $this->assertTransitionAllowed($asset, ['assigned', 'pending_acceptance'], 'return');

        return DB::transaction(function () use ($assignment, $asset, $actor, $meta) {
            $assignment->update([
                'status' => 'returned',
                'returned_at' => now(),
                'returned_to' => $actor->id,
                'return_condition' => $meta['return_condition'] ?? null,
                'remarks' => $meta['remarks'] ?? $assignment->remarks,
            ]);

            $old = ['status' => $asset->status, 'current_assignee_id' => $asset->current_assignee_id];
            $asset->update(['status' => 'available', 'current_assignee_id' => null]);

            $this->writeHistory($asset, AssetHistory::ACTION_RETURNED, 'available', $actor, $assignment->user_id, $meta['remarks'] ?? null, $old, ['status' => 'available']);
            $this->auditLogger->record('tenant_user', $actor->id, (int) $asset->tenant_id, 'asset.returned', 'Asset', $asset->id, $old, ['status' => 'available']);

            return $assignment;
        });
    }

    public function transfer(Asset $asset, int $toUserId, User $actor, array $meta = []): AssetAssignment
    {
        $this->assertTransitionAllowed($asset, ['assigned'], 'transfer');

        return DB::transaction(function () use ($asset, $toUserId, $actor, $meta) {
            $currentAssignment = $asset->currentAssignment;
            $fromUserId = $currentAssignment?->user_id ?? $asset->current_assignee_id;

            if ($currentAssignment) {
                $currentAssignment->update(['status' => 'transferred']);
            }

            $newAssignment = AssetAssignment::create([
                'tenant_id' => $asset->tenant_id,
                'asset_id' => $asset->id,
                'user_id' => $toUserId,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'status' => 'pending_acceptance',
                'remarks' => $meta['reason'] ?? null,
            ]);

            AssetTransfer::create([
                'tenant_id' => $asset->tenant_id,
                'asset_id' => $asset->id,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'from_branch_id' => $asset->branch_id,
                'to_branch_id' => $meta['to_branch_id'] ?? $asset->branch_id,
                'transferred_by' => $actor->id,
                'transferred_at' => now(),
                'reason' => $meta['reason'] ?? null,
            ]);

            $old = ['status' => $asset->status, 'current_assignee_id' => $asset->current_assignee_id];
            $asset->update([
                'status' => 'pending_acceptance',
                'current_assignee_id' => null,
                'branch_id' => $meta['to_branch_id'] ?? $asset->branch_id,
            ]);

            $this->writeHistory($asset, AssetHistory::ACTION_TRANSFERRED, 'pending_acceptance', $actor, $toUserId, $meta['reason'] ?? null, $old, ['status' => 'pending_acceptance', 'transferred_to' => $toUserId]);
            $this->auditLogger->record('tenant_user', $actor->id, (int) $asset->tenant_id, 'asset.transferred', 'Asset', $asset->id, $old, ['transferred_to' => $toUserId]);

            if ($newAssignee = User::find($toUserId)) {
                $this->notifications->notifyAssetTransferred($asset, $newAssignee);
            }

            return $newAssignment;
        });
    }

    /* ============================================================
     |  REPAIR
     ============================================================ */

    public function sendForRepair(Asset $asset, User $actor, array $meta = []): AssetRepair
    {
        $this->assertTransitionAllowed($asset, ['available', 'assigned'], 'send for repair');

        return DB::transaction(function () use ($asset, $actor, $meta) {
            $repair = AssetRepair::create([
                'tenant_id' => $asset->tenant_id,
                'asset_id' => $asset->id,
                'reported_at' => now(),
                'status' => 'reported',
                'reported_by' => $actor->id,
                'issue_description' => $meta['issue_description'] ?? null,
                'sent_to_vendor_id' => $meta['sent_to_vendor_id'] ?? null,
                'sent_at' => $meta['sent_at'] ?? null,
                'expected_return_date' => $meta['expected_return_date'] ?? null,
            ]);

            $old = ['status' => $asset->status];
            $asset->update(['status' => 'in_repair', 'pre_repair_status' => $asset->status]);

            $this->writeHistory($asset, AssetHistory::ACTION_SENT_FOR_REPAIR, 'in_repair', $actor, $asset->current_assignee_id, $meta['issue_description'] ?? null, $old, ['status' => 'in_repair']);
            $this->auditLogger->record('tenant_user', $actor->id, (int) $asset->tenant_id, 'asset.sent_for_repair', 'Asset', $asset->id, $old, ['status' => 'in_repair']);

            $custodian = $asset->current_assignee_id ? User::find($asset->current_assignee_id) : null;
            $this->notifications->notifyRepairStatusChanged($asset, $custodian);

            return $repair;
        });
    }

    public function completeRepair(AssetRepair $repair, User $actor, array $meta = []): AssetRepair
    {
        $asset = $repair->asset;

        if ($asset->status !== 'in_repair') {
            throw new \InvalidArgumentException('This asset is not currently in repair.');
        }

        return DB::transaction(function () use ($repair, $asset, $actor, $meta) {
            $repair->update([
                'status' => $meta['status'] ?? 'completed',
                'completed_at' => now(),
                'repair_cost' => $meta['repair_cost'] ?? $repair->repair_cost,
                'resolution_notes' => $meta['resolution_notes'] ?? null,
            ]);

            $restoredStatus = $asset->pre_repair_status ?: 'available';
            $old = ['status' => $asset->status];
            $asset->update(['status' => $restoredStatus, 'pre_repair_status' => null]);

            $this->writeHistory($asset, AssetHistory::ACTION_REPAIR_COMPLETED, $restoredStatus, $actor, $asset->current_assignee_id, $meta['resolution_notes'] ?? null, $old, ['status' => $restoredStatus]);
            $this->auditLogger->record('tenant_user', $actor->id, (int) $asset->tenant_id, 'asset.repair_completed', 'Asset', $asset->id, $old, ['status' => $restoredStatus]);

            $custodian = $asset->current_assignee_id ? User::find($asset->current_assignee_id) : null;
            $this->notifications->notifyRepairStatusChanged($asset, $custodian);

            return $repair;
        });
    }

    /* ============================================================
     |  DAMAGE / LOSS
     ============================================================ */

    public function reportDamageOrLost(Asset $asset, User $actor, array $meta): AssetDamageReport
    {
        $type = $meta['type'] ?? 'damaged';
        $this->assertTransitionAllowed($asset, ['available', 'assigned', 'in_repair'], 'report ' . $type);

        return DB::transaction(function () use ($asset, $actor, $meta, $type) {
            $report = AssetDamageReport::create([
                'tenant_id' => $asset->tenant_id,
                'asset_id' => $asset->id,
                'reported_at' => now(),
                'type' => $type,
                'reported_by' => $actor->id,
                'description' => $meta['description'] ?? null,
                'estimated_loss_value' => $meta['estimated_loss_value'] ?? null,
                'is_chargeable' => $meta['is_chargeable'] ?? null,
            ]);

            $newStatus = $type === 'lost' ? 'lost' : 'damaged';
            $old = ['status' => $asset->status];
            $asset->update(['status' => $newStatus]);

            $action = $type === 'lost' ? AssetHistory::ACTION_LOST : AssetHistory::ACTION_DAMAGED;
            $this->writeHistory($asset, $action, $newStatus, $actor, $asset->current_assignee_id, $meta['description'] ?? null, $old, ['status' => $newStatus]);
            $this->auditLogger->record('tenant_user', $actor->id, (int) $asset->tenant_id, 'asset.' . $type, 'Asset', $asset->id, $old, ['status' => $newStatus]);

            return $report;
        });
    }

    public function resolveDamageReport(AssetDamageReport $report, User $actor, array $meta): AssetDamageReport
    {
        $asset = $report->asset;

        return DB::transaction(function () use ($report, $asset, $actor, $meta) {
            $resolution = $meta['resolution'] ?? 'repaired';

            $report->update([
                'resolution' => $resolution,
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
                'charged_amount' => $meta['charged_amount'] ?? $report->charged_amount,
                'remarks' => $meta['remarks'] ?? null,
            ]);

            $newStatus = in_array($resolution, ['written_off', 'replaced'], true) ? 'retired' : 'available';
            $old = ['status' => $asset->status];
            $asset->update(['status' => $newStatus]);

            $this->writeHistory($asset, AssetHistory::ACTION_RESOLVED, $newStatus, $actor, $asset->current_assignee_id, $meta['remarks'] ?? null, $old, ['status' => $newStatus, 'resolution' => $resolution]);
            $this->auditLogger->record('tenant_user', $actor->id, (int) $asset->tenant_id, 'asset.damage_resolved', 'Asset', $asset->id, $old, ['status' => $newStatus]);

            return $report;
        });
    }

    /* ============================================================
     |  RETIREMENT / DISPOSAL
     ============================================================ */

    public function retire(Asset $asset, User $actor, ?string $reason = null): Asset
    {
        $this->assertTransitionAllowed($asset, ['available', 'damaged', 'lost'], 'retire');

        return DB::transaction(function () use ($asset, $actor, $reason) {
            $old = ['status' => $asset->status];
            $asset->update(['status' => 'retired', 'retired_at' => now()]);

            $this->writeHistory($asset, AssetHistory::ACTION_RETIRED, 'retired', $actor, null, $reason, $old, ['status' => 'retired']);
            $this->auditLogger->record('tenant_user', $actor->id, (int) $asset->tenant_id, 'asset.retired', 'Asset', $asset->id, $old, ['status' => 'retired']);

            return $asset;
        });
    }

    public function dispose(Asset $asset, User $actor, array $meta = []): AssetDisposal
    {
        $this->assertTransitionAllowed($asset, ['available', 'retired', 'damaged', 'lost'], 'dispose');

        return DB::transaction(function () use ($asset, $actor, $meta) {
            $disposal = AssetDisposal::create([
                'tenant_id' => $asset->tenant_id,
                'asset_id' => $asset->id,
                'disposed_by' => $actor->id,
                'disposed_at' => now(),
                'method' => $meta['method'] ?? null,
                'sale_value' => $meta['sale_value'] ?? null,
                'buyer_or_recipient' => $meta['buyer_or_recipient'] ?? null,
                'reason' => $meta['reason'] ?? null,
                'approved_by' => $meta['approved_by'] ?? null,
            ]);

            $old = ['status' => $asset->status];
            $asset->update(['status' => 'disposed', 'disposed_at' => now()]);

            $this->writeHistory($asset, AssetHistory::ACTION_DISPOSED, 'disposed', $actor, null, $meta['reason'] ?? null, $old, ['status' => 'disposed']);
            $this->auditLogger->record('tenant_user', $actor->id, (int) $asset->tenant_id, 'asset.disposed', 'Asset', $asset->id, $old, ['status' => 'disposed']);
            $this->notifications->notifyDisposalRecorded($asset);

            return $disposal;
        });
    }

    /* ============================================================
     |  HELPERS
     ============================================================ */

    private function assertTransitionAllowed(Asset $asset, array $allowedFromStatuses, string $action): void
    {
        if (!in_array($asset->status, $allowedFromStatuses, true)) {
            throw new \InvalidArgumentException(
                "Cannot {$action} an asset that is currently \"" . str_replace('_', ' ', $asset->status) . '".'
            );
        }
    }

    private function writeHistory(Asset $asset, string $action, ?string $status, ?User $actor, ?int $relatedUserId, ?string $remarks, array $old = [], array $new = []): AssetHistory
    {
        return AssetHistory::create([
            'tenant_id' => $asset->tenant_id,
            'asset_id' => $asset->id,
            'action' => $action,
            'status' => $status,
            'actor_id' => $actor?->id,
            'related_user_id' => $relatedUserId,
            'remarks' => $remarks,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
        ]);
    }
}
