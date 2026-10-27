<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_imports', function (Blueprint $table): void {
            $table->string('kind', 40)->default('academic_progress')->after('original_name');
            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('academic_imports', function (Blueprint $table): void {
            $table->dropIndex('academic_imports_kind_created_at_index');
            $table->dropColumn('kind');
        });
    }
};
