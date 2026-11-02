<?php

namespace App\Http\Requests\Site;

/**
 * Field rules for recording / editing a site visit — one list shared by the
 * web form requests and the mobile API requests (which only differ in their
 * error envelope). The visit date is never accepted: it is always today.
 */
final class SiteVisitRules
{
    /** @return array<string,mixed> */
    public static function rules(bool $update): array
    {
        $rules = [
            'contact_person_name' => 'nullable|string|max:191',
            'contact_mobile' => 'nullable|digits:10',
            'remarks' => 'required|string|max:2000',
            'file' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx',
        ];

        return $update
            ? $rules + ['remove_file' => 'nullable|boolean']
            : $rules + [
                'site_id' => 'required|integer',
                'latitude' => 'nullable|numeric|between:-90,90|required_with:longitude',
                'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude',
            ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'site_id.required' => 'Choose a site.',
            'remarks.required' => 'Remarks are required.',
            'contact_mobile.digits' => 'Contact mobile must be a 10-digit number.',
        ];
    }
}
