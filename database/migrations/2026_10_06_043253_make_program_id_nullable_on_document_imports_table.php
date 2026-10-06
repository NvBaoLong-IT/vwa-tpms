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
        Schema::table('document_imports', function (Blueprint $table): void {
            $table->dropForeign(['program_id']);
            $table->foreignId('program_id')->nullable()->change();
            $table->foreign('program_id')->references('id')->on('programs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_imports', function (Blueprint $table): void {
            $table->dropForeign(['program_id']);
            $table->foreignId('program_id')->nullable(false)->change();
            $table->foreign('program_id')->references('id')->on('programs')->cascadeOnDelete();
        });
    }
};
