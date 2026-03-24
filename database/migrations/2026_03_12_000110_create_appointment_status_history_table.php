<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->onUpdate('cascade')->onDelete('cascade');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('action_key', 120)->nullable();
            $table->string('actor_role', 50)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason_code', 120)->nullable();
            $table->text('reason_note')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'created_at'], 'appointment_status_history_appt_created_idx');
            $table->index(['to_status', 'created_at'], 'appointment_status_history_to_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_history');
    }
};
