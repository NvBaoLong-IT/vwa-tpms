<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('diploma_ledgers', function (Blueprint $table) {
            $table->string('cohort_code', 30)->nullable();
            $table->string('decision_code', 50)->nullable();
            $table->string('holder_name')->nullable();
            $table->date('holder_date_of_birth')->nullable();
            $table->string('holder_ethnicity', 100)->nullable();
            $table->string('amendment_decision', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diploma_ledgers', function (Blueprint $table) {
            $table->dropColumn(['cohort_code', 'decision_code', 'holder_name', 'holder_date_of_birth', 'holder_ethnicity', 'amendment_decision']);
        });
    }
};
