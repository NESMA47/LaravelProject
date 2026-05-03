<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')->constrained('users');
            $table->string('target_type', 50);
            $table->uuid('target_id');
            $table->string('reason', 50);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignUuid('resolved_by_user_id')->nullable()->constrained('users');
            $table->text('resolution_notes')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_status_check CHECK (status IN ('pending', 'investigating', 'resolved', 'dismissed'))");
        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_reason_check CHECK (reason IN ('spam', 'fraudulent', 'misleading', 'inappropriate', 'discriminatory', 'other'))");
        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_target_type_check CHECK (target_type IN ('job', 'review', 'user', 'employer'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
