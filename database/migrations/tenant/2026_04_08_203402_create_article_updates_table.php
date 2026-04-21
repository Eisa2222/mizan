<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('legal_documents')->cascadeOnDelete();
            // The article this update applies to, e.g. "المادة 14".
            // Matches DocumentChunk::label produced by DocumentChunker.
            $table->string('article_label', 100);
            $table->date('update_date');
            $table->string('decree_number', 100)->nullable();
            $table->string('decree_url', 500)->nullable();
            $table->text('body');
            // Optional: the document that introduced this update (e.g. a ministerial decree).
            $table->foreignId('source_document_id')->nullable()
                ->constrained('legal_documents')->nullOnDelete();
            // True when produced by DiffDocumentVersionJob, false when added manually.
            $table->boolean('auto_generated')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['document_id', 'article_label']);
            $table->index('update_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_updates');
    }
};
