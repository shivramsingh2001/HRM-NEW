<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Site\SiteVisitRules;

/** POST|PUT /api/site-visits/{id} — edit a visit (multipart when a file is sent). */
class UpdateSiteVisitRequest extends MobileApiRequest
{
    public function rules(): array
    {
        return SiteVisitRules::rules(true);
    }

    public function messages(): array
    {
        return SiteVisitRules::messages();
    }
}
