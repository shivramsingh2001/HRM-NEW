<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportScheduleRequest;
use App\Models\ReportSchedule;
use App\Services\Reports\ReportScheduleService;
use App\Services\Reports\ReportScheduleTiming;
use App\Support\ReportCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Reports → Scheduled Reports (2026-11-10): email a report's export on a
 * schedule. Admin + HR only (route middleware). The mail itself goes out from
 * `reports:send-scheduled` (routes/console.php), never from a web request.
 */
class ReportScheduleController extends Controller
{
    public function __construct(private ReportScheduleService $schedules) {}

    public function index(): View
    {
        $user = Auth::user();
        $list = $this->schedules->list((int) $user->tenant_id);

        return view('client.report.schedules.index', [
            'schedules' => $list,
            'summary' => $this->schedules->summary($list),
            'options' => $this->schedules->formOptions($user),
            'periodLabels' => ReportCatalog::PERIODS,
        ] + ['describe' => fn (ReportSchedule $s) => ReportScheduleTiming::describe($s)]);
    }

    public function store(ReportScheduleRequest $request): JsonResponse
    {
        $s = $this->schedules->save(null, $request->validated(), Auth::user());

        return response()->json(['status' => true, 'message' => "Schedule \"{$s->name}\" created. Next send: ".($s->next_run_at?->format('d M Y, h:i A') ?? '—').'.']);
    }

    public function update(ReportScheduleRequest $request, int $id): JsonResponse
    {
        $s = $this->schedules->save($this->find($id), $request->validated(), Auth::user());

        return response()->json(['status' => true, 'message' => "Schedule \"{$s->name}\" updated. Next send: ".($s->next_run_at?->format('d M Y, h:i A') ?? '—').'.']);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $s = $this->schedules->setActive($this->find($id), $request->boolean('active'));

        return response()->json(['status' => true, 'message' => $s->is_active ? "\"{$s->name}\" resumed." : "\"{$s->name}\" paused."]);
    }

    public function sendNow(int $id): JsonResponse
    {
        $s = $this->find($id);
        if ($why = ReportCatalog::denialReason($s->report_key, Auth::user())) {
            return response()->json(['status' => false, 'message' => $why], 422);
        }
        $this->schedules->requestSendNow($s);

        return response()->json(['status' => true, 'message' => "\"{$s->name}\" will be sent within a minute. Check the run log for the result."]);
    }

    public function runs(int $id): JsonResponse
    {
        $runs = $this->find($id)->runs()->limit(ReportSchedule::RUNS_KEPT)->get()->map(fn ($r) => [
            'at' => $r->started_at->format('d M Y, h:i A'),
            'trigger' => $r->trigger === 'manual' ? 'Send now' : 'Scheduled',
            'status' => $r->status,
            'period' => $r->period_label,
            'file' => $r->file_name,
            'size' => $r->file_size !== null ? number_format($r->file_size / 1024, 1).' KB' : null,
            'recipients' => count((array) $r->recipients),
            'error' => $r->error,
        ]);

        return response()->json(['status' => true, 'runs' => $runs]);
    }

    public function destroy(int $id): JsonResponse
    {
        $s = $this->find($id);
        $this->schedules->delete($s);

        return response()->json(['status' => true, 'message' => "Schedule \"{$s->name}\" deleted."]);
    }

    private function find(int $id): ReportSchedule
    {
        return ReportSchedule::query()->where('tenant_id', (int) Auth::user()->tenant_id)->findOrFail($id);
    }
}
