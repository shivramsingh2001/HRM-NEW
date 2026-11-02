<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Site\SiteException;
use App\Services\Site\SiteImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sites → Import (admin / HR): template, preview, confirm, failed-rows file.
 * Rules live in SiteImportService.
 */
class SiteImportController extends Controller
{
    public function __construct(private SiteImportService $import) {}

    public function template()
    {
        return response()->download($this->import->templateFile(), 'site_import_template.xlsx')->deleteFileAfterSend();
    }

    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|max:5120|mimes:xlsx,csv,txt']);

        try {
            $result = $this->import->preview($request->file('file'), (int) Auth::user()->tenant_id, (int) Auth::id());
        } catch (SiteException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => true] + $result);
    }

    public function confirm(Request $request)
    {
        $data = $request->validate(['token' => 'required|string|size:32']);

        try {
            $result = $this->import->confirm($data['token'], (int) Auth::user()->tenant_id, Auth::user());
        } catch (SiteException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        $message = $result['created'] === 0 ? 'No sites were imported.'
            : ($result['created'] === 1 ? "1 site imported ({$result['first_code']})."
                : "{$result['created']} sites imported ({$result['first_code']} to {$result['last_code']}).");
        if ($result['skipped']) {
            $message .= " {$result['skipped']} skipped because the same site was added in the meantime.";
        }

        return response()->json(['status' => true, 'message' => $message] + $result);
    }

    public function errors(string $token)
    {
        try {
            $path = $this->import->errorFile($token, (int) Auth::user()->tenant_id, (int) Auth::id());
        } catch (SiteException $e) {
            return redirect()->route('sites.index')->with('error', $e->getMessage());
        }

        return response()->download($path, 'site_import_errors.xlsx')->deleteFileAfterSend();
    }
}
