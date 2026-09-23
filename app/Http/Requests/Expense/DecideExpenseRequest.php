<?php

namespace App\Http\Requests\Expense;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Approve / reject an expense. `complete` is deliberately NOT accepted any
 * more: it only ever worked from `pending` (bypassing approval and payment)
 * and its metadata columns never existed. An expense now completes
 * automatically once it is fully paid (PaymentController).
 */
class DecideExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'status' => 'required|in:approved,cancelled',
            'remarks' => 'nullable|string|max:500',
            // Opt-in, per approval: a settlement bigger than the employee's advance balance is split —
            // the advance covers what it can and the rest becomes a payable reimbursement.
            'cover_shortfall' => 'nullable|boolean',
        ];

        // The mobile API sends the expense id in the body; the web route carries it in the URL.
        if ($this->is('api/*')) {
            $rules['id'] = 'required|exists:expenses,id';
        }

        return $rules;
    }

    protected function failedValidation(Validator $validator): void
    {
        $status = $this->is('api/*') ? 200 : 422;

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
        ], $status));
    }
}
