<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->onUpdate('cascade')->nullOnDelete();
            $table->foreignId('episode_id')->nullable()->constrained('home_care_episodes')->onUpdate('cascade')->nullOnDelete();
            $table->string('severity', 40);
            $table->string('pathway', 40);
            $table->string('status', 60)->default('escalation_open');
            $table->string('opened_by_role', 50)->nullable();
            $table->unsignedBigInteger('opened_by_id')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity'], 'escalations_status_severity_idx');
            $table->index(['appointment_id', 'status'], 'escalations_appointment_status_idx');
        });

        Schema::create('escalation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escalation_id')->constrained('escalations')->onUpdate('cascade')->onDelete('cascade');
            $table->string('event_type', 80);
            $table->string('actor_role', 50)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->index(['escalation_id', 'created_at'], 'escalation_events_escalation_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_events');
        Schema::dropIfExists('escalations');
    }
};
