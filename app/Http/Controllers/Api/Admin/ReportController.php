<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ReportScheduleException;
use App\Http\Controllers\Controller;
use App\Services\Reports\MobileReportService;
use App\Support\ReportCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Mobile admin API — Reports (Admin / HR): the reports this user may open, one
 * report as a table (columns + rows), and the same report as its CSV / XLSX file.
 */
class ReportController extends Controller
{
    public function __construct(private MobileReportService $reports) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->reports->catalog(Auth::user())]);
    }

    public function show(Request $request, string $key): Response
    {
        $input = $this->input($request) + $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return $this->run($key, fn () => response()->json([
            'success' => true,
            'data' => $this->reports->table(Auth::user(), app('current_tenant'), $key, $input),
        ]));
    }

    public function download(Request $request, string $key): Response
    {
        $input = $this->input($request);

        return $this->run($key, function () use ($key, $input) {
            $file = $this->reports->file(Auth::user(), app('current_tenant'), $key, $input);

            return response($file['content'], 200, [
                'Content-Type' => $file['mime'],
                'Content-Disposition' => 'attachment; filename="'.addcslashes($file['filename'], '"\\').'"',
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function input(Request $request): array
    {
        return $request->validate([
            'period' => ['nullable', 'string', 'max:30'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ]);
    }

    /** Unknown report 404; refused 403 with the reason; bad period 422; anything else 500. */
    private function run(string $key, callable $produce): Response
    {
        if (! ReportCatalog::exists($key)) {
            return response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }

        try {
            return $produce();
        } catch (ReportScheduleException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->pause ? 403 : 422);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            // abort() inside the report (e.g. its role / permission check).
            return response()->json(['success' => false, 'message' => $e->getMessage() ?: 'You cannot open this report.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not produce the report. Please try again.'], 500);
        }
    }
}
