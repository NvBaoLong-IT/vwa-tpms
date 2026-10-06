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
        Schema::create('diploma_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->enum('diploma_type', ['dai_hoc', 'thac_si', 'tien_si', 'chung_chi']);
            $table->string('reg_number')->unique();
            $table->string('serial_number')->unique();
            $table->date('issue_date');
            $table->string('decision_number')->nullable();
            $table->enum('status', ['issued', 'replaced', 'cancelled'])->default('issued');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diploma_ledgers');
    }
};
