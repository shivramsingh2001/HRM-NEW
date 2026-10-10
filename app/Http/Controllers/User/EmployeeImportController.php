<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Employee import from a spreadsheet + its template. Moved out of UserController
 * unchanged (code-quality plan, Phase 4); route names are the same.
 */
class EmployeeImportController extends Controller
{
    /**
     * Bulk import employees (users table only) from an Excel/CSV sheet with
     * Name, Email, Mobile No, Password. All-or-nothing — see EmployeeImportService.
     */
    public function importEmployees(Request $request, \App\Services\User\EmployeeImportService $importer)
    {
        $request->validate([
            'file' => 'required|file|max:5120|mimes:xlsx,csv,txt',
        ]);

        try {
            $result = $importer->import($request->file('file'), (int) auth()->user()->tenant_id);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Employee import failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Import failed. Please check the file and try again.'], 500);
        }

        if ($result['errors']) {
            return response()->json([
                'success' => false,
                'message' => 'Nothing was imported. Please fix the rows below and upload again.',
                'errors' => $result['errors'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['created'].' employee(s) imported successfully.',
        ]);
    }

    /**
     * Sample sheet for the employee import (CSV — opens in Excel).
     */
    public function importTemplate()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Name', 'Email', 'Mobile No', 'Password']);
            fputcsv($out, ['Rahul Sharma', 'rahul.sharma@example.com', '9876543210', 'Pass@123']);
            fclose($out);
        }, 'employee_import_template.csv', ['Content-Type' => 'text/csv']);
    }
}
