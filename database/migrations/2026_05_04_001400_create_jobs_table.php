<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employer_id')->constrained('employers');
            $table->foreignUuid('posted_by_user_id')->constrained('users');
            $table->foreignUuid('category_id')->nullable()->constrained('categories');
            $table->string('title', 200);
            $table->string('slug', 250)->unique();
            $table->text('description');
            $table->text('requirements');
            $table->text('responsibilities')->nullable();
            $table->text('benefits')->nullable();
            $table->string('type', 20);
            $table->string('workplace_type', 20);
            $table->string('experience_level', 20);
            $table->string('career_level', 50)->nullable();
            $table->string('education_level', 20)->nullable();
            $table->integer('salary_min')->nullable();
            $table->integer('salary_max')->nullable();
            $table->char('currency', 3)->default('EGP');
            $table->boolean('is_salary_visible')->default(true);
            $table->string('location', 200);
            $table->string('city', 100)->nullable();
            $table->char('country', 2)->default('EG');
            $table->smallInteger('vacancies')->default(1);
            $table->string('status', 20)->default('draft');
            $table->timestampTz('expires_at')->nullable();
            $table->integer('views_count')->default(0);
            $table->integer('applications_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestampTz('featured_until')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->text('rejection_reason')->nullable();

            $table->index(['employer_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['status', 'is_featured', 'created_at']);
            $table->index(['city', 'country', 'status']);
        });

        DB::statement("ALTER TABLE jobs ADD CONSTRAINT jobs_status_check CHECK (status IN ('draft', 'pending_review', 'active', 'paused', 'closed', 'rejected', 'expired'))");
        DB::statement('ALTER TABLE jobs ADD CONSTRAINT jobs_vacancies_check CHECK (vacancies > 0)');
        DB::statement("CREATE INDEX jobs_fulltext_search ON jobs USING GIN (to_tsvector('english', title || ' ' || COALESCE(description, '') || ' ' || COALESCE(requirements, '')))");
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
