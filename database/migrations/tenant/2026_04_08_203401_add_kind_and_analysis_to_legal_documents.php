<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            // What kind of artifact this row represents:
            //   document = regular legal document (نظام، لائحة، فتوى، حكم...)
            //   contract = uploaded contract subject to risk analysis
            //   case     = court case subject to outcome analysis
            $table->string('kind', 20)->default('document')->after('type');
            // AI analysis output (contract risks, case prediction, etc.)
            $table->json('analysis')->nullable()->after('metadata');

            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'analysis']);
        });
    }
};
