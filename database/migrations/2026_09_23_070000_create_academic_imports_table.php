<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('original_name');
            $t->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $t->unsignedInteger('rows_imported')->default(0);
            $t->unsignedInteger('error_count')->default(0);
            $t->json('errors')->nullable();
            $t->text('failure_message')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_imports');
    }
};
