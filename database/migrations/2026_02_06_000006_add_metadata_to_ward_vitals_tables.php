<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'ward_temps',
            'ward_weights',
            'ward_bp_sys',
            'ward_bp_dias',
            'ward_sugars',
            'other_vitals',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'taken_at')) {
                    $table->timestamp('taken_at')->nullable();
                }
                if (!Schema::hasColumn($tableName, 'recorded_at')) {
                    $table->timestamp('recorded_at')->nullable();
                }
                if (!Schema::hasColumn($tableName, 'source')) {
                    $table->string('source')->nullable();
                }
                if ($tableName === 'other_vitals' && !Schema::hasColumn($tableName, 'unit')) {
                    $table->string('unit')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'ward_temps',
            'ward_weights',
            'ward_bp_sys',
            'ward_bp_dias',
            'ward_sugars',
            'other_vitals',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if ($tableName === 'other_vitals' && Schema::hasColumn($tableName, 'unit')) {
                    $table->dropColumn('unit');
                }
                if (Schema::hasColumn($tableName, 'source')) {
                    $table->dropColumn('source');
                }
                if (Schema::hasColumn($tableName, 'recorded_at')) {
                    $table->dropColumn('recorded_at');
                }
                if (Schema::hasColumn($tableName, 'taken_at')) {
                    $table->dropColumn('taken_at');
                }
            });
        }
    }
};
