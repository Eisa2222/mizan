<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central `super_admins` table — SaaS operators. Deliberately separate
 * from the tenant `users` table so a compromised tenant session can't
 * escalate, and so the SuperAdmin login lives on the central domain
 * only.
 *
 * Accompanied by its own password_reset_tokens table (also central) so
 * the `super_admins` password broker defined in config/auth.php can
 * issue reset links without colliding with tenant broker tokens.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('super_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('super_admin_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admin_password_reset_tokens');
        Schema::dropIfExists('super_admins');
    }
};
