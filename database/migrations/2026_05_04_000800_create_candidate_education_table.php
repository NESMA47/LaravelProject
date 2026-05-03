<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_education', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('candidate_id')->constrained('candidates');
            $table->string('degree', 150);
            $table->string('institution', 200);
            $table->string('field_of_study', 150);
            $table->smallInteger('start_year');
            $table->smallInteger('end_year')->nullable();
            $table->string('grade', 50)->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('description')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_education');
    }
};
