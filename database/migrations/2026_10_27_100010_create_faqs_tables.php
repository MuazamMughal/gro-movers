<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('faqables', function (Blueprint $table) {
            $table->foreignId('faq_id')->constrained()->cascadeOnDelete();
            $table->morphs('faqable');
            $table->unsignedInteger('sort')->default(0);

            $table->primary(['faq_id', 'faqable_type', 'faqable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqables');
        Schema::dropIfExists('faqs');
    }
};
