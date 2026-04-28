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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('application_id')
                   ->unique()
                   ->constrained('applications')
                   ->restrictOnDelete(); 

            $table->foreignId('employer_id')
                   ->constrained('employer_profiles')
                   ->restrictOnDelete();

            $table->string('provider', 30);    // 'stripe' | 'paypal'

            $table->string('provider_tx_id')->unique();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD'); 

            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])
                   ->default('pending');
             $table->json('metadata')->nullable();

            $table->timestamps();
            $table->index('status');
        });
    } 

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
