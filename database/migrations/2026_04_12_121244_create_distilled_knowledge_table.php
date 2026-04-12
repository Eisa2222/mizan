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
        Schema::create('distilled_knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->string('court_type', 50)->nullable()->index();
            $table->string('source_label', 200)->nullable();
            $table->string('batch', 100)->nullable()->index();
            $table->text('summary');
            $table->json('legal_topics');
            $table->json('reasoning_patterns');
            $table->json('cited_laws');
            $table->json('key_phrases');
            $table->json('applicable_to');
            $table->string('distill_model', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distilled_knowledge');
    }
};
