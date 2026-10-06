<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('web_scheduled_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_key', 150)->unique();
            $table->string('task', 40);
            $table->string('period', 20);
            $table->string('origin', 20);
            $table->string('status', 20);
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('retry_after')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['task', 'started_at']);
        });
        DB::table('web_scheduled_runs')->insert(['run_key' => 'activation', 'task' => 'system', 'period' => now()->toDateString(), 'origin' => 'system', 'status' => 'completed', 'started_at' => now(), 'finished_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_scheduled_runs');
    }
};
