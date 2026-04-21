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
        Schema::table('tenders', function (Blueprint $table) {
            $table->json('boq_items')->nullable()->after('special_conditions');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn('boq_items');
        });
    }
};
