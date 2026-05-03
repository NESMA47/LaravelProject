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

        DB::statement('CREATE UNIQUE INDEX resumes_candidate_id_is_default_unique ON resumes (candidate_id, is_default) WHERE is_default = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
