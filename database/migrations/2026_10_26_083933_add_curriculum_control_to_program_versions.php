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
        Schema::table('programs', function (Blueprint $table): void {
            $table->json('course_substitutions')->nullable();
        });
        Schema::table('program_versions', function (Blueprint $table) {
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->foreignId('iso_workflow_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table): void {
            $table->dropColumn('course_substitutions');
        });
        Schema::table('program_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('iso_workflow_id');
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['effective_from', 'effective_until', 'submitted_at', 'reviewed_at', 'review_note']);
        });
    }
};
