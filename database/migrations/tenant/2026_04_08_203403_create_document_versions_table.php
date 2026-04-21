<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('legal_documents')->cascadeOnDelete();
            // 1-based version number per document. The original upload is implicit
            // (not stored here) — DocumentVersion rows represent re-uploads only.
            $table->unsignedInteger('version_number');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedInteger('file_size');
            $table->longText('content')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
