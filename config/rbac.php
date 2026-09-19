<?php

/**
 * Tenant RBAC (Super Admin Panel Phase 5). Modules + actions match
 * role_permissions.action (ENUM) and the panel's config/rbac.php.
 * `system_roles` is the default matrix seeded by `php artisan rbac:sync-roles`
 * for tenants that predate provisioning-time seeding.
 */
return [
    'modules' => [
        'employee', 'attendance', 'leave', 'payroll', 'tasks', 'projects', 'recruitment',
        'onboarding', 'offboarding', 'expenses', 'loans', 'meetings',
        'announcements', 'reports', 'settings', 'performance', 'performance_reviews', 'overtime', 'team',
        'requests', 'branches', 'assets',
    ],

    'actions' => ['view', 'create', 'edit', 'delete', 'approve', 'export', 'manage'],

    // own = the user's own record only; team = records of people who report
    // to the user (reporting_head = user.id); company = every record in the
    // tenant. Only meaningful for modules where "whose record" applies
    // (employee, attendance, leave, tasks, expenses, ...) — ignored
    // elsewhere. Defaults to 'company' to preserve pre-scope behavior for
    // every existing seeded permission.
    'scopes' => ['own', 'team', 'company'],

    // slug => [display name, is_system, permissions map]
    // Each module's value is either:
    //   'all'                          — every action, scope=company
    //   ['view', 'edit', ...]          — those actions, scope=company
    //   ['view' => 'own', 'edit' => 'team']  — per-action scope
    'system_roles' => [
        'admin' => ['Administrator', true, [
            'employee' => 'all', 'attendance' => 'all', 'leave' => 'all', 'payroll' => 'all', 'tasks' => 'all',
            'projects' => 'all', 'recruitment' => 'all', 'onboarding' => 'all', 'offboarding' => 'all',
            'expenses' => 'all', 'loans' => 'all', 'meetings' => 'all', 'announcements' => 'all',
            'reports' => 'all', 'settings' => 'all', 'performance' => 'all', 'performance_reviews' => 'all',
            'overtime' => 'all', 'team' => 'all', 'requests' => 'all', 'branches' => 'all', 'assets' => 'all',
        ]],
        'hr' => ['HR', true, [
            // tasks/projects => 'all': matches the pre-RBAC
            // TaskPermissionService::isElevated() behavior (admin+hr always
            // treated as equally privileged for tasks/projects) — kept as
            // 'all' deliberately so wiring HR through the permission system
            // doesn't silently narrow access they already had. NOTE: this
            // comment always claimed 'projects' was included, but the key
            // was actually missing from this array until the Project module
            // upgrade (2026-09-21) wired real permission: middleware onto
            // project.* routes — until then the gap was silently inert since
            // nothing read this grant for the real web ProjectController.
            // payroll => 'all' (was implicitly true: MonthlyPayrollController's
            // create/store/update/status/bulk-update/export/destroy routes had
            // NO role check at all before RBAC — HR is who actually runs
            // monthly payroll processing, so this preserves their real
            // workflow rather than the accidental "view only" narrower grant).
            'employee' => 'all', 'attendance' => 'all', 'leave' => 'all', 'payroll' => 'all', 'tasks' => 'all',
            'projects' => 'all',
            // expenses => 'all' (was implicitly true for hr under the old
            // in_array(role, ['admin','hr','manager']) approval gate).
            'expenses' => 'all',
            'recruitment' => 'all', 'onboarding' => 'all', 'offboarding' => 'all', 'loans' => ['view'],
            'meetings' => 'all', 'announcements' => 'all', 'reports' => ['view', 'export'],
            'performance' => 'all', 'performance_reviews' => 'all',
            // overtime => 'all': OvertimeController::viewAll()/show()/
            // pendingApprovals() only ever checked for 'admin'/'manager' (an
            // apparent oversight — every other module treats hr the same as
            // admin) — hr fell through to the "regular employee, own only"
            // branch. Corrected to match hr's access everywhere else.
            'overtime' => 'all', 'team' => 'all', 'requests' => 'all',
            // branches => 'all': hr manages the new organizational Branch
            // module the same as Department/Designation-adjacent org setup.
            'branches' => 'all',
            // assets => 'all': HR/IT typically owns the company asset
            // inventory (registration, assignment, disposal) the same way
            // they own Branch org-setup data.
            'assets' => 'all',
        ]],
        'manager' => ['Manager', true, [
            'employee' => ['view' => 'team'],
            // edit => team: markAttendance()/the V1 mark() API actor check
            // already allowed a manager to mark attendance (no further
            // per-record scoping existed) — team here keeps that working
            // without granting company-wide edit.
            'attendance' => ['view' => 'team', 'approve' => 'team', 'edit' => 'team'],
            'leave' => ['view' => 'team', 'approve' => 'team'],
            'tasks' => 'all',
            // Old code let a manager view_all() with an own+team filter and
            // gated approval to admin/hr/manager (though a bug made the
            // intended team-only restriction on approval never actually
            // run — see ExpenseController::updateStatus fix). team scope
            // here is the CORRECTED behavior, not a preserved bug.
            'expenses' => ['view' => 'team', 'approve' => 'team'],
            // payroll => own view only: a manager could always view their own
            // salary slip (id-ownership check, no role gate) — they were never
            // able to see their team's or the company's payroll.
            'payroll' => ['view' => 'own'],
            // meetings => own (participant-only): old code showed manager/
            // employee only meetings they're a participant of, admin/hr saw
            // all. 'own' is reused here to mean "meetings I'm part of" since
            // there's no single owner column for a many-to-many relation.
            // projects edit => own: Project Management upgrade (2026-09-21) —
            // a manager can edit/update projects where they're the project
            // head or an active team member (record-level check in
            // ProjectController::authorizeProjectAccess()), not company-wide.
            // Was 'view' only, which didn't match the detail page's existing
            // "Edit Project" button already being shown to managers.
            'projects' => ['view', 'edit' => 'own'], 'recruitment' => ['view'],
            // meetings edit => own: any meeting creator (any role) could
            // already update/cancel/complete their own meeting.
            'meetings' => ['view' => 'own', 'edit' => 'own'], 'reports' => ['view'],
            // performance => company scope (not team): old code let ANY
            // manager view/rate ANY employee's individual KPI report, not
            // just direct reports — teamReport() applies its own separate
            // department/reporting_head narrowing internally, unrelated to
            // this grant.
            'performance' => ['view' => 'company', 'approve' => 'company'],
            // performance_reviews => team scope: authorizeReview()/
            // authorizeViewReview() already correctly restrict a manager to
            // their own direct reports (reporting_head check) — a different,
            // stricter rule than the KPI-score module above.
            'performance_reviews' => ['view' => 'team', 'create' => 'team', 'edit' => 'team', 'delete' => 'team'],
            // team => team scope: getTeamMembers()/canViewUserProfile()/
            // attendanceSummary()-family already restrict a manager to
            // reporting_head-based direct reports.
            'team' => ['view' => 'team', 'export' => 'team'],
            // overtime => team: managerMayAct()/viewAll() already correctly
            // restrict a manager to their own direct reports' requests.
            'overtime' => ['view' => 'team', 'approve' => 'team'],
            // requests approve => team: old code let any manager approve/
            // reject ANY tenant's request with no reportee check at all —
            // team scope here is the CORRECTED behavior, not a preserved bug.
            'requests' => ['view' => 'team', 'approve' => 'team'],
            // loans => team: the API loan listing's "isAdminOrManager" check
            // included EVERY valid role (admin/hr/manager/employee), so it
            // was always true and every user — including plain employees —
            // saw every tenant loan unfiltered. team scope here is the
            // corrected behavior for manager; employee below gets 'own'.
            'loans' => ['view' => 'team'],
            // branches => company: Branch is reference/org-structure data,
            // visible read-only to every role — same spirit as Department/
            // Designation dropdowns being populated for all roles.
            'branches' => ['view' => 'company'],
            // assets => team: read-only visibility into assets currently
            // assigned to the manager's own direct reports (getTeamMembers()
            // -style reporting_head scoping), no create/edit/manage.
            'assets' => ['view' => 'team'],
        ]],
        'employee' => ['Employee', true, [
            'employee' => ['view' => 'own'],
            'attendance' => ['view' => 'own'],
            'leave' => ['view' => 'own', 'create' => 'own'],
            'tasks' => ['view', 'edit'],
            'payroll' => ['view' => 'own'],
            // Employees could already reach view_all() and see their own
            // expenses (no route/role gate existed) — preserved as 'own'
            // view; they were never able to approve.
            'expenses' => ['view' => 'own', 'create' => 'own'],
            'loans' => ['view' => 'own', 'create' => 'own'],
            'meetings' => ['view' => 'own', 'edit' => 'own'],
            'announcements' => ['view'],
            'performance' => ['view' => 'own'],
            'performance_reviews' => ['view' => 'own'],
            // projects => own: the AI projects endpoint already restricted
            // employees to projects they're assigned to (via project_assigns,
            // not a user_id owner column) — 'own' is reused for that.
            'projects' => ['view' => 'own'],
            // team => own: canViewUserProfile() always let a user view their
            // own profile via id-equality regardless of role.
            'team' => ['view' => 'own'],
            'overtime' => ['view' => 'own'],
            'requests' => ['view' => 'own'],
            'branches' => ['view' => 'company'],
            // assets => own: an employee only sees assets currently or
            // previously assigned to them (My Assets self-service page).
            // Accept/return of an asset already assigned to them is always
            // allowed regardless of this grant (ownership-checked in the
            // controller, matching Loans/Requests).
            'assets' => ['view' => 'own'],
        ]],
    ],
];
