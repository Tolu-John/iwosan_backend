<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_transaction_id')->nullable()->after('reference');
            $table->string('channel')->nullable()->after('gateway_transaction_id');
            $table->string('currency')->nullable()->after('channel');
            $table->unsignedInteger('fees')->nullable()->after('currency');
            $table->json('gateway_payload')->nullable()->after('fees');
            $table->timestamp('processing_at')->nullable()->after('verified_at');
            $table->timestamp('paid_at')->nullable()->after('processing_at');
            $table->timestamp('failed_at')->nullable()->after('paid_at');
            $table->timestamp('refunded_at')->nullable()->after('failed_at');
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_transaction_id',
                'channel',
                'currency',
                'fees',
                'gateway_payload',
                'processing_at',
                'paid_at',
                'failed_at',
                'refunded_at',
            ]);
        });
    }
};
