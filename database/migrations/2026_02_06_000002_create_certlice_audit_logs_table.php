<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certlice_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('certlice_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certlice_audit_logs');
    }
};
