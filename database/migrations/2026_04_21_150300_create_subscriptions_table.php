<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lifecycle record for each tenant's paid access. One tenant may have
 * many rows over time (renewals, plan changes, reactivations) — the
 * active one is found by status + ends_at, not by "latest row".
 *
 * Statuses follow Moyasar's lifecycle vocabulary:
 *   trialing  — in free trial window
 *   active    — paid + current
 *   past_due  — latest invoice failed, grace period
 *   canceled  — explicit cancel by tenant
 *   expired   — trial/sub ended without renewal
 *   suspended — SuperAdmin hold (billing dispute, TOS)
 *
 * `coupon_id` + `discount_amount` pin the redemption to this sub so a
 * deleted coupon doesn't orphan the billed amount.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();

            $table->string('billing_cycle', 16);           // monthly | yearly
            $table->string('status', 16)->index();         // see doc block

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->index();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SAR');

            $table->foreignId('coupon_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
