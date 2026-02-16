<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->nullable()->constrained('comm_threads')->nullOnDelete();
            $table->string('direction')->nullable();
            $table->string('event_type')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('sender_role')->nullable();
            $table->timestamp('event_timestamp')->nullable();
            $table->string('delivery_status')->nullable();
            $table->string('signature_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'event_type'], 'comm_events_thread_type_idx');
            $table->index(['provider_message_id'], 'comm_events_provider_msg_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_events');
    }
};
