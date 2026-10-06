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
        Schema::table('students', function (Blueprint $table) {
            $table->date('expected_graduation_date')->nullable();
            $table->date('graduated_at')->nullable();
            $table->date('withdrawn_at')->nullable();
            $table->date('proposal_deadline')->nullable();
            $table->date('proposal_submitted_at')->nullable();
            $table->date('department_defense_deadline')->nullable();
            $table->date('department_defended_at')->nullable();
            $table->date('academy_defense_deadline')->nullable();
            $table->date('academy_defended_at')->nullable();
            $table->date('maximum_study_end_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['expected_graduation_date', 'graduated_at', 'withdrawn_at', 'proposal_deadline', 'proposal_submitted_at', 'department_defense_deadline', 'department_defended_at', 'academy_defense_deadline', 'academy_defended_at', 'maximum_study_end_date']);
        });
    }
};
