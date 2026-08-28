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
        Schema::create('detection_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->uuid('request_id');
            $table->string('result');
            $table->json('triggered_scenarios');
            $table->json('details');
            $table->json('incomplete_checks');
            $table->timestamp('created_at')->useCurrent();

            $table->index('run_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detection_events');
    }
};
