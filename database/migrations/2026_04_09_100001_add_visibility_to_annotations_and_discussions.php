<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // visibility: 'public' (everyone), 'org' (same org), 'private' (owner only)
        Schema::table('annotations', function (Blueprint $table) {
            $table->string('visibility', 10)->default('org')->after('color');
        });

        Schema::table('discussions', function (Blueprint $table) {
            $table->string('visibility', 10)->default('org')->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });

        Schema::table('discussions', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
