<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('code');
            $table->string('gateway')->nullable()->after('reference');
            $table->timestamp('verified_at')->nullable()->after('gateway');
            $table->string('status_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['reference', 'gateway', 'verified_at', 'status_reason']);
        });
    }
};
