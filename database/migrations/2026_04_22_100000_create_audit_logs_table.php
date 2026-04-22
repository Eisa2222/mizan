<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only record of every consequential SuperAdmin action —
 * suspensions, plan changes, refunds, impersonations, coupon edits.
 * Required for NCA/PDPL reviewability; also lets the UI show each
 * tenant's history ("who suspended this tenant and when?").
 *
 * Schema choices:
 *   - `target_type` + `target_id` keep the table polymorphic without
 *     FK constraints — we log across models and still want a row when
 *     the referenced model is deleted afterwards.
 *   - `before` / `after` hold JSON snapshots of just the changed fields
 *     (never the full row) to keep log volume reasonable.
 *   - No soft-delete; rows are permanent. Operators who need to purge
 *     for GDPR-style right-to-be-forgotten run a dedicated maintenance
 *     command.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('super_admin_id')
                ->nullable()
                ->constrained('super_admins')
                ->nullOnDelete();

            // Actor context when the action isn't a SuperAdmin — e.g.
            // a webhook or scheduled job triggers the change.
            $table->string('actor_type', 32)->default('super_admin');

            $table->string('action', 64);  // tenant.suspend, payment.refund, etc.
            $table->string('target_type', 64)->nullable(); // App\\Models\\Tenant
            $table->string('target_id')->nullable();       // stringified (UUID or int)

            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['target_type', 'target_id']);
            $table->index('super_admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
