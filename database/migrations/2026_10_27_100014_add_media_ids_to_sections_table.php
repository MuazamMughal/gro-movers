<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Media IDs referenced anywhere in a section's data, kept in sync on save so
     * the media library can tell where an image is used.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->json('media_ids')->nullable()->after('data');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('media_ids');
        });
    }
};
