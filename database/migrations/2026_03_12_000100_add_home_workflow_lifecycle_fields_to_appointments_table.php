<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('scheduled_at');
            $table->timestamp('departed_at')->nullable()->after('approved_at');
            $table->timestamp('arrived_at')->nullable()->after('departed_at');
            $table->string('assignment_source', 80)->nullable()->after('owned_by_id');
            $table->unsignedInteger('current_eta_minutes')->nullable()->after('assignment_source');
            $table->string('home_window_code', 60)->nullable()->after('preferred_window');
            $table->string('home_window_label', 120)->nullable()->after('home_window_code');
            $table->string('status_reason_code', 120)->nullable()->after('status_reason');
            $table->text('status_reason_note')->nullable()->after('status_reason_code');

            $table->index(['status', 'approved_at'], 'appointments_status_approved_idx');
            $table->index(['status', 'arrived_at'], 'appointments_status_arrived_idx');
            $table->index('home_window_code', 'appointments_home_window_code_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_status_approved_idx');
            $table->dropIndex('appointments_status_arrived_idx');
            $table->dropIndex('appointments_home_window_code_idx');

            $table->dropColumn([
                'approved_at',
                'departed_at',
                'arrived_at',
                'assignment_source',
                'current_eta_minutes',
                'home_window_code',
                'home_window_label',
                'status_reason_code',
                'status_reason_note',
            ]);
        });
    }
};
