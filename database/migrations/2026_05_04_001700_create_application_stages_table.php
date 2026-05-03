<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained('applications');
            $table->string('stage', 20);
            $table->text('notes')->nullable();
            $table->foreignUuid('changed_by_user_id')->nullable()->constrained('users');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_stages');
    }
};
