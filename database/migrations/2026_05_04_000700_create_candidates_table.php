<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users');
            $table->string('headline', 150)->nullable();
            $table->text('bio')->nullable();
            $table->string('location', 150)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('country', 2)->default('EG');
            $table->integer('experience_years')->nullable();
            $table->string('education_level', 20)->nullable();
            $table->string('linkedin_url', 500)->nullable();
            $table->string('github_url', 500)->nullable();
            $table->string('portfolio_url', 500)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->boolean('is_open_to_work')->default(true);
            $table->string('preferred_job_type', 20)->nullable();
            $table->jsonb('preferred_locations')->nullable();
            $table->integer('expected_salary_min')->nullable();
            $table->integer('expected_salary_max')->nullable();
            $table->char('currency', 3)->default('EGP');
            $table->smallInteger('profile_completion_score')->default(0);
            $table->timestampsTz();

            $table->index('is_open_to_work');
            $table->index('city');
            $table->index('country');
            $table->index('preferred_job_type');
        });

        DB::statement('ALTER TABLE candidates ADD CONSTRAINT candidates_profile_completion_score_check CHECK (profile_completion_score >= 0 AND profile_completion_score <= 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
