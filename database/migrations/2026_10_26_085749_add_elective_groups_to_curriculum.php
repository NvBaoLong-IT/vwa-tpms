<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table): void {
            $table->json('elective_groups')->nullable();
        });
        Schema::table('program_course', function (Blueprint $table): void {
            $table->string('elective_group_code', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('program_course', function (Blueprint $table): void {
            $table->dropColumn('elective_group_code');
        });
        Schema::table('programs', function (Blueprint $table): void {
            $table->dropColumn('elective_groups');
        });
    }
};
