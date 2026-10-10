<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Code-quality plan, Phase 2: /dashboard renders for every role after the
 * DashboardController split (data built in App\Services\Dashboard\*), and the
 * old per-role URLs — which answered 500 — now send people to /dashboard.
 */
class DashboardRenderTest extends TestCase
{
    private function first(string $role): ?User
    {
        return User::withoutGlobalScopes()->where('role', $role)->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->first();
    }

    public function test_every_role_gets_its_dashboard(): void
    {
        foreach (['admin' => 'Admin Dashboard', 'manager' => 'Manager Dashboard', 'employee' => null] as $role => $title) {
            $user = $this->first($role);
            if (! $user) {
                continue;
            }
            $res = $this->actingAs($user)->withSession(['tenant_id' => $user->tenant_id])->get(route('dashboard'))->assertOk();
            $res->assertViewIs('client.dashboard.'.$role);
            if ($title) {
                $res->assertViewHas('page_title', $title);
            }
        }
    }

    public function test_old_role_urls_redirect_to_the_dashboard(): void
    {
        foreach (['admin' => 'dashboard.admin', 'manager' => 'dashboard.manager', 'employee' => 'dashboard.employee'] as $role => $route) {
            $user = $this->first($role);
            if (! $user) {
                continue;
            }
            $this->actingAs($user)->withSession(['tenant_id' => $user->tenant_id])->get(route($route))->assertRedirect(route('dashboard'));
        }
    }
}
