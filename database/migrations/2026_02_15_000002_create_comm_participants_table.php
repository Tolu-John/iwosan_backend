<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('comm_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role')->nullable();
            $table->string('wa_id')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'role'], 'comm_participants_thread_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_participants');
    }
};
