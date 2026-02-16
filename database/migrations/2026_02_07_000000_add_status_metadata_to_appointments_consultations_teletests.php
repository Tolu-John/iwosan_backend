<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('status_description')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('no_show_at')->nullable();
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->string('status_description')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('no_show_at')->nullable();
        });

        Schema::table('teletests', function (Blueprint $table) {
            $table->string('status_description')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('no_show_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'status_description',
                'scheduled_at',
                'started_at',
                'completed_at',
                'cancelled_at',
                'no_show_at',
            ]);
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn([
                'status_description',
                'scheduled_at',
                'started_at',
                'completed_at',
                'cancelled_at',
                'no_show_at',
            ]);
        });

        Schema::table('teletests', function (Blueprint $table) {
            $table->dropColumn([
                'status_description',
                'scheduled_at',
                'started_at',
                'completed_at',
                'cancelled_at',
                'no_show_at',
            ]);
        });
    }
};
