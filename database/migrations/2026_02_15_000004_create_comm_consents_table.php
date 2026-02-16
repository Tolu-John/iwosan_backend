<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->string('channel')->default('whatsapp');
            $table->string('scope')->nullable();
            $table->string('version')->nullable();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->timestamp('consented_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'channel'], 'comm_consents_user_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_consents');
    }
};
