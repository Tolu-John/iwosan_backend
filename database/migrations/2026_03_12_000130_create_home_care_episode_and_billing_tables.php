<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_care_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('patient_id')->constrained('patients')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('hospital_id')->constrained('hospitals')->onUpdate('cascade')->onDelete('cascade');
            $table->string('episode_status', 60)->default('home_admitted_active');
            $table->foreignId('current_quote_id')->nullable()->constrained('home_admission_quotes')->onUpdate('cascade')->nullOnDelete();
            $table->json('care_plan_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'episode_status'], 'home_care_episodes_patient_status_idx');
            $table->index(['hospital_id', 'episode_status'], 'home_care_episodes_hospital_status_idx');
        });

        Schema::create('episode_billing_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('home_care_episodes')->onUpdate('cascade')->onDelete('cascade');
            $table->date('cycle_start');
            $table->date('cycle_end');
            $table->timestamp('due_at');
            $table->unsignedBigInteger('amount_due_minor')->default(0);
            $table->unsignedBigInteger('amount_paid_minor')->default(0);
            $table->string('billing_status', 40)->default('due');
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('grace_until')->nullable();
            $table->timestamps();

            $table->index(['episode_id', 'due_at'], 'episode_billing_cycles_episode_due_idx');
            $table->index(['billing_status', 'due_at'], 'episode_billing_cycles_status_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_billing_cycles');
        Schema::dropIfExists('home_care_episodes');
    }
};
