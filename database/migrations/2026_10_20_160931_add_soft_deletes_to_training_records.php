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
        Schema::table('training_records', function (Blueprint $table) {
            foreach (['programs', 'courses', 'students', 'diploma_ledgers', 'diploma_blanks'] as $name) {
                Schema::table($name, fn (Blueprint $table) => $table->softDeletes());
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_records', function (Blueprint $table) {
            foreach (['programs', 'courses', 'students', 'diploma_ledgers', 'diploma_blanks'] as $name) {
                Schema::table($name, fn (Blueprint $table) => $table->dropSoftDeletes());
            }
        });
    }
};
