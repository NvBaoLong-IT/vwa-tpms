<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_write_locks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('curriculum_write_locks')->insert(['id' => 1]);
        Schema::table('document_imports', function (Blueprint $table): void {
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approval_expires_at')->nullable();
            $table->text('review_note')->nullable();
            $table->char('approved_hash', 64)->nullable();
        });
        DB::table('program_versions')->where('status', 'approved')
            ->where(fn ($query) => $query->whereNull('submitted_by')->orWhereNull('reviewed_by')->orWhereColumn('submitted_by', 'reviewed_by'))
            ->update(['status' => 'returned', 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => 'Cần trình lại và được lãnh đạo khác người trình phê duyệt theo quy tắc kiểm soát mới.']);
    }

    public function down(): void
    {
        Schema::table('document_imports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn(['submitted_at', 'approval_expires_at', 'review_note', 'approved_hash']);
        });
        Schema::dropIfExists('curriculum_write_locks');
    }
};
