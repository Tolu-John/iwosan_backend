<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vital_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vital_id');
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type')->nullable();
            $table->string('action');
            $table->text('snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vital_audit_logs');
    }
};
