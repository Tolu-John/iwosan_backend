<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->string('status')->default('ordered')->after('done');
            $table->timestamp('scheduled_at')->nullable()->after('status');
            $table->timestamp('collected_at')->nullable()->after('scheduled_at');
            $table->timestamp('resulted_at')->nullable()->after('collected_at');
        });
    }

    public function down(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropColumn(['status', 'scheduled_at', 'collected_at', 'resulted_at']);
        });
    }
};
