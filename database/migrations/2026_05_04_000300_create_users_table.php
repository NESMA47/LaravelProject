<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 255)->unique();
            $table->string('password_hash', 255);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('avatar_url', 500)->nullable();
            $table->uuid('avatar_file_id')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('role', 20)->default('candidate');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('role');
            $table->index(['is_active', 'deleted_at']);
            $table->index('created_at');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('candidate', 'employer', 'admin'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
