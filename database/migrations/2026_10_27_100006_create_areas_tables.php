<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('city');
            $table->string('state_name');
            $table->char('state_code', 2);
            $table->string('slug')->unique();
            $table->text('intro')->nullable();
            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('zip_codes')->nullable();
            $table->text('map_embed')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_menu')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Services offered in an area.
        Schema::create('area_service', function (Blueprint $table) {
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->primary(['area_id', 'service_id']);
        });

        Schema::create('area_neighbors', function (Blueprint $table) {
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('neighbor_id')->constrained('areas')->cascadeOnDelete();

            $table->primary(['area_id', 'neighbor_id']);
        });

        // "{Service} in {City}" landing pages; sections and SEO attach via morphs.
        Schema::create('area_service_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('use_template')->default(true);
            $table->string('title_override')->nullable();
            $table->timestamps();

            $table->unique(['area_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_service_pages');
        Schema::dropIfExists('area_neighbors');
        Schema::dropIfExists('area_service');
        Schema::dropIfExists('areas');
    }
};
