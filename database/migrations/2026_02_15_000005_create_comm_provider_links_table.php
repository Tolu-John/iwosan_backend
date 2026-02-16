<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_provider_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('comm_threads')->cascadeOnDelete();
            $table->string('provider')->default('whatsapp');
            $table->string('wa_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('external_id')->nullable();
            $table->timestamps();

            $table->index(['provider', 'wa_id'], 'comm_provider_links_provider_wa_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_provider_links');
    }
};
