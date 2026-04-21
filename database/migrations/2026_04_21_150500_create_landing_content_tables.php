<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landing-page CMS bits — features grid and FAQ accordion. SuperAdmin
 * reorders them via drag-and-drop (POST /reorder endpoint updates the
 * sort_order column), so keeping sort_order as an unsigned smallint
 * with an index lets the landing page render in one `ORDER BY` query.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('landing_features', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('icon')->nullable(); // icon name or inline SVG
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('landing_faqs', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->text('answer');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_faqs');
        Schema::dropIfExists('landing_features');
    }
};
