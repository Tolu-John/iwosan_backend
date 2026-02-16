<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            if (!Schema::hasColumn('drugs', 'status_reason')) {
                $table->string('status_reason')->nullable()->after('status');
            }
        });

        Schema::table('lab_tests', function (Blueprint $table) {
            if (!Schema::hasColumn('lab_tests', 'status_reason')) {
                $table->string('status_reason')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            if (Schema::hasColumn('drugs', 'status_reason')) {
                $table->dropColumn('status_reason');
            }
        });

        Schema::table('lab_tests', function (Blueprint $table) {
            if (Schema::hasColumn('lab_tests', 'status_reason')) {
                $table->dropColumn('status_reason');
            }
        });
    }
};
