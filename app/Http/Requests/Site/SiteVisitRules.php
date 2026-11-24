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
            'current_stock' => 'nullable|numeric|min:0|max:9999999999',
            'date_of_stock_received' => 'nullable|date_format:Y-m-d|before_or_equal:today',
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

    /** @return array<string,string> field names used in validation messages */
    public static function attributes(): array
    {
        return ['site_id' => 'outlet', 'current_stock' => 'current stock', 'date_of_stock_received' => 'date of stock received'];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'site_id.required' => 'Choose an outlet.',
            'current_stock.numeric' => 'Current stock must be a number.',
            'date_of_stock_received.before_or_equal' => 'Date of stock received cannot be in the future.',
            'remarks.required' => 'Remarks are required.',
            'contact_mobile.digits' => 'Contact mobile must be a 10-digit number.',
        ];
    }
}
