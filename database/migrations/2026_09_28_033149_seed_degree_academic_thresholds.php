<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            DB::statement('alter table academic_settings drop index academic_settings_key_unique');
        } catch (Throwable) {
            // The legacy unique index is absent on fresh installations after the schema migration.
        }
        $defaults = DB::table('academic_settings')->whereNull('degree_level')->get();
        foreach (['dai_hoc', 'thac_si', 'tien_si'] as $degree) {
            foreach ($defaults as $setting) {
                DB::table('academic_settings')->updateOrInsert(['key' => $setting->key, 'degree_level' => $degree], ['value' => $setting->value, 'label' => $setting->label, 'type' => $setting->type, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('academic_settings')->whereIn('degree_level', ['dai_hoc', 'thac_si', 'tien_si'])->delete();
    }
};
