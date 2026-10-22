<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Department extends Model
{
    use TenantTrait;
    protected $guarded=[];

    /**
     * Strip stray whitespace/line breaks (pasted or imported names) so they never reach page scripts.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function name(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : trim($value));
    }
}
