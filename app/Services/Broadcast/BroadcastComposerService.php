<?php

namespace App\Services\Broadcast;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\CompanyBranch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Tenant Admin composer orchestration: validate → (transaction: create the
 * broadcast row + resolve audience + chunk-snapshot recipients) → deliver.
 *
 * Resolution/snapshotting is intentionally inside the transaction (a crash
 * mid-way must never leave an ambiguous "was 0 the real audience, or did it
 * crash?" state); delivery happens after commit since individual delivery
 * failures are retryable and must never roll back an already-committed
 * recipient snapshot.
 */
class BroadcastComposerService
{
    public function __construct(
        protected BroadcastAudienceResolver $resolver,
        protected BroadcastDeliveryService $delivery,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function validate(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'action_url' => ['nullable', 'url', 'max:500'],
            'action_label' => ['nullable', 'required_with:action_url', 'string', 'max:100'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'scheduled_at' => ['nullable', 'date', 'after_or_equal:now'],
            'expires_at' => ['nullable', 'date'],
            'channels' => ['array'],
            'channels.*' => ['in:fcm,email,sms'],
            'all' => ['nullable', 'boolean'],
            'role' => ['array'],
            'role.*' => ['in:admin,hr,manager,employee'],
            'department_ids' => ['array'],
            'department_ids.*' => ['integer'],
            'designation_ids' => ['array'],
            'designation_ids.*' => ['integer'],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer'],
            'user_ids' => ['array'],
            'user_ids.*' => ['integer'],
        ]);

        $validator->after(function ($v) use ($request) {
            $this->applyAudienceRules($v, $request);

            $scheduledAt = $request->filled('scheduled_at') ? Carbon::parse($request->input('scheduled_at')) : now();
            if ($request->filled('expires_at') && Carbon::parse($request->input('expires_at'))->lte($scheduledAt)) {
                $v->errors()->add('expires_at', 'Expiry must be after the send time.');
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Lightweight audience-only validation for the live recipient-count
     * preview — deliberately does NOT require title/body, so adjusting
     * filters before typing a title still gives a real count instead of
     * always showing 0.
     *
     * @throws ValidationException
     */
    public function validateAudienceOnly(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'all' => ['nullable', 'boolean'],
            'role' => ['array'],
            'role.*' => ['in:admin,hr,manager,employee'],
            'department_ids' => ['array'],
            'department_ids.*' => ['integer'],
            'designation_ids' => ['array'],
            'designation_ids.*' => ['integer'],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer'],
            'user_ids' => ['array'],
            'user_ids.*' => ['integer'],
        ]);

        $validator->after(fn ($v) => $this->applyAudienceRules($v, $request));

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function applyAudienceRules($v, Request $request): void
    {
        $all = $request->boolean('all');
        $role = array_values(array_filter($request->input('role', [])));
        $departmentIds = array_values(array_filter($request->input('department_ids', [])));
        $designationIds = array_values(array_filter($request->input('designation_ids', [])));
        $branchIds = array_values(array_filter($request->input('branch_ids', [])));
        $userIds = array_values(array_filter($request->input('user_ids', [])));

        $anyFilter = $role || $departmentIds || $designationIds || $branchIds || $userIds;

        if ($all && $anyFilter) {
            $v->errors()->add('all', 'Choose either "All employees" or specific filters, not both.');
        }
        if (! $all && ! $anyFilter) {
            $v->errors()->add('audience', 'Select an audience: a filter, specific employees, or "All employees".');
        }

        // Existence checks scoped through TenantTrait-scoped models, not a
        // raw `exists:table,id` rule — a raw rule would validate against
        // the GLOBAL table and accept another tenant's id as "valid"
        // before the resolver's join silently drops it. This turns that
        // mismatch into a clean validation error instead.
        if ($departmentIds && Department::whereIn('id', $departmentIds)->count() !== count($departmentIds)) {
            $v->errors()->add('department_ids', 'One or more selected departments are invalid.');
        }
        if ($designationIds && Designation::whereIn('id', $designationIds)->count() !== count($designationIds)) {
            $v->errors()->add('designation_ids', 'One or more selected designations are invalid.');
        }
        if ($branchIds && CompanyBranch::whereIn('id', $branchIds)->count() !== count($branchIds)) {
            $v->errors()->add('branch_ids', 'One or more selected branches are invalid.');
        }
        if ($userIds && User::whereIn('id', $userIds)->count() !== count($userIds)) {
            $v->errors()->add('user_ids', 'One or more selected employees are invalid.');
        }
    }

    public function countRecipients(array $validated, int $excludeUserId): int
    {
        return $this->resolver->countFor($this->filtersFrom($validated), $excludeUserId);
    }

    public function create(User $creator, array $validated): Broadcast
    {
        $filters = $this->filtersFrom($validated);
        $scheduledAt = $validated['scheduled_at'] ?? null;

        $broadcast = DB::transaction(function () use ($creator, $validated, $filters, $scheduledAt) {
            $broadcast = Broadcast::create([
                'origin' => 'tenant_admin',
                'origin_tenant_id' => $creator->tenant_id,
                'created_by_user_id' => $creator->id,
                'title' => $validated['title'],
                'body' => $validated['body'],
                'action_url' => $validated['action_url'] ?? null,
                'action_label' => $validated['action_label'] ?? null,
                'audience_type' => 'tenant_filtered',
                'audience_filters' => $filters,
                // 'database' is always implicit (it's how the module's own
                // read-tracking works, see BroadcastRecipient); fcm/email/sms
                // are opt-in per broadcast, picked in the composer UI.
                'channels' => array_values(array_unique(array_merge(['database'], $validated['channels'] ?? []))),
                'priority' => $validated['priority'] ?? 'normal',
                'status' => $scheduledAt ? 'scheduled' : 'sending',
                'scheduled_at' => $scheduledAt,
                'expires_at' => $validated['expires_at'] ?? null,
            ]);

            if (! $scheduledAt) {
                $this->snapshotRecipients($broadcast, $filters, $creator->id);
            }

            return $broadcast;
        });

        if (! $scheduledAt) {
            // Phase B: dispatch a Bus::batch() of SendBroadcastBatchJob
            // chunks rather than delivering inline — the composer's web
            // request returns as soon as the snapshot transaction commits,
            // not after every recipient's Notification::send() completes.
            $this->delivery->dispatchDelivery($broadcast);
        }

        return $broadcast;
    }

    /**
     * Snapshot the resolved audience into broadcast_recipients, chunked, and
     * set total_recipients. Called both from create() (immediate send) and
     * from the broadcast:send-scheduled command (Phase B) at fire time —
     * scheduled sends deliberately re-resolve at fire time, not compose
     * time, so a filter-based audience reflects who currently matches.
     */
    public function snapshotRecipients(Broadcast $broadcast, array $filters, ?int $excludeUserId = null): int
    {
        $count = 0;
        $now = now();

        $this->resolver->resolveFor($filters, $excludeUserId)->chunk(500)->each(function ($chunk) use ($broadcast, $now, &$count) {
            $rows = $chunk->map(fn ($user) => [
                'broadcast_id' => $broadcast->id,
                'tenant_id' => $user->tenant_id,
                'recipient_type' => 'tenant_user',
                'user_id' => $user->id,
                'super_admin_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if ($rows) {
                // 'updated_at' as the update column is a harmless no-op on
                // conflict (just refreshes the timestamp) — this is retry-
                // safety against a duplicate snapshot call, not expected in
                // normal flow (the UNIQUE(broadcast_id, user_id) constraint
                // is the real duplicate-prevention mechanism).
                BroadcastRecipient::upsert($rows, ['broadcast_id', 'user_id'], ['updated_at']);
                $count += count($rows);
            }
        });

        $broadcast->update(['total_recipients' => $count]);

        return $count;
    }

    private function filtersFrom(array $validated): array
    {
        return [
            'all' => (bool) ($validated['all'] ?? false),
            'role' => array_values(array_filter($validated['role'] ?? [])),
            'department_ids' => array_values(array_filter($validated['department_ids'] ?? [])),
            'designation_ids' => array_values(array_filter($validated['designation_ids'] ?? [])),
            'branch_ids' => array_values(array_filter($validated['branch_ids'] ?? [])),
            'user_ids' => array_values(array_filter($validated['user_ids'] ?? [])),
        ];
    }
}
