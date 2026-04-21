<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central key-value store for SaaS-wide configuration editable from the
 * SuperAdmin UI: app_name, support_email, Moyasar keys, mail creds,
 * hero text, trial rules, notification toggles, etc.
 *
 * `group` lets the Settings page render tabs (general/trial/moyasar/mail/
 * landing/notifications). `value` is text because we store heterogeneous
 * content (strings, numbers, JSON, encrypted secrets) — the model does
 * type casting on read based on key.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group', 32)->index();
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
