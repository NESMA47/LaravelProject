<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_team_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employer_id')->constrained('employers');
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('role_in_company', 50);
            $table->boolean('is_primary')->default(false);
            $table->foreignUuid('invited_by')->nullable()->constrained('users');
            $table->timestampsTz();

            $table->unique(['employer_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_team_members');
    }
};
