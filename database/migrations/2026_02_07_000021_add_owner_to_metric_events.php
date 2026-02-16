<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metric_events', function (Blueprint $table) {
            $table->string('owner_type')->nullable()->after('actor_role');
            $table->unsignedBigInteger('owner_id')->nullable()->after('owner_type');
            $table->index(['owner_type', 'owner_id'], 'metric_events_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::table('metric_events', function (Blueprint $table) {
            $table->dropIndex('metric_events_owner_idx');
            $table->dropColumn(['owner_type', 'owner_id']);
        });
    }
};
