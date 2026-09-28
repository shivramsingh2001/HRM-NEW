<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broadcast Notification module — step 2/2.
 *
 * `broadcast_recipients` is the per-recipient snapshot and the REAL
 * security/scoping boundary for this module: `tenant_id` here is always
 * populated (from the acting tenant admin's own tenant, or from each
 * resolved user's own tenant_id when a superadmin broadcast spans many
 * tenants) and is never trusted from client input. See
 * App\Services\Broadcast\BroadcastAudienceResolver for how it's enforced.
 *
 * No FK on user_id/super_admin_id on purpose — a later user deletion must
 * never cascade-corrupt broadcast history; the row is a permanent record of
 * who was notified, not a live pointer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('broadcast_recipients')) {
            Schema::create('broadcast_recipients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('broadcast_id');
                $table->unsignedBigInteger('tenant_id');
                $table->enum('recipient_type', ['tenant_user', 'super_admin']);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('super_admin_id')->nullable();
                $table->char('notification_id', 36)->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('action_clicked_at')->nullable();
                $table->json('channel_status')->nullable();
                $table->timestamps();

                $table->foreign('broadcast_id')->references('id')->on('broadcast_notifications')->cascadeOnDelete();

                $table->unique(['broadcast_id', 'user_id'], 'bcast_recip_broadcast_user_uq');
                $table->unique(['broadcast_id', 'super_admin_id'], 'bcast_recip_broadcast_sa_uq');
                $table->index('broadcast_id', 'bcast_recip_broadcast_idx');
                $table->index(['tenant_id', 'broadcast_id'], 'bcast_recip_tenant_broadcast_idx');
                $table->index(['tenant_id', 'user_id', 'read_at'], 'bcast_recip_tenant_user_read_idx');
                $table->index(['super_admin_id', 'read_at'], 'bcast_recip_sa_read_idx');
                $table->index('notification_id', 'bcast_recip_notification_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
    }
};
