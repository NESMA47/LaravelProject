<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('candidate_id')->constrained('candidates');
            $table->string('title', 150);
            $table->foreignUuid('file_id')->constrained('files');
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();

            $table->index('candidate_id');
            $table->index(['candidate_id', 'is_default']);
        });

        // Partial unique index (PostgreSQL-only) removed; enforced via ResumeObserver in application layer
    }

    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
