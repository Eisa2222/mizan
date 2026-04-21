<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('priority')->default(2); // 1=Low, 2=Medium, 3=High, 4=Urgent
            $table->unsignedTinyInteger('status')->default(1);   // 1=New, 2=InProgress, 3=InReview, 4=Done, 5=Cancelled
            $table->date('due_date')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('legal_documents')->nullOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['org_id', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
