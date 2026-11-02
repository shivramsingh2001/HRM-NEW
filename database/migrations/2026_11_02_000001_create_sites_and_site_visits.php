<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sites & Site Visits (plan phase 1, 2026-11-02). `sites` is the company's
 * site master — added by admin/HR by hand or by Excel import; `site_code`
 * (e.g. SITE-0001) is generated from the per-company running `site_no`.
 * `site_visits` is one row per visit an employee/manager records at a site;
 * `visit_date` is always the day it was saved (set by the server, never by
 * the client). New tables only — nothing existing changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sites')) {
            Schema::create('sites', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedInteger('site_no');
                $table->string('site_code', 30);
                $table->string('site_name', 191);
                $table->unsignedBigInteger('state_code');
                $table->string('district', 120)->nullable();
                $table->string('address', 500);
                $table->string('location', 191)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->date('onboarding_date')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tenant_id', 'site_no'], 'sites_tenant_no_uq');
                $table->unique(['tenant_id', 'site_code'], 'sites_tenant_code_uq');
                $table->index(['tenant_id', 'status'], 'sites_tenant_status_idx');
                $table->index(['tenant_id', 'state_code'], 'sites_tenant_state_idx');
            });
        }

        if (! Schema::hasTable('site_visits')) {
            Schema::create('site_visits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->foreignId('site_id')->constrained('sites');
                $table->date('visit_date');
                $table->dateTime('visited_at');
                $table->string('contact_person_name', 191)->nullable();
                $table->string('contact_mobile', 15)->nullable();
                $table->text('remarks');
                $table->string('file_path', 500)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->timestamp('edited_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'visit_date'], 'site_visits_tenant_date_idx');
                $table->index(['tenant_id', 'user_id', 'visit_date'], 'site_visits_user_date_idx');
                $table->index(['tenant_id', 'site_id', 'visit_date'], 'site_visits_site_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
        Schema::dropIfExists('sites');
    }
};
