<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

/** Site Visits page → Add visit. Rules: SiteVisitRules. */
class StoreSiteVisitRequest extends FormRequest
{
    /** Route middleware (feature + permission:site_visits,*) already decides who may get here. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return SiteVisitRules::rules(false);
    }

    public function messages(): array
    {
        return SiteVisitRules::messages();
    }
}
