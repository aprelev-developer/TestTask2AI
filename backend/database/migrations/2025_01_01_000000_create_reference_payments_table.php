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
        Schema::create('reference_payments', function (Blueprint $table) {
            // id == run_id: the reference payment a check run is validated against.
            $table->uuid('id')->primary();
            $table->string('address');
            $table->string('amount');
            $table->string('network');
            $table->json('allowed_scripts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reference_payments');
    }
};
