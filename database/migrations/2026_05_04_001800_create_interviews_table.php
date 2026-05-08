<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained('applications');
            $table->timestampTz('scheduled_at');
            $table->smallInteger('duration_minutes')->default(60);
            $table->string('location_type', 20);
            $table->string('location_details', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->foreignUuid('created_by_user_id')->constrained('users');
            $table->timestampsTz();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE interviews ADD CONSTRAINT interviews_status_check CHECK (status IN ('scheduled', 'completed', 'cancelled', 'no_show'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
