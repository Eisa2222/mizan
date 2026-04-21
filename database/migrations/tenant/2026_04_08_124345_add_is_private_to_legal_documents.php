<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('uploaded_by');
            $table->index(['org_id', 'is_private']);
        });
    }

    public function down(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropIndex(['org_id', 'is_private']);
            $table->dropColumn('is_private');
        });
    }
};
