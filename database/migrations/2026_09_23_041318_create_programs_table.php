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
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name_vi');
            $table->string('name_en')->nullable();
            $table->enum('degree_level', ['dai_hoc', 'thac_si', 'tien_si']);
            $table->string('delivery_mode', 100)->nullable();
            $table->unsignedSmallInteger('total_credits');
            $table->string('decision_number', 100)->nullable();
            $table->date('decision_date')->nullable();
            $table->unsignedTinyInteger('review_cycle_years')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['degree_level', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
