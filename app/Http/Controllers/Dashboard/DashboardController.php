<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\AdminDashboard;
use App\Services\Dashboard\EmployeeDashboard;
use App\Services\Dashboard\ManagerDashboard;
use Illuminate\Support\Facades\Auth;

/**
 * /dashboard — the signed-in user's dashboard by role: admin and HR get the
 * admin dashboard, managers the manager one, everyone else the employee one.
 * The data is built in App\Services\Dashboard\* (code-quality plan, Phase 2).
 */
class DashboardController extends Controller
{
    public function index()
    {
        return match (Auth::user()->role) {
            'admin', 'hr' => view('client.dashboard.admin', app(AdminDashboard::class)->build()),
            'manager' => view('client.dashboard.manager', app(ManagerDashboard::class)->build()),
            default => view('client.dashboard.employee', app(EmployeeDashboard::class)->build()),
        };
    }

    /**
     * The old per-role URLs (dashboard.admin / .hr / .manager / .employee,
     * used by the redirect.role middleware). They pointed at private methods
     * and answered 500; /dashboard already picks the right dashboard.
     */
    public function roleDashboard()
    {
        return redirect()->route('dashboard');
    }
}
