<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iso_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['new', 'major', 'minor']);
            $table->string('title');
            $table->date('start_date');
            $table->date('expected_end_date');
            $table->enum('status', ['draft', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['status', 'expected_end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iso_workflows');
    }
};
