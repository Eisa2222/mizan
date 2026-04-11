<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GPC = Government Procurement & Competition
     *
     * Authoritative knowledge base for نظام المنافسات والمشتريات الحكومية:
     *   • النظام الصادر بالمرسوم الملكي رقم م/128 بتاريخ 13/11/1440هـ
     *   • اللائحة التنفيذية الصادرة بقرار وزير المالية رقم 1242 بتاريخ 21/3/1441هـ
     *   • أدلة هيئة كفاءة الإنفاق (إعداد وثائق المنافسة، التأهيل، الترسية، إلخ)
     *
     * Each row is one article (مادة) or guideline section that the RAG layer
     * can retrieve and inject into AI prompts.
     */
    public function up(): void
    {
        Schema::create('gpc_knowledge', function (Blueprint $table) {
            $table->id();
            // source: 'system' (النظام) | 'regulation' (اللائحة) | 'guide_*' (أدلة كفاءة الإنفاق)
            $table->string('source', 50);
            $table->string('source_label');           // النص الكامل للمصدر
            $table->string('article_number', 20)->nullable(); // مثل "23" أو "23-1"
            $table->string('article_label', 100);     // مثل "المادة 23" أو "الدليل 4 / القسم 2"
            $table->string('chapter')->nullable();    // الباب أو الفصل
            $table->string('topic')->nullable();      // الموضوع المختصر
            $table->text('content');                  // النص الكامل للمادة
            $table->text('normalized')->nullable();   // نص مُطبَّع للبحث
            $table->json('keywords')->nullable();     // كلمات مفتاحية للترشيح السريع
            $table->json('related_to')->nullable();   // [['source'=>...,'article'=>...]]
            $table->timestamps();

            $table->index(['source', 'article_number']);
            $table->index('topic');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gpc_knowledge');
    }
};
