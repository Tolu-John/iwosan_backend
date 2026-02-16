<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('wards', function (Blueprint $table) {
            $table->text('discharge_summary')->nullable()->after('discharge_date');
        });
    }

    public function down()
    {
        Schema::table('wards', function (Blueprint $table) {
            $table->dropColumn('discharge_summary');
        });
    }
};
