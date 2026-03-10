<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_clinical_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->text('chief_complaint')->nullable();
            $table->json('chief_complaint_symptoms')->nullable();
            $table->string('chief_complaint_duration', 64)->nullable();
            $table->string('chief_complaint_severity', 32)->nullable();
            $table->text('history')->nullable();
            $table->text('diagnosis')->nullable();
            $table->string('risk_level', 32)->nullable();
            $table->string('disposition', 64)->nullable();
            $table->string('duration_bucket', 64)->nullable();
            $table->text('treatment_note')->nullable();
            $table->timestamps();
            $table->index('consultation_id');
        });

        Schema::create('consultation_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->string('next_step', 64)->nullable();
            $table->string('timing', 64)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('consultation_id');
        });

        Schema::create('consultation_warning_signs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->string('label', 160);
            $table->timestamps();
            $table->index('consultation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_warning_signs');
        Schema::dropIfExists('consultation_follow_ups');
        Schema::dropIfExists('consultation_clinical_summaries');
    }
};

