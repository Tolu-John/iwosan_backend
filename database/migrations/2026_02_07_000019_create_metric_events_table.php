<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_role')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'created_at'], 'metric_events_type_created_idx');
            $table->index(['model_type', 'model_id'], 'metric_events_model_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_events');
    }
};
