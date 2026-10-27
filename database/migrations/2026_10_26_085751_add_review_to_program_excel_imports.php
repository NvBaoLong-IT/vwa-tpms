<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_excel_imports', function (Blueprint $table): void {
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->char('reviewed_payload_hash', 64)->nullable();
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('program_excel_imports', function (Blueprint $table): void {
            $table->dropIndex(['status', 'submitted_at']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['submitted_at', 'reviewed_at', 'review_note', 'reviewed_payload_hash']);
        });
    }
};
