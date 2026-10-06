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
        Schema::create('diploma_blanks', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number')->unique();
            $table->enum('type', ['dai_hoc', 'thac_si', 'tien_si', 'chung_chi']);
            $table->date('import_date');
            $table->enum('status', ['in_stock', 'used', 'damaged', 'destroyed'])->default('in_stock');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diploma_blanks');
    }
};
