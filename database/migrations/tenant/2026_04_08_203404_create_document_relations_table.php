<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_document_id')->constrained('legal_documents')->cascadeOnDelete();
            $table->foreignId('to_document_id')->constrained('legal_documents')->cascadeOnDelete();
            // implements | amends | supersedes | references | cites | related
            // See LegalDocument::RELATION_TYPES for Arabic labels.
            $table->string('relation_type', 30);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['from_document_id', 'to_document_id', 'relation_type'], 'doc_relations_unique');
            $table->index('to_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_relations');
    }
};
