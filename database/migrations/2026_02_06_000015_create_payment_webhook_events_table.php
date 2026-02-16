<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('event');
            $table->string('reference')->nullable();
            $table->string('event_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('signature')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'event', 'reference']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
