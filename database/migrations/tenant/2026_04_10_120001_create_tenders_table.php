<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('scope_input')->nullable(); // raw user input
            // Project type: it | construction | consulting | operations | legal
            $table->string('type', 30)->default('it');
            $table->string('duration')->nullable();
            $table->json('deliverables')->nullable();
            $table->json('evaluation_criteria')->nullable();
            $table->json('special_conditions')->nullable();
            $table->json('expanded_scope')->nullable(); // AI-expanded tasks
            // Status: draft | generating | ready | reviewing | finalized
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['org_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenders');
    }
};
