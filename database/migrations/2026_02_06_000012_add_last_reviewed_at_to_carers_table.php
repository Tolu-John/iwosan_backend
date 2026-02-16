<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('carers', function (Blueprint $table) {
            $table->timestamp('last_reviewed_at')->nullable()->after('super_admin_approved');
        });
    }

    public function down()
    {
        Schema::table('carers', function (Blueprint $table) {
            $table->dropColumn('last_reviewed_at');
        });
    }
};
