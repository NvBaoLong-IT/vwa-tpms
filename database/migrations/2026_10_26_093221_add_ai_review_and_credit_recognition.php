<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_imports', function (Blueprint $table): void {
            $table->json('comparison_before')->nullable();
            $table->json('comparison_after')->nullable();
            $table->char('comparison_hash', 64)->nullable();
        });
        Schema::create('student_course_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_version_id')->constrained()->restrictOnDelete();
            $table->string('course_code', 30);
            $table->string('semester', 30);
            $table->unsignedSmallInteger('credits');
            $table->string('outcome', 20);
            $table->string('evidence', 255);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_note')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'program_version_id', 'course_code', 'semester'], 'student_course_result_attempt_unique');
        });
        Schema::create('credit_recognitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_version_id')->constrained()->restrictOnDelete();
            $table->json('request_data');
            $table->json('allocation');
            $table->char('baseline_hash', 64);
            $table->string('status', 20)->default('submitted');
            $table->string('decision_number', 100);
            $table->text('note');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_recognitions');
        Schema::dropIfExists('student_course_results');
        Schema::table('document_imports', function (Blueprint $table): void {
            $table->dropColumn(['comparison_before', 'comparison_after', 'comparison_hash']);
        });
    }
};
