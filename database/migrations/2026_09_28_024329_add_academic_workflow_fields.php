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
        if (! Schema::hasColumn('students', 'advisor_user_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->foreignId('advisor_user_id')->nullable()->after('program_id')->constrained('users')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('students', 'faculty_name')) {
            Schema::table('students', fn (Blueprint $table) => $table->string('faculty_name', 150)->nullable()->after('advisor_user_id'));
        }

        if (! Schema::hasColumn('academic_settings', 'degree_level')) {
            Schema::table('academic_settings', function (Blueprint $table) {
                $table->string('degree_level', 20)->nullable()->after('key');
                $table->unique(['key', 'degree_level']);
            });
        }

        if (! Schema::hasTable('student_progress_histories')) {
            Schema::create('student_progress_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_progress_id')->constrained('student_progress')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 30);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->timestamps();
                $table->index(['student_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_progress_histories');
        Schema::table('academic_settings', function (Blueprint $table) {
            $table->dropUnique(['key', 'degree_level']);
            $table->dropColumn('degree_level');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['advisor_user_id']);
            $table->dropColumn(['advisor_user_id', 'faculty_name']);
        });
    }
};
