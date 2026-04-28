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
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employer_id')
                   ->constrained('employer_profiles')
                   ->cascadeOnDelete();

            $table->foreignId('category_id')
                   ->constrained('categories')
                   ->restrictOnDelete(); 

            $table->string('title', 200);
            $table->longText('description');
            $table->text('requirements')->nullable();
            $table->text('benefits')->nullable();

            $table->decimal('salary_min', 10, 2)->nullable();
            $table->decimal('salary_max', 10, 2)->nullable();
             $table->enum('work_type', ['remote', 'onsite', 'hybrid']);
            $table->string('location', 150)->nullable();

            // Admin moderation state machine
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])
                   ->default('pending');
            $table->text('rejection_reason')->nullable(); 

            $table->date('deadline')->nullable();
            $table->unsignedInteger('views_count')->default(0); // analytics

            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('work_type');
            $table->index('location');
            $table->index('deadline');
            $table->fullText(['title', 'description']); // MySQL FULLTEXT keyword search
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_listings');
    }
};
