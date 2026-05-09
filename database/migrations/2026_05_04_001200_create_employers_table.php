<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users');
            $table->string('company_name', 150);
            $table->string('slug', 150)->unique();
            $table->string('logo_url', 500)->nullable();
            $table->foreignUuid('logo_file_id')->nullable()->constrained('files');
            $table->string('cover_image_url', 500)->nullable();
            $table->foreignUuid('cover_image_file_id')->nullable()->constrained('files');
            $table->string('industry', 100)->nullable();
            $table->string('company_size', 20)->nullable();
            $table->smallInteger('founded_year')->nullable();
            $table->string('website', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('headquarters', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('country', 2)->default('EG');
            $table->boolean('is_verified')->default(false);
            $table->foreignUuid('verification_document_id')->nullable()->constrained('files');
            $table->decimal('average_rating', 2, 1)->default(0.0);
            $table->integer('total_reviews')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('is_verified');
            $table->index('industry');
            $table->index('city');
            $table->index('country');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE employers ADD CONSTRAINT employers_average_rating_check CHECK (average_rating >= 0 AND average_rating <= 5)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employers');
    }
};
