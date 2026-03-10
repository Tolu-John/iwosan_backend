<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->string('plan_type', 32);
            $table->string('status', 24)->default('active');
            $table->unsignedSmallInteger('version')->default(1);
            $table->json('payload_json')->nullable();
            $table->unsignedBigInteger('entered_by')->nullable();
            $table->timestamp('entered_at')->nullable();
            $table->timestamps();
            $table->index(['consultation_id', 'plan_type']);
        });

        Schema::create('consultation_medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('consultation_plans')->nullOnDelete();
            $table->string('drug_name');
            $table->decimal('strength_value', 10, 2)->nullable();
            $table->string('strength_unit', 32)->nullable();
            $table->decimal('dose_amount', 10, 2)->nullable();
            $table->string('dose_unit', 32)->nullable();
            $table->string('route', 32)->nullable();
            $table->string('formulation', 32)->nullable();
            $table->string('frequency_code', 32)->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->boolean('prn')->default(false);
            $table->string('max_daily_dose', 64)->nullable();
            $table->text('indication')->nullable();
            $table->text('instructions')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('stop_at')->nullable();
            $table->timestamps();
            $table->index(['consultation_id', 'drug_name']);
        });

        Schema::create('consultation_lab_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('consultation_plans')->nullOnDelete();
            $table->string('urgency', 24)->default('routine');
            $table->boolean('fasting_required')->default(false);
            $table->text('clinical_question')->nullable();
            $table->string('status', 24)->default('ordered');
            $table->timestamps();
        });

        Schema::create('consultation_lab_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('consultation_lab_orders')->cascadeOnDelete();
            $table->string('test_name', 120);
            $table->string('test_code', 64)->nullable();
            $table->string('specimen', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('consultation_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('consultation_plans')->nullOnDelete();
            $table->string('destination_type', 32)->nullable();
            $table->string('specialty', 120)->nullable();
            $table->text('reason')->nullable();
            $table->string('urgency', 24)->default('routine');
            $table->string('status', 24)->default('active');
            $table->timestamps();
        });

        Schema::create('consultation_care_advices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('consultation_plans')->nullOnDelete();
            $table->string('advice_code', 64)->nullable();
            $table->string('label', 120);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('consultation_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('consultation_plans')->nullOnDelete();
            $table->json('monitoring_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_observations');
        Schema::dropIfExists('consultation_care_advices');
        Schema::dropIfExists('consultation_referrals');
        Schema::dropIfExists('consultation_lab_order_items');
        Schema::dropIfExists('consultation_lab_orders');
        Schema::dropIfExists('consultation_medications');
        Schema::dropIfExists('consultation_plans');
    }
};

