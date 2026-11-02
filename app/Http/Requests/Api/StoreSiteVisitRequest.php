<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Site\SiteVisitRules;

/** POST /api/site-visits — record a visit (multipart when a file is sent). */
class StoreSiteVisitRequest extends MobileApiRequest
{
    public function rules(): array
    {
        return SiteVisitRules::rules(false);
    }

    public function messages(): array
    {
        return SiteVisitRules::messages();
    }
}
