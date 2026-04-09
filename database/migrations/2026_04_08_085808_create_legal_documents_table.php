<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('title');
            $table->string('title_en')->nullable();
            $table->unsignedTinyInteger('type'); // 1=نظام, 2=لائحة, 3=مرسوم ملكي, 4=قرار وزاري, 5=تعميم, 6=حكم قضائي, 7=فتوى
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->date('issued_at')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['org_id', 'type']);
            $table->index('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
