<?php

namespace App\Http\Requests\Expense;

use App\Services\Expense\ExpenseAttachmentService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Shared by the web panel and the mobile API so both enforce identical rules
 * (previously the API accepted any file type and negative amounts, and the
 * web form accepted an amount of 0). Failure responses keep each surface's
 * existing envelope: web -> 422 {success:false, errors}; legacy mobile API ->
 * HTTP 200 {success:false, message} per docs/architecture.md.
 */
class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // An advance is a request for money in future, so its date may be
        // ahead of today; a settlement/reimbursement records spend that has
        // already happened.
        $date = $this->input('requirement_type') === 'advance'
            ? ['required', 'date']
            : ['required', 'date', 'before_or_equal:today'];

        return [
            'expense_type' => 'required|exists:expense_types,id',
            'project_id' => 'nullable|exists:projects,id',
            'requirement_type' => 'required|in:advance,settlement,reimbursement',
            'description' => 'nullable|string|max:1000',
            'file' => 'nullable|file|' . ExpenseAttachmentService::rule(),
            // Extra receipts (the web form sends files[]; the mobile app keeps using the single `file`).
            'files' => 'nullable|array|max:' . ExpenseAttachmentService::MAX_FILES,
            'files.*' => 'file|' . ExpenseAttachmentService::rule(),
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'date' => $date,
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.gt' => 'The amount must be greater than zero.',
            'amount.regex' => 'The amount can have at most 2 decimal places.',
            'amount.max' => 'The amount is too large.',
            'file.mimes' => 'The receipt must be a JPG, PNG or PDF file.',
            'file.max' => 'The receipt may not be larger than 5 MB.',
            'files.max' => 'You can attach at most ' . ExpenseAttachmentService::MAX_FILES . ' receipts.',
            'files.*.mimes' => 'Every receipt must be a JPG, PNG or PDF file.',
            'files.*.max' => 'Each receipt may not be larger than 5 MB.',
            'date.before_or_equal' => 'The expense date cannot be in the future.',
        ];
    }

    /**
     * Every uploaded receipt in the request, in order: the single `file` first (legacy / mobile),
     * then `files[]`. ExpenseService treats the first as the primary receipt.
     *
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    public function receipts(): array
    {
        $files = [];

        if ($this->hasFile('file')) {
            $files[] = $this->file('file');
        }
        foreach ((array) $this->file('files', []) as $f) {
            if ($f) {
                $files[] = $f;
            }
        }

        return $files;
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->is('api/*')) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 200));
        }

        throw new HttpResponseException(response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422));
    }
}
