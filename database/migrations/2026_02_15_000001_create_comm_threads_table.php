<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->string('channel')->default('whatsapp');
            $table->string('provider_thread_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_message_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_role')->nullable();
            $table->timestamps();

            $table->index(['consultation_id', 'channel'], 'comm_threads_consult_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_threads');
    }
};
