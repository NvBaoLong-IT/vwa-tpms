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
        Schema::table('iso_workflows', function (Blueprint $table) {
            $table->unsignedSmallInteger('changed_credits')->nullable();
        });
        Schema::table('iso_steps', function (Blueprint $table) {
            $table->timestamp('overdue_notified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iso_steps', fn (Blueprint $table) => $table->dropColumn('overdue_notified_at'));
        Schema::table('iso_workflows', fn (Blueprint $table) => $table->dropColumn('changed_credits'));
    }
};
