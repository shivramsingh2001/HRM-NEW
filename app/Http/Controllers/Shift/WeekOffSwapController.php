<?php

namespace App\Http\Controllers\Shift;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shift\StoreWeekOffSwapRequest;
use App\Models\WeekoffSwap;
use App\Services\Shift\WeekOffSwapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Shift → Week-off Swaps (admin / HR): employees work a week-off and get
 * another day off instead. Rules live in WeekOffSwapService.
 */
class WeekOffSwapController extends Controller
{
    public function __construct(private WeekOffSwapService $swaps)
    {
    }

    public function index()
    {
        return view('client.shift.weekoff-swaps', [
            'swaps' => WeekoffSwap::with(['employees.user:id,name,employee_id', 'creator:id,name'])
                ->orderByDesc('worked_date')->orderByDesc('id')->limit(200)->get(),
        ]);
    }

    /** Employees whose week-off is the worked day, and whether the day off suits each. */
    public function candidates(Request $request)
    {
        $data = $request->validate([
            'worked_date' => 'required|date_format:Y-m-d',
            'replacement_date' => 'nullable|date_format:Y-m-d',
        ]);

        return response()->json(['status' => true, 'data' => $this->swaps->candidates(
            (int) Auth::user()->tenant_id, $data['worked_date'], $data['replacement_date'] ?? null
        )]);
    }

    public function store(StoreWeekOffSwapRequest $request)
    {
        $data = $request->validated();
        $result = $this->swaps->create((int) Auth::user()->tenant_id, (int) Auth::id(), $data['worked_date'],
            $data['replacement_date'], $data['reason'] ?? null, $data['employees']);

        $message = $result['applied'] . ' employee(s) get their day off instead.';
        if ($result['skipped']) {
            $message .= ' Skipped ' . count($result['skipped']) . ': '
                . collect($result['skipped'])->map(fn ($s) => "{$s['name']} ({$s['reason']})")->implode('; ') . '.';
        }

        return response()->json(['status' => $result['applied'] > 0, 'message' => $message, 'skipped' => $result['skipped']],
            $result['applied'] > 0 ? 200 : 422);
    }

    /** Cancel the whole swap, or one employee's part of it (user_id). */
    public function cancel(Request $request, $id)
    {
        $data = $request->validate(['user_id' => 'nullable|integer']);
        $swap = WeekoffSwap::findOrFail($id);
        $count = $this->swaps->cancel($swap, (int) Auth::id(), $data['user_id'] ?? null);

        return response()->json(['status' => true, 'message' => $count ? "Cancelled for {$count} employee(s); their shift is back." : 'Nothing left to cancel.']);
    }
}
