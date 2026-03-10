<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('consent_accepted')->default(false)->after('extra_notes');
            $table->json('attachments_json')->nullable()->after('consent_accepted');
            $table->decimal('address_lat', 10, 7)->nullable()->after('address');
            $table->decimal('address_lon', 10, 7)->nullable()->after('address_lat');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'consent_accepted',
                'attachments_json',
                'address_lat',
                'address_lon',
            ]);
        });
    }
};

