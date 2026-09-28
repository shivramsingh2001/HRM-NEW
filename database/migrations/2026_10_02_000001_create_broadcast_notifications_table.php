<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broadcast Notification module — step 1/2.
 *
 * `broadcast_notifications` is the broadcast definition, shared by both this
 * app (Tenant Admin composer) and the separate hrm-superadmin app (Superadmin
 * composer, cross-tenant), which points a plain unscoped Eloquent model at
 * this same table (it never migrates the shared DB itself).
 *
 * `origin_tenant_id` is PROVENANCE ONLY ("which tenant's admin authored
 * this"), not a security/scoping boundary — deliberately not named
 * `tenant_id` so nothing reflexively adds TenantTrait to the model (which
 * would hide every superadmin-originated broadcast, since NULL never
 * matches a tenant scope). The real scoping boundary is
 * `broadcast_recipients.tenant_id` (see the next migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('broadcast_notifications')) {
            Schema::create('broadcast_notifications', function (Blueprint $table) {
                $table->id();
                $table->enum('origin', ['tenant_admin', 'superadmin']);
                $table->unsignedBigInteger('origin_tenant_id')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('created_by_super_admin_id')->nullable();
                $table->string('title');
                $table->text('body');
                $table->string('action_url', 500)->nullable();
                $table->string('action_label', 100)->nullable();
                $table->enum('audience_type', [
                    'tenant_filtered', 'superadmin_users', 'tenant_admins_managers',
                    'tenant_employees', 'selected_tenants', 'all_eligible',
                ]);
                $table->json('audience_filters');
                // No DB-level default (MySQL JSON columns can't take a plain
                // literal default pre-8.0.13, and this codebase has no
                // precedent for expression defaults) — BroadcastComposerService
                // always passes ['database'] explicitly when none is given.
                $table->json('channels');
                $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
                $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed', 'cancelled', 'expired'])->default('draft');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedInteger('total_recipients')->default(0);
                $table->unsignedInteger('delivered_count')->default(0);
                $table->unsignedInteger('read_count')->default(0);
                $table->unsignedInteger('action_click_count')->default(0);
                $table->text('failure_reason')->nullable();
                $table->timestamps();

                $table->index(['status', 'scheduled_at'], 'bcast_notif_status_scheduled_idx');
                $table->index(['status', 'expires_at'], 'bcast_notif_status_expires_idx');
                $table->index(['origin', 'origin_tenant_id'], 'bcast_notif_origin_idx');
                $table->index('created_by_user_id', 'bcast_notif_created_by_user_idx');
                $table->index('created_by_super_admin_id', 'bcast_notif_created_by_sa_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_notifications');
    }
};
