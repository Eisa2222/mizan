<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central `tenants` table — one row per customer organization. Custom
 * columns match App\Models\Tenant::getCustomColumns() so stancl persists
 * them as native SQL columns (indexable, filterable) instead of
 * serialising into `data` JSON.
 *
 * Keeping stancl's `id` (string PK) + `data` (JSON overflow) + timestamps
 * alongside lets the package's migrations module stay happy.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();

            // SaaS profile fields
            $table->string('company_name');
            $table->string('owner_name');
            $table->string('owner_email');
            $table->string('owner_phone')->nullable();

            // Branding + preferences
            $table->string('logo')->nullable();
            $table->string('timezone', 64)->default('Asia/Riyadh');
            $table->string('language', 8)->default('ar');

            // Lifecycle — mirrors App\Models\Tenant::STATUS_*
            $table->string('status', 16)->default('active')->index();

            // Tenant-scoped feature flags + UI preferences (not authoritative
            // config — SaaS-wide config lives in central system_settings).
            $table->json('settings')->nullable();

            $table->timestamps();

            // stancl's own overflow bucket for anything that wasn't broken
            // out into a native column.
            $table->json('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
