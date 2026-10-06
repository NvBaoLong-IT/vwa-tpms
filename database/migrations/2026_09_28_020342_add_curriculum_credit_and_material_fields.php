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
            $table->unsignedSmallInteger('elective_credits_required')->default(0);
        });

        Schema::table('course_materials', function (Blueprint $table) {
            $table->string('material_type', 30)->default('textbook');
        });

        Schema::create('course_material_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 30);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_material_confirmations');
        Schema::table('course_materials', fn (Blueprint $table) => $table->dropColumn('material_type'));
        Schema::table('programs', fn (Blueprint $table) => $table->dropColumn('elective_credits_required'));
    }
};
