<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait TenantTrait
{
    protected static function bootTenantTrait()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {

            if (app()->bound('current_tenant')) {
                $tenant = app('current_tenant');
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenant->id);
            }
        });

        static::creating(function ($model) {

            if (!$model->tenant_id && app()->bound('current_tenant')) {
                $model->tenant_id = app('current_tenant')->id;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function scopeAllTenants($query)
    {
        return $query->withoutGlobalScope('tenant');
    }
}
