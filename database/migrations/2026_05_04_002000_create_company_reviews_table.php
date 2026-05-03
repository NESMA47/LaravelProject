<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employer_id')->constrained('employers');
            $table->foreignUuid('candidate_id')->constrained('candidates');
            $table->string('job_title_at_time', 150)->nullable();
            $table->string('employment_type', 20)->nullable();
            $table->boolean('is_current_employee')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->smallInteger('rating_overall');
            $table->smallInteger('rating_work_life_balance')->nullable();
            $table->smallInteger('rating_salary')->nullable();
            $table->smallInteger('rating_culture')->nullable();
            $table->smallInteger('rating_management')->nullable();
            $table->smallInteger('rating_career_growth')->nullable();
            $table->string('title', 200);
            $table->text('pros')->nullable();
            $table->text('cons')->nullable();
            $table->text('advice')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->index(['employer_id', 'is_approved', 'created_at']);
            $table->unique(['employer_id', 'candidate_id']);
        });

        DB::statement('ALTER TABLE company_reviews ADD CONSTRAINT reviews_rating_overall_check CHECK (rating_overall >= 1 AND rating_overall <= 5)');
        DB::statement('ALTER TABLE company_reviews ADD CONSTRAINT reviews_rating_work_life_balance_check CHECK (rating_work_life_balance IS NULL OR (rating_work_life_balance >= 1 AND rating_work_life_balance <= 5))');
        DB::statement('ALTER TABLE company_reviews ADD CONSTRAINT reviews_rating_salary_check CHECK (rating_salary IS NULL OR (rating_salary >= 1 AND rating_salary <= 5))');
        DB::statement('ALTER TABLE company_reviews ADD CONSTRAINT reviews_rating_culture_check CHECK (rating_culture IS NULL OR (rating_culture >= 1 AND rating_culture <= 5))');
        DB::statement('ALTER TABLE company_reviews ADD CONSTRAINT reviews_rating_management_check CHECK (rating_management IS NULL OR (rating_management >= 1 AND rating_management <= 5))');
        DB::statement('ALTER TABLE company_reviews ADD CONSTRAINT reviews_rating_career_growth_check CHECK (rating_career_growth IS NULL OR (rating_career_growth >= 1 AND rating_career_growth <= 5))');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_reviews');
    }
};
