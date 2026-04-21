<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription plans offered on the landing page. Each plan has both
 * monthly and yearly pricing so the UI can show a toggle + savings badge.
 *
 * `limits` (JSON) holds numeric caps the app enforces (max_users override,
 * max_storage override, per-feature rate limits). `features` (JSON) is
 * a legacy/overflow bag; the authoritative per-feature flag list lives
 * in the `plan_features` pivot so SuperAdmin can drag-reorder it.
 *
 * `is_featured` + `badge_text/color` drive the "most popular" highlight
 * on the pricing grid.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Pricing — always in SAR halalas-compatible (store major units,
            // convert to halalas × 100 only when handing to Moyasar).
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency', 3)->default('SAR');

            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->unsignedInteger('max_users')->default(0);   // 0 = unlimited
            $table->unsignedInteger('max_storage_gb')->default(0);

            $table->json('features')->nullable();
            $table->json('limits')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0)->index();

            $table->string('badge_text')->nullable();
            $table->string('badge_color', 16)->nullable();

            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature');
            $table->boolean('included')->default(true);
            $table->string('limit')->nullable();  // e.g. "5 مستخدمين" or "10 GB"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['plan_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
