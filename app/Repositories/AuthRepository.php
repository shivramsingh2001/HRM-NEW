<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\AuthRepositoryInterface;

class AuthRepository implements AuthRepositoryInterface
{
    /**
     * Relies on User's TenantTrait global scope — correctly scoped as long
     * as a tenant has been resolved by TenantMiddleware before this runs,
     * which is now mandatory for the pre-auth routes that call this (see
     * TenantMiddleware::handleApiRequest — a missing/invalid tenant
     * identifier is rejected before the request ever reaches here).
     */
    public function findByEmployeeId($employeeId)
    {
        return User::with('jobDetails')
            ->where('employee_id', $employeeId)
            ->first();
    }
}