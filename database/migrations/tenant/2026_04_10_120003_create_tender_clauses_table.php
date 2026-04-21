<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_clauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained('tenders')->cascadeOnDelete();
            // clause_type: sla, penalties, confidentiality, data_protection,
            // warranty, payment, ip, support, termination
            $table->string('clause_type', 30);
            $table->string('title');
            $table->longText('content');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['tender_id', 'clause_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_clauses');
    }
};
