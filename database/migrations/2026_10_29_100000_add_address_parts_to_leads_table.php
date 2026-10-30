<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The address parts Photon (OpenStreetMap) returns, beyond city, state, ZIP and coordinates. */
    private const PARTS = ['house_number', 'street', 'neighborhood', 'county', 'state_name', 'country', 'country_code', 'osm_id'];

    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach (['from', 'to'] as $prefix) {
                $table->string("{$prefix}_house_number", 20)->nullable()->after("{$prefix}_address");
                $table->string("{$prefix}_street")->nullable()->after("{$prefix}_house_number");
                $table->string("{$prefix}_neighborhood", 100)->nullable()->after("{$prefix}_street");
                $table->string("{$prefix}_county", 100)->nullable()->after("{$prefix}_city");
                $table->string("{$prefix}_state_name", 100)->nullable()->after("{$prefix}_state");
                $table->string("{$prefix}_country", 100)->nullable()->after("{$prefix}_zip");
                $table->char("{$prefix}_country_code", 2)->nullable()->after("{$prefix}_country");
                // OpenStreetMap object, e.g. "N12021744966" (node), "W432096036" (way), "R6571629" (relation).
                $table->string("{$prefix}_osm_id", 30)->nullable()->after("{$prefix}_lng");
            }
        });

        // Google Places is replaced by Photon: drop its key, add the Photon server URL.
        Setting::query()->where('key', 'integrations.google_places_key')->delete();
        Setting::query()->firstOrCreate(
            ['key' => 'integrations.address_api_url'],
            ['group' => 'integrations', 'value' => config('settings.defaults')['integrations.address_api_url']],
        );
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(collect(['from', 'to'])->crossJoin(self::PARTS)->map(fn ($c) => "{$c[0]}_{$c[1]}")->all());
        });

        Setting::query()->where('key', 'integrations.address_api_url')->delete();
        Setting::query()->firstOrCreate(['key' => 'integrations.google_places_key'], ['group' => 'integrations', 'value' => null]);
    }
};
