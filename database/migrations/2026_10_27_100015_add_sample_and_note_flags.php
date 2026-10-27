<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Seeded placeholder reviews: shown with a warning in admin, never used for rating markup.
        Schema::table('reviews', function (Blueprint $table) {
            $table->boolean('is_sample')->default(false)->after('is_active');
        });

        // A note for admins on a page (e.g. "review with legal before launch").
        Schema::table('pages', function (Blueprint $table) {
            $table->string('admin_note', 500)->nullable()->after('template');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', fn (Blueprint $table) => $table->dropColumn('is_sample'));
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('admin_note'));
    }
};
