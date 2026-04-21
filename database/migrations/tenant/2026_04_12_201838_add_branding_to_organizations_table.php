<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('logo_path', 500)->nullable()->after('domain');
            $table->string('header_text', 500)->nullable()->after('logo_path');
            $table->string('footer_text', 500)->nullable()->after('header_text');
            $table->string('primary_color', 7)->default('#1a3a52')->after('footer_text');
            $table->string('accent_color', 7)->default('#c8a94b')->after('primary_color');
            $table->string('phone', 50)->nullable()->after('accent_color');
            $table->string('email', 200)->nullable()->after('phone');
            $table->string('website', 300)->nullable()->after('email');
            $table->string('address', 500)->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path', 'header_text', 'footer_text',
                'primary_color', 'accent_color',
                'phone', 'email', 'website', 'address',
            ]);
        });
    }
};
