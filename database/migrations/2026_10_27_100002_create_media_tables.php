<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('disk', 32)->default('public');
            $table->string('folder', 16);
            $table->string('filename');
            $table->string('original_path');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('preset', 32)->nullable();
            $table->json('crop')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('format', 8);
            $table->string('path');
            $table->unsignedInteger('size');
            $table->timestamps();

            $table->unique(['media_id', 'width', 'format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('media');
    }
};
