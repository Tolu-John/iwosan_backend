<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('lab_results', function (Blueprint $table) {
            $table->dropForeign(['carer_id']);
            $table->dropForeign(['teletest_id']);
        });

        DB::statement('ALTER TABLE lab_results MODIFY carer_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE lab_results MODIFY teletest_id BIGINT UNSIGNED NULL');

        DB::statement('UPDATE lab_results SET teletest_id = NULL WHERE teletest_id = 0');

        Schema::table('lab_results', function (Blueprint $table) {
            $table->foreign('carer_id')->references('id')->on('carers')->nullOnDelete()->onUpdate('cascade');
            $table->foreign('teletest_id')->references('id')->on('teletests')->nullOnDelete()->onUpdate('cascade');
        });
    }

    public function down()
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('lab_results', function (Blueprint $table) {
            $table->dropForeign(['carer_id']);
            $table->dropForeign(['teletest_id']);
        });

        DB::statement('ALTER TABLE lab_results MODIFY carer_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE lab_results MODIFY teletest_id BIGINT UNSIGNED NOT NULL');

        Schema::table('lab_results', function (Blueprint $table) {
            $table->foreign('carer_id')->references('id')->on('carers')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('teletest_id')->references('id')->on('teletests')->onDelete('cascade')->onUpdate('cascade');
        });
    }
};
