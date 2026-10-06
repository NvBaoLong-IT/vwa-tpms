<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warning_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_progress_id')->nullable()->constrained('student_progress')->nullOnDelete();
            $t->enum('warning_type', ['low_gpa', 'credit_debt', 'delayed_progress']);
            $t->text('reason');
            $t->enum('status', ['open', 'processing', 'resolved', 'dismissed'])->default('open');
            $t->timestamp('issued_at');
            $t->timestamp('resolved_at')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'warning_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warning_logs');
    }
};
