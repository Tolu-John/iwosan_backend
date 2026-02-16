<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carers', function (Blueprint $table) {
            if (!Schema::hasColumn('carers', 'service_radius_km')) {
                $table->unsignedSmallInteger('service_radius_km')->nullable()->after('home_day_time');
            }
            if (!Schema::hasColumn('carers', 'response_time_minutes')) {
                $table->unsignedSmallInteger('response_time_minutes')->nullable()->after('service_radius_km');
            }
        });
    }

    public function down(): void
    {
        Schema::table('carers', function (Blueprint $table) {
            if (Schema::hasColumn('carers', 'response_time_minutes')) {
                $table->dropColumn('response_time_minutes');
            }
            if (Schema::hasColumn('carers', 'service_radius_km')) {
                $table->dropColumn('service_radius_km');
            }
        });
    }
};
