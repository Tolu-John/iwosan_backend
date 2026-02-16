<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('actor_role')->nullable();
            $table->string('owner_type')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->decimal('conversion_rate', 6, 3)->nullable();
            $table->decimal('completion_rate', 6, 3)->nullable();
            $table->decimal('refund_rate', 6, 3)->nullable();
            $table->json('counts')->nullable();
            $table->timestamps();

            $table->unique(['date', 'actor_role', 'owner_type', 'owner_id'], 'metric_daily_date_role_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_daily_summaries');
    }
};
