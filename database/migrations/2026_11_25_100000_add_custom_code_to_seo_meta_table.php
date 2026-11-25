<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-page third-party code (SEO tab → Custom code): printed in that page's <head> and before </body>.
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->text('head_code')->nullable()->after('schema_extra');
            $table->text('body_code')->nullable()->after('head_code');
        });

        // Existing editor roles get the new permission, as on a fresh install (config/permissions.php).
        foreach (DB::table('roles')->whereIn('name', ['Content Editor', 'SEO Manager'])->get() as $role) {
            $permissions = json_decode($role->permissions, true) ?: [];

            if (! in_array('custom_code', $permissions, true)) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode([...$permissions, 'custom_code'])]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->dropColumn(['head_code', 'body_code']);
        });

        foreach (DB::table('roles')->get() as $role) {
            $permissions = json_decode($role->permissions, true) ?: [];
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values(array_diff($permissions, ['custom_code'])))]);
        }
    }
};
