<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->constrained('jobs');
            $table->foreignUuid('skill_id')->constrained('skills');
            $table->boolean('is_required')->default(true);
            $table->string('min_proficiency', 20)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['job_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_skills');
    }
};
