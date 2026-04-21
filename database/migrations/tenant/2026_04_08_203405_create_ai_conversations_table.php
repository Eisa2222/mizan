<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Optional: scope a conversation to a specific document for RAG context.
            // Null means a free-form chat not tied to a document.
            $table->foreignId('document_id')->nullable()
                ->constrained('legal_documents')->cascadeOnDelete();
            $table->string('title', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
