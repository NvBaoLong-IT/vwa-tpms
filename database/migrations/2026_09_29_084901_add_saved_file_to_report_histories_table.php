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
        Schema::table('report_histories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('file_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_histories', function (Blueprint $table) {
            $table->dropColumn('file_path');
            // Automated reports have no user, so retain nullable ownership on rollback.
        });
    }
};
