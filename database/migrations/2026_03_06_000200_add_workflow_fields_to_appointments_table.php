<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('owned_by_role')->nullable()->after('status_description');
            $table->unsignedBigInteger('owned_by_id')->nullable()->after('owned_by_role');
            $table->timestamp('next_action_at')->nullable()->after('owned_by_id');

            $table->index(['owned_by_role', 'owned_by_id'], 'appointments_owner_idx');
            $table->index('next_action_at', 'appointments_next_action_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_owner_idx');
            $table->dropIndex('appointments_next_action_idx');
            $table->dropColumn(['owned_by_role', 'owned_by_id', 'next_action_at']);
        });
    }
};

