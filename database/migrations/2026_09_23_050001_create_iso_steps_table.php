<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iso_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iso_workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step_number');
            $table->string('step_name');
            $table->date('deadline');
            $table->enum('status', ['not_started', 'in_progress', 'waiting_library', 'waiting_approval', 'completed'])->default('not_started');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['iso_workflow_id', 'step_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iso_steps');
    }
};
