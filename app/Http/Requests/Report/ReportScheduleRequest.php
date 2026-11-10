<?php

namespace App\Http\Requests\Report;

use App\Support\ReportCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Reports → Scheduled Reports → Add / Edit schedule. Recipients come in as one
 * text box (comma / semicolon / new line separated); "send to me" adds the
 * saver's own email. Any address is allowed (decision 2026-11-10), up to 20.
 */
class ReportScheduleRequest extends FormRequest
{
    public const MAX_RECIPIENTS = 20;

    /** Route middleware (role:admin,hr) already decides who may get here. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $emails = preg_split('/[\s,;]+/', strtolower((string) $this->input('recipients_text')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($this->boolean('send_to_me') && $this->user()?->email) {
            $emails[] = strtolower((string) $this->user()->email);
        }

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'recipients' => array_values(array_unique($emails)),
            'subject' => $this->filled('subject') ? trim((string) $this->input('subject')) : null,
            'message' => $this->filled('message') ? trim((string) $this->input('message')) : null,
            'send_time' => substr((string) $this->input('send_time'), 0, 5),
            'branch_id' => $this->filled('branch_id') ? $this->input('branch_id') : null,
            'department_id' => $this->filled('department_id') ? $this->input('department_id') : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = (int) $this->user()->tenant_id;

        return [
            'name' => 'required|string|max:150',
            'report_key' => ['required', 'string', function ($attr, $value, $fail) {
                if (! ReportCatalog::exists((string) $value)) {
                    $fail('Choose a report.');
                } elseif ($why = ReportCatalog::denialReason((string) $value, $this->user())) {
                    $fail($why);
                }
            }],
            'period' => ['nullable', Rule::in(array_keys(ReportCatalog::PERIODS))],
            'branch_id' => ['nullable', 'integer', Rule::exists('company_branches', 'id')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'weekdays' => 'nullable|array',
            'weekdays.*' => 'integer|between:0,6',
            'weekly_day' => 'required_if:frequency,weekly|nullable|integer|between:0,6',
            'day_of_month' => 'required_if:frequency,monthly|nullable|integer|between:0,28',
            'send_time' => ['required', 'date_format:H:i'],
            'recipients' => 'required|array|min:1|max:'.self::MAX_RECIPIENTS,
            'recipients.*' => 'email:filter|max:191',
            'subject' => 'nullable|string|max:191',
            'message' => 'nullable|string|max:2000',
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            // Recipient errors are shown under the one text box.
            foreach ($v->errors()->keys() as $key) {
                if (str_starts_with($key, 'recipients.')) {
                    $bad = $this->input('recipients')[(int) substr($key, 11)] ?? '';
                    $v->errors()->add('recipients_text', "\"{$bad}\" is not a valid email address.");
                }
            }
            if ($v->errors()->has('recipients')) {
                $v->errors()->add('recipients_text', $v->errors()->first('recipients'));
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'recipients.required' => 'Add at least one email address (or tick "Send to me").',
            'recipients.min' => 'Add at least one email address (or tick "Send to me").',
            'recipients.max' => 'Up to '.self::MAX_RECIPIENTS.' email addresses per schedule.',
            'send_time.date_format' => 'Pick the time to send.',
            'weekly_day.required_if' => 'Pick the day of the week.',
            'day_of_month.required_if' => 'Pick the day of the month.',
        ];
    }
}
