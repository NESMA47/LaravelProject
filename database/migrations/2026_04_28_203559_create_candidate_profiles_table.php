<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('candidate_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                   ->unique()
                   ->constrained('users')
                   ->cascadeOnDelete();

            $table->string('headline', 200)->nullable(); // "Full-Stack Dev | 3 yrs"
            $table->text('bio')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('resume_path')->nullable();  // default resume on profile
            $table->json('skills')->nullable();         // ["PHP","Vue","MySQL"]
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_profiles');
    }
};
