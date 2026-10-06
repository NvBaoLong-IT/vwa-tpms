<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iso_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('iso_workflow_id')->constrained()->cascadeOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $t->string('original_name');
            $t->string('path');
            $t->string('file_type')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iso_files');
    }
};
