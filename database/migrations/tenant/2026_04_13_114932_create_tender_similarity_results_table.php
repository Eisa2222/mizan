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
        Schema::create('tender_similarity_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->foreignId('compared_tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->unsignedBigInteger('org_id')->index();
            $table->float('text_similarity_score')->default(0);
            $table->float('semantic_similarity_score')->default(0);
            $table->float('structural_similarity_score')->default(0);
            $table->float('final_similarity_score')->default(0);
            $table->string('similarity_level', 30); // exact_match|high_similarity|medium_similarity|weak_similarity
            $table->boolean('duplicate_risk')->default(false);
            $table->json('reusable_sections')->nullable();
            $table->json('reusable_clauses')->nullable();
            $table->json('reusable_criteria')->nullable();
            $table->json('lessons_learned')->nullable();
            $table->text('recommendation')->nullable();
            $table->timestamps();
            $table->unique(['source_tender_id', 'compared_tender_id']);
            $table->index('final_similarity_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_similarity_results');
    }
};
