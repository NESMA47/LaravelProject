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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('job_id')
                   ->constrained('job_listings')
                   ->cascadeOnDelete();

            $table->foreignId('candidate_id')
                   ->constrained('candidate_profiles')
                   ->cascadeOnDelete();

            $table->string('resume_path')->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();

            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])
                   ->default('pending');
            $table->text('notes')->nullable(); 

            $table->softDeletes();   
            $table->timestamps();
             $table->unique(['job_id', 'candidate_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
