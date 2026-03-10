<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tests', function (Blueprint $table) {
            if (!Schema::hasColumn('tests', 'category')) {
                $table->string('category')->nullable()->after('code');
            }
            if (!Schema::hasColumn('tests', 'cash_price')) {
                $table->unsignedInteger('cash_price')->nullable()->after('price');
            }
            if (!Schema::hasColumn('tests', 'hmo_price')) {
                $table->unsignedInteger('hmo_price')->nullable()->after('cash_price');
            }
            if (!Schema::hasColumn('tests', 'emergency_price')) {
                $table->unsignedInteger('emergency_price')->nullable()->after('hmo_price');
            }
        });
    }

    public function down()
    {
        Schema::table('tests', function (Blueprint $table) {
            if (Schema::hasColumn('tests', 'emergency_price')) {
                $table->dropColumn('emergency_price');
            }
            if (Schema::hasColumn('tests', 'hmo_price')) {
                $table->dropColumn('hmo_price');
            }
            if (Schema::hasColumn('tests', 'cash_price')) {
                $table->dropColumn('cash_price');
            }
            if (Schema::hasColumn('tests', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
