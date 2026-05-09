<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->jsonb('job_snapshot')->nullable()->change();
            $table->jsonb('employer_snapshot')->nullable()->change();
            $table->jsonb('candidate_snapshot')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->jsonb('job_snapshot')->nullable(false)->change();
            $table->jsonb('employer_snapshot')->nullable(false)->change();
            $table->jsonb('candidate_snapshot')->nullable(false)->change();
        });
    }
};
