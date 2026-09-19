<?php

namespace App\Http\Controllers\Asset;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetDamageReport;
use App\Models\AssetRepair;
use App\Services\Asset\AssetLifecycleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrappers around AssetLifecycleService — one action per lifecycle
 * transition, mirroring how TeamController::markAttendance() wraps its own
 * state change. All business rules (legal-transition checks, history/audit/
 * notification writes) live in the service; this controller only resolves
 * the model, checks who's allowed to act, and translates the result/
 * exception into a response.
 */
class AssetLifecycleController extends Controller
{
    public function __construct(protected AssetLifecycleService $lifecycle)
    {
    }

    public function assign(Request $request, $id)
    {
        $asset = Asset::findOrFail(decrypt($id));

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'expected_return_date' => 'nullable|date',
            'remarks' => 'nullable|string|max:1000',
        ]);

        try {
            $this->lifecycle->assign($asset, (int) $request->user_id, Auth::user(), $request->only(['expected_return_date', 'remarks']));

            return response()->json(['success' => true, 'message' => 'Asset assigned. Awaiting employee acceptance.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Asset assign error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to assign asset.'], 500);
        }
    }

    public function accept(Request $request, $id)
    {
        $assignment = AssetAssignment::with('asset')->findOrFail(decrypt($id));
        $actor = Auth::user();

        $isSelf = $actor->id === $assignment->user_id;
        $canForce = app(\App\Services\RbacService::class)->can($actor, 'assets', 'manage');

        if (!$isSelf && !$canForce) {
            return response()->json(['success' => false, 'message' => 'Only the assignee (or an admin/HR user) can accept this asset.'], 403);
        }

        $request->validate(['note' => 'nullable|string|max:1000']);

        try {
            $this->lifecycle->accept($assignment, $actor, $request->note);

            return response()->json(['success' => true, 'message' => 'Asset accepted.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Asset accept error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to accept asset.'], 500);
        }
    }

    public function returnAsset(Request $request, $id)
    {
        $assignment = AssetAssignment::with('asset')->findOrFail(decrypt($id));
        $actor = Auth::user();

        $isSelf = $actor->id === $assignment->user_id;
        $canManage = app(\App\Services\RbacService::class)->can($actor, 'assets', 'manage');

        if (!$isSelf && !$canManage) {
            return response()->json(['success' => false, 'message' => 'You are not allowed to return this asset.'], 403);
        }

        $request->validate([
            'return_condition' => 'nullable|in:new,good,fair,poor,damaged',
            'remarks' => 'nullable|string|max:1000',
        ]);

        try {
            $this->lifecycle->returnAsset($assignment, $actor, $request->only(['return_condition', 'remarks']));

            return response()->json(['success' => true, 'message' => 'Asset returned.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Asset return error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to return asset.'], 500);
        }
    }

    public function transfer(Request $request, $id)
    {
        $asset = Asset::findOrFail(decrypt($id));

        $request->validate([
            'to_user_id' => 'required|exists:users,id',
            'to_branch_id' => 'nullable|exists:company_branches,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $this->lifecycle->transfer($asset, (int) $request->to_user_id, Auth::user(), $request->only(['to_branch_id', 'reason']));

            return response()->json(['success' => true, 'message' => 'Asset transferred. Awaiting new assignee acceptance.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Asset transfer error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to transfer asset.'], 500);
        }
    }

    public function sendForRepair(Request $request, $id)
    {
        $asset = Asset::findOrFail(decrypt($id));

        $request->validate([
            'issue_description' => 'nullable|string|max:2000',
            'sent_to_vendor_id' => 'nullable|exists:vendors,id',
            'sent_at' => 'nullable|date',
            'expected_return_date' => 'nullable|date',
        ]);

        try {
            $this->lifecycle->sendForRepair($asset, Auth::user(), $request->only([
                'issue_description', 'sent_to_vendor_id', 'sent_at', 'expected_return_date',
            ]));

            return response()->json(['success' => true, 'message' => 'Asset sent for repair.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Asset send-for-repair error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to send asset for repair.'], 500);
        }
    }

    public function completeRepair(Request $request, $id)
    {
        $repair = AssetRepair::with('asset')->findOrFail(decrypt($id));

        $request->validate([
            'status' => 'nullable|in:completed,cancelled',
            'repair_cost' => 'nullable|numeric|min:0',
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        try {
            $this->lifecycle->completeRepair($repair, Auth::user(), $request->only(['status', 'repair_cost', 'resolution_notes']));

            return response()->json(['success' => true, 'message' => 'Repair marked complete.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Asset complete-repair error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to complete repair.'], 500);
        }
    }

    public function reportDamage(Request $request, $id)
    {
        $asset = Asset::findOrFail(decrypt($id));

        $request->validate([
            'type' => 'required|in:damaged,lost',
            'description' => 'nullable|string|max:2000',
            'estimated_loss_value' => 'nullable|numeric|min:0',
            'is_chargeable' => 'nullable|boolean',
        ]);

        try {
            $this->lifecycle->reportDamageOrLost($asset, Auth::user(), $request->only([
                'type', 'description', 'estimated_loss_value', 'is_chargeable',
            ]));

            return response()->json(['success' => true, 'message' => 'Report recorded.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Asset damage-report error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to record report.'], 500);
        }
    }

    public function resolveDamage(Request $request, $id)
    {
        $report = AssetDamageReport::with('asset')->findOrFail(decrypt($id));

        $request->validate([
            'resolution' => 'required|in:written_off,repaired,replaced,recovered',
            'charged_amount' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
        ]);

        try {
            $this->lifecycle->resolveDamageReport($report, Auth::user(), $request->only(['resolution', 'charged_amount', 'remarks']));

            return response()->json(['success' => true, 'message' => 'Damage/loss report resolved.']);
        } catch (Exception $e) {
            Log::error('Asset resolve-damage error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to resolve report.'], 500);
        }
    }

    public function retire(Request $request, $id)
    {
        $asset = Asset::findOrFail(decrypt($id));

        $request->validate(['reason' => 'nullable|string|max:1000']);

        try {
            $this->lifecycle->retire($asset, Auth::user(), $request->reason);

            return response()->json(['success' => true, 'message' => 'Asset retired.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Asset retire error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to retire asset.'], 500);
        }
    }

    public function dispose(Request $request, $id)
    {
        $asset = Asset::findOrFail(decrypt($id));

        $request->validate([
            'method' => 'nullable|in:sold,scrapped,donated,write_off,other',
            'sale_value' => 'nullable|numeric|min:0',
            'buyer_or_recipient' => 'nullable|string|max:150',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $this->lifecycle->dispose($asset, Auth::user(), $request->only([
                'method', 'sale_value', 'buyer_or_recipient', 'reason',
            ]));

            return response()->json(['success' => true, 'message' => 'Asset disposed.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Asset dispose error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to dispose asset.'], 500);
        }
    }
}
