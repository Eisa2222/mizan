<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant key/value store — separate from central `system_settings`
 * which holds SaaS-wide operator config (Moyasar keys, hero copy).
 * This table is for things the tenant admin tweaks inside their own
 * workspace: branding colors, default folders, AI model preferences,
 * notification toggles.
 *
 * Runs once per tenant DB when stancl provisions them, never seen in
 * central.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
