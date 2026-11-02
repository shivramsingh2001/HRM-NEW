<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sites → Add / Edit site. The code is system-generated, so it is never
 * accepted here. The same name + address twice in one company is refused.
 */
class SiteRequest extends FormRequest
{
    /** Route middleware (role:admin,hr + permission:sites,*) already decides who may get here. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'site_name' => trim((string) $this->input('site_name')),
            'address' => trim((string) $this->input('address')),
            'district' => $this->filled('district') ? trim((string) $this->input('district')) : null,
            'location' => $this->filled('location') ? trim((string) $this->input('location')) : null,
        ]);
    }

    public function rules(): array
    {
        $siteId = $this->route('id');

        return [
            'site_name' => [
                'required', 'string', 'max:191',
                Rule::unique('sites', 'site_name')
                    ->where('tenant_id', (int) $this->user()->tenant_id)
                    ->where('address', (string) $this->input('address'))
                    ->whereNull('deleted_at')
                    ->ignore($siteId),
            ],
            'state_code' => ['required', 'integer', Rule::exists('states', 'state_code')->where('status', 1)],
            'district' => 'nullable|string|max:120',
            'address' => 'required|string|max:500',
            'location' => 'nullable|string|max:191',
            'latitude' => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude',
            'onboarding_date' => 'nullable|date_format:Y-m-d',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'site_name.unique' => 'A site with this name and address already exists.',
            'state_code.required' => 'Choose a state.',
            'state_code.exists' => 'Choose a state from the list.',
        ];
    }
}
