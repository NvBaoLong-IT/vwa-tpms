<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $t) {
            $t->id();
            $t->string('student_code', 50)->unique();
            $t->string('full_name');
            $t->date('date_of_birth')->nullable();
            $t->string('class_code', 50)->nullable();
            $t->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('enrollment_year')->nullable();
            $t->enum('degree_level', ['dai_hoc', 'thac_si', 'tien_si'])->default('dai_hoc');
            $t->string('email')->nullable();
            $t->enum('status', ['active', 'graduated', 'withdrawn'])->default('active');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
