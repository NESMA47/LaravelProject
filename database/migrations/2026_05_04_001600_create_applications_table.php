<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->constrained('jobs');
            $table->foreignUuid('candidate_id')->constrained('candidates');
            $table->text('cover_letter')->nullable();
            $table->jsonb('job_snapshot');
            $table->jsonb('employer_snapshot');
            $table->jsonb('candidate_snapshot');
            $table->string('status', 20)->default('applied');
            $table->string('current_stage', 50)->nullable();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->string('withdrawn_reason', 255)->nullable();
            $table->timestampTz('applied_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['job_id', 'status']);
            $table->index(['candidate_id', 'applied_at']);
            $table->unique(['job_id', 'candidate_id']);
        });

        DB::statement("ALTER TABLE applications ADD CONSTRAINT applications_status_check CHECK (status IN ('applied', 'reviewed', 'shortlisted', 'interviewed', 'offered', 'hired', 'rejected', 'withdrawn'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
