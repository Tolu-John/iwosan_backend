<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tests', function (Blueprint $table) {
            if (!Schema::hasColumn('tests', 'fasting_required')) {
                $table->boolean('fasting_required')->default(false)->after('preparation_notes');
            }
        });
    }

    public function down()
    {
        Schema::table('tests', function (Blueprint $table) {
            if (Schema::hasColumn('tests', 'fasting_required')) {
                $table->dropColumn('fasting_required');
            }
        });
    }
};
