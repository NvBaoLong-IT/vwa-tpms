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
        Schema::table('programs', function (Blueprint $table) {
            $table->json('required_knowledge_blocks')->nullable();
        });

        Schema::create('program_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('cohort_year');
            $table->unsignedSmallInteger('revision');
            $table->string('decision_number', 100);
            $table->date('decision_date');
            $table->string('status', 20)->default('draft');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'cohort_year', 'revision']);
            $table->index(['program_id', 'cohort_year', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_versions');
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('required_knowledge_blocks');
        });
    }
};
