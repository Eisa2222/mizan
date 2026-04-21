<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment attempts routed through Moyasar. We store the full Moyasar
 * response JSON so refunds, disputes, and audits have the raw record.
 *
 * `status` is our view of the payment; `moyasar_response.status` may
 * drift (e.g. Moyasar transitions paid → refunded via their API) — we
 * reconcile via the webhook handler added in Phase 3.
 *
 * Soft FK to subscription_id: a failed payment still gets a row even if
 * no subscription was created (checkout abandoned after charge).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('subscription_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Moyasar identifiers — nullable because rows are written
            // before the API call and filled after.
            $table->string('moyasar_payment_id')->nullable()->unique();
            $table->string('moyasar_invoice_id')->nullable()->index();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SAR');

            $table->string('status', 16)->index(); // initiated|paid|failed|refunded
            $table->string('payment_method', 32)->nullable(); // creditcard|applepay|stcpay
            $table->json('moyasar_response')->nullable();
            $table->text('failure_message')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
