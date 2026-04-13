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
        Schema::create('tender_similarity_ignores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->foreignId('matched_tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->foreignId('ignored_by')->constrained('users');
            $table->text('ignore_reason');
            $table->timestamps();
            $table->unique(['tender_id', 'matched_tender_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_similarity_ignores');
    }
};
