<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->unsignedTinyInteger('compliance_score')->default(0); // 0-100
            $table->json('issues')->nullable();         // [{severity, category, title, ...}]
            $table->json('recommendations')->nullable();
            $table->json('statistics')->nullable();      // {critical, high, medium, improvement}
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index('tender_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_reviews');
    }
};
