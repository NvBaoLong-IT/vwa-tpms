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
        try {
            Schema::table('academic_settings', fn (Blueprint $table) => $table->dropUnique('academic_settings_key_unique'));
        } catch (Throwable) {
            // The legacy index was already removed on upgraded MySQL databases.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The composite key remains the authoritative uniqueness constraint.
    }
};
