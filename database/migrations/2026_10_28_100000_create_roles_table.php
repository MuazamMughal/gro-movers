<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('description', 255)->nullable();
            $table->json('permissions');
            // The built-in Administrator role: every permission, cannot be edited or deleted.
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('email')->constrained()->restrictOnDelete();
        });

        // Everyone who existed before roles keeps full access.
        $now = now();
        $admin = DB::table('roles')->insertGetId([
            'name' => 'Administrator',
            'description' => 'Full access, including users and roles.',
            'permissions' => '[]',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('users')->update(['role_id' => $admin]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
        Schema::dropIfExists('roles');
    }
};
