<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gen_vitals', function (Blueprint $table) {
            $table->string('type')->nullable()->after('name');
            $table->string('unit')->nullable()->after('value');
            $table->decimal('value_num', 10, 2)->nullable()->after('unit');
            $table->decimal('systolic', 10, 2)->nullable()->after('value_num');
            $table->decimal('diastolic', 10, 2)->nullable()->after('systolic');
            $table->decimal('pulse', 10, 2)->nullable()->after('diastolic');
            $table->timestamp('taken_at')->nullable()->after('pulse');
            $table->timestamp('recorded_at')->nullable()->after('taken_at');
            $table->string('context')->nullable()->after('recorded_at');
            $table->string('source')->nullable()->after('context');
            $table->string('device_name')->nullable()->after('source');
            $table->string('device_model')->nullable()->after('device_name');
            $table->string('device_serial')->nullable()->after('device_model');
            $table->string('location')->nullable()->after('device_serial');
            $table->text('notes')->nullable()->after('location');
            $table->string('status_flag')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('gen_vitals', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'unit',
                'value_num',
                'systolic',
                'diastolic',
                'pulse',
                'taken_at',
                'recorded_at',
                'context',
                'source',
                'device_name',
                'device_model',
                'device_serial',
                'location',
                'notes',
                'status_flag',
            ]);
        });
    }
};
