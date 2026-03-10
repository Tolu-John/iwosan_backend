<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('push_notifications_enabled')->default(true)->after('other_kin_address');
            $table->boolean('sms_alerts_enabled')->default(true)->after('push_notifications_enabled');
            $table->boolean('share_vitals_with_carers')->default(true)->after('sms_alerts_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'push_notifications_enabled',
                'sms_alerts_enabled',
                'share_vitals_with_carers',
            ]);
        });
    }
};

