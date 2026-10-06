<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('warning_logs', function (Blueprint $table) {
            $table->string('warning_type', 50)->change();
        });
        DB::table('academic_settings')->where('key', 'gpa_below')->whereIn('value', ['2', '2.0'])->where(function ($query) {
            $query->whereNull('degree_level')->orWhere('degree_level', 'dai_hoc');
        })->update(['value' => '1.6', 'updated_at' => now()]);
        foreach (['graduation_lookahead_days' => ['180', 'Số ngày dự báo tốt nghiệp'], 'milestone_lookahead_days' => ['30', 'Số ngày nhắc mốc sau đại học']] as $key => [$value, $label]) {
            foreach ([null, 'dai_hoc', 'thac_si', 'tien_si'] as $degree) {
                DB::table('academic_settings')->updateOrInsert(['key' => $key, 'degree_level' => $degree], ['value' => $value, 'label' => $label, 'type' => 'number', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing warnings may use new types, so narrowing the column would discard data.
    }
};
