<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discount codes issued by SuperAdmin and applied at checkout. Split
 * into `coupons` (the code itself) and `coupon_uses` (each redemption)
 * so we can track usage per tenant without losing history when a coupon
 * is deleted — the uses row references subscription_id which is the
 * audit trail.
 *
 * `uses_count` is an atomic counter incremented via DB::increment() in
 * CouponService::apply() to prevent race conditions on high-traffic
 * checkout (DON'T read-modify-write in PHP).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');

            // 'percentage' (1-100) or 'fixed' (SAR amount)
            $table->string('type', 16);
            $table->decimal('value', 10, 2);

            $table->unsignedInteger('max_uses')->nullable(); // null = unlimited
            $table->unsignedInteger('uses_count')->default(0);
            $table->decimal('min_order_amount', 10, 2)->nullable();

            // Null = applies to all plans / all billing cycles.
            $table->json('applicable_plans')->nullable();
            $table->json('billing_cycles')->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();

            $table->boolean('is_active')->default(true)->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('super_admins')
                ->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('coupon_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id');
            $table->unsignedBigInteger('subscription_id');
            $table->decimal('discount_amount', 10, 2);
            $table->timestamp('used_at');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_uses');
        Schema::dropIfExists('coupons');
    }
};
