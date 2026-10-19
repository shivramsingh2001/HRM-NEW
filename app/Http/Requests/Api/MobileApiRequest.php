<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base for legacy mobile API (/api, JWT) form requests: a failed validation
 * answers in that API's envelope — 422 { success: false, message: first error,
 * errors } — the same shape the existing mobile controllers return.
 */
abstract class MobileApiRequest extends FormRequest
{
    /** Route middleware decides who may get here. */
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
