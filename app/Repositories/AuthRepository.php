<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\AuthRepositoryInterface;
use Illuminate\Support\Facades\Log;

class AuthRepository implements AuthRepositoryInterface
{
    protected function tenantId()
    {
        return optional(app('tenant'))->id;
    }

    public function findByEmployeeId($employeeId)
    {
        Log::info('🔍 Searching for user with employee_id:', [
            'employee_id' => $employeeId,
            'type' => gettype($employeeId),
            'length' => strlen($employeeId)
        ]);

        $user = User::with('jobDetails')
            ->where('employee_id', $employeeId)
            ->first();

        Log::info('📊 Search result:', [
            'found' => $user ? 'YES' : 'NO',
            'user_id' => $user->id ?? null,
            'user_name' => $user->name ?? null,
            'user_role' => $user->role ?? null,
            'user_status' => $user->status ?? null
        ]);

        return $user;
    }
}