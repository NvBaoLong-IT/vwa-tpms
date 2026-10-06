<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->string('semester', 30);
            $t->unsignedSmallInteger('credits_accumulated')->default(0);
            $t->decimal('gpa_4', 3, 2)->nullable();
            $t->unsignedSmallInteger('credits_required')->nullable();
            $t->date('recorded_at')->nullable();
            $t->timestamps();
            $t->unique(['student_id', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_progress');
    }
};
