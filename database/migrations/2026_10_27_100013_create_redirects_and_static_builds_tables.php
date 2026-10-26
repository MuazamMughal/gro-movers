<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path', 2048);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Static HTML publisher log.
        Schema::create('static_builds', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('status', 10);
            $table->text('error')->nullable();
            $table->timestamp('built_at');

            $table->index(['path', 'built_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('static_builds');
        Schema::dropIfExists('redirects');
    }
};
