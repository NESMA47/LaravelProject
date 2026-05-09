<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // --- applications table changes ---
        Schema::table('applications', function (Blueprint $table) {
            // New columns
            $table->uuid('original_job_id')->after('job_id');
            $table->string('current_status', 30)->after('status')->default('applied');
            $table->timestampTz('job_removed_at')->nullable()->after('current_stage');
            $table->string('resume_url', 500)->nullable()->after('job_removed_at');

            // Make job_id nullable
            $table->uuid('job_id')->nullable()->change();

            // New indexes
            $table->index('job_removed_at');
            $table->index(['job_id', 'applied_at']);
            $table->index(['job_id', 'current_status']);
        });

        // Update existing rows: set original_job_id = job_id
        DB::table('applications')->whereNull('original_job_id')->update([
            'original_job_id' => DB::raw('job_id'),
        ]);

        // Make original_job_id non-nullable after populating
        Schema::table('applications', function (Blueprint $table) {
            $table->uuid('original_job_id')->nullable(false)->change();
        });

        Schema::table('applications', function (Blueprint $table) {
            // Drop old unique and index
            $table->dropUnique(['job_id', 'candidate_id']);
            $table->dropIndex(['job_id', 'status']);

            // Add new unique on original_job_id
            $table->unique(['original_job_id', 'candidate_id']);

            // Drop status column
            $table->dropColumn('status');
        });

        // Update foreign key on job_id to nullOnDelete
        // SQLite: drop and re-add the foreign key constraint
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support altering FK constraints easily.
            // We recreate the table to apply the nullOnDelete behavior.
            // However, in Laravel 13, we can use dropForeign + add.
            // Since SQLite doesn't enforce FK constraints by default in Laravel tests,
            // we'll just make the column nullable and skip the FK recreation for SQLite.
            Schema::table('applications', function (Blueprint $table) {
                $table->dropForeign(['job_id']);
            });
        } else {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropForeign(['job_id']);
                $table->foreign('job_id')->references('id')->on('jobs')->nullOnDelete();
            });
        }

        // --- application_stages table changes ---
        Schema::table('application_stages', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('notes');
        });

        // --- interviews table changes ---
        Schema::table('interviews', function (Blueprint $table) {
            $table->string('cancellation_reason', 30)->nullable()->after('status');
            $table->text('cancellation_note')->nullable()->after('cancellation_reason');
            $table->softDeletesTz();
        });

        // Add CHECK constraint for cancellation_reason on non-SQLite
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE interviews ADD CONSTRAINT interviews_cancellation_reason_check CHECK (cancellation_reason IS NULL OR cancellation_reason IN ('job_removed', 'employer_cancelled', 'candidate_cancelled', 'other'))");
        }
    }

    public function down(): void
    {
        // --- interviews table rollback ---
        Schema::table('interviews', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement('ALTER TABLE interviews DROP CONSTRAINT IF EXISTS interviews_cancellation_reason_check');
            }
            $table->dropColumn(['cancellation_reason', 'cancellation_note', 'deleted_at']);
        });

        // --- application_stages table rollback ---
        Schema::table('application_stages', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });

        // --- applications table rollback ---
        Schema::table('applications', function (Blueprint $table) {
            $table->string('status', 20)->default('applied')->after('candidate_snapshot');
            $table->dropUnique(['original_job_id', 'candidate_id']);
            $table->dropIndex(['job_id', 'current_status']);
            $table->dropIndex(['job_id', 'applied_at']);
            $table->dropIndex(['job_removed_at']);
            $table->dropColumn(['original_job_id', 'current_status', 'job_removed_at', 'resume_url']);

            $table->uuid('job_id')->nullable(false)->change();
            $table->unique(['job_id', 'candidate_id']);
            $table->index(['job_id', 'status']);
        });

        // Restore FK constraint
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropForeign(['job_id']);
                $table->foreign('job_id')->references('id')->on('jobs')->cascadeOnDelete();
            });
        }
    }
};
