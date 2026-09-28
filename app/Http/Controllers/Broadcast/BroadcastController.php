<?php

namespace App\Http\Controllers\Broadcast;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\CompanyBranch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\User;
use App\Services\Broadcast\BroadcastComposerService;
use App\Services\FeatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Tenant Admin composer — thin controller (validate → service → respond),
 * matching Announcement\AnnouncementController's structural weight (no
 * money/ledger locking concern here, unlike Expense).
 */
class BroadcastController extends Controller
{
    public function __construct(protected BroadcastComposerService $composer)
    {
    }

    /**
     * Single page: history list (with stat cards + filters) AND the
     * "Send Broadcast" composer, which now lives in a slide-over drawer on
     * this same page (see resources/views/client/broadcast/partials/
     * send-drawer.blade.php) instead of a separate /broadcast/create route.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Broadcast::query()->mine($user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where('title', 'like', "%{$search}%");
        }
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }

        $broadcasts = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        // Stat cards reflect ALL of this admin's broadcasts, not just the
        // current filter/page — a stable "at a glance" summary, matching
        // Leave Credit Management's KPI-card convention (overall figures,
        // not a filtered subset).
        $statsBase = Broadcast::mine($user->id);
        $stats = [
            'total' => (clone $statsBase)->count(),
            'sent' => (clone $statsBase)->where('status', 'sent')->count(),
            'scheduled' => (clone $statsBase)->where('status', 'scheduled')->count(),
            'recipients' => (int) (clone $statsBase)->sum('total_recipients'),
        ];

        $branchesEnabled = app(FeatureService::class)->enabledForCurrentTenant('branches');

        return view('client.broadcast.index', [
            'broadcasts' => $broadcasts,
            'stats' => $stats,
            'filters' => $request->query(),
            'statuses' => Broadcast::STATUSES,
            // composer drawer data:
            'departments' => Department::where('status', 1)->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::where('status', 1)->orderBy('name')->get(['id', 'name']),
            'branchesEnabled' => $branchesEnabled,
            'branches' => $branchesEnabled ? CompanyBranch::where('status', 1)->orderBy('name')->get(['id', 'name']) : collect(),
            'users' => User::where('status', 1)->where('id', '!=', $user->id)->orderBy('name')->get(['id', 'name', 'employee_id']),
            // SMS is DLT-template-gated (see App\Channels\SmsChannel) — shown
            // but disabled with an explanation until an operator configures
            // DLT_TEMPLATE_ID_BROADCAST, rather than silently hidden.
            'smsAvailable' => (bool) config('sms.airtel.broadcast_dlt_template_id'),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->composer->validate($request);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }

        try {
            $broadcast = $this->composer->create(Auth::user(), $validated);
        } catch (\Throwable $e) {
            Log::error('Broadcast store error: ' . $e->getMessage(), ['user_id' => Auth::id()]);

            return response()->json(['success' => false, 'message' => 'Failed to send broadcast. Please try again.'], 500);
        }

        $message = match ($broadcast->status) {
            'scheduled' => 'Broadcast scheduled.',
            'sending' => 'Broadcast is being sent to ' . $broadcast->total_recipients . ' recipient(s).',
            default => 'Broadcast sent.',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['id' => $broadcast->id, 'status' => $broadcast->status, 'total_recipients' => $broadcast->total_recipients],
        ]);
    }

    public function previewCount(Request $request)
    {
        try {
            $validated = $this->composer->validateAudienceOnly($request);
        } catch (ValidationException $e) {
            // Preview is best-effort while the admin is still composing —
            // an incomplete/invalid combination just shows 0, not an error.
            return response()->json(['success' => true, 'recipient_count' => 0]);
        }

        $count = $this->composer->countRecipients($validated, Auth::id());

        return response()->json(['success' => true, 'recipient_count' => $count]);
    }

    public function show(int $id)
    {
        $user = Auth::user();

        $broadcast = Broadcast::mine($user->id)->findOrFail($id);

        $stats = [
            'total' => BroadcastRecipient::where('broadcast_id', $broadcast->id)->count(),
            'delivered' => BroadcastRecipient::where('broadcast_id', $broadcast->id)->whereNotNull('delivered_at')->count(),
            'read' => BroadcastRecipient::where('broadcast_id', $broadcast->id)->whereNotNull('read_at')->count(),
            'clicked' => BroadcastRecipient::where('broadcast_id', $broadcast->id)->whereNotNull('action_clicked_at')->count(),
        ];

        return view('client.broadcast.show', compact('broadcast', 'stats'));
    }

    public function cancel(int $id)
    {
        $user = Auth::user();

        $broadcast = Broadcast::mine($user->id)->findOrFail($id);

        if (! in_array($broadcast->status, ['draft', 'scheduled'], true)) {
            return response()->json(['success' => false, 'message' => 'Only a draft or scheduled broadcast can be cancelled.'], 422);
        }

        $broadcast->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'Broadcast cancelled.']);
    }
}
