<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('legal_documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');           // original chunk text
            $table->text('normalized');        // normalized for search (Arabic-aware)
            $table->string('label', 100)->nullable(); // e.g. "المادة 74"
            $table->unsignedInteger('char_start')->default(0);
            $table->unsignedInteger('char_end')->default(0);
            $table->unsignedInteger('token_count')->default(0);
            // RAG-ready: store embedding as JSON for now (vector DB later)
            $table->json('embedding')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'chunk_index']);
            $table->index('indexed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
