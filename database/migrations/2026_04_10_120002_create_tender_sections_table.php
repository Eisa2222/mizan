<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained('tenders')->cascadeOnDelete();
            // section_key: title_page, introduction, scope, deliverables,
            // timeline, qualifications, evaluation, conditions, pricing, contract
            $table->string('section_key', 50);
            $table->string('title');
            $table->longText('content')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_edited')->default(false);
            $table->timestamps();

            $table->index(['tender_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_sections');
    }
};
