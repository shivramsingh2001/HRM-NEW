<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollArrears;
use Illuminate\Http\Request;

/**
 * Payroll rebuild — Phase 6.
 *
 * Read-mostly view of arrears queued by PayrollArrearsCalculator (see
 * PayrollEmployeeStructureController::store() and
 * PayrollRevisionApprovalHandler::approved(), which both call it whenever
 * a revision's effective_from is backdated into an already-processed
 * month). PayrollCalculationEngine folds 'pending' rows into the next
 * payslip it computes for that employee.
 */
class PayrollArrearsController extends Controller
{
    public function index(Request $request)
    {
        $query = PayrollArrears::with(['user', 'component']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending'); // default view: what's still outstanding
        }

        $arrears = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('client.payroll.arrears.index', compact('arrears'));
    }

    public function cancel($id)
    {
        $row = PayrollArrears::findOrFail($id);

        if ($row->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending arrears can be cancelled.');
        }

        $row->update(['status' => 'cancelled']);

        return redirect()->route('payroll-arrears.index')->with('success', 'Arrears entry cancelled.');
    }
}
