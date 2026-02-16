<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['consult_id']);
            $table->dropForeign(['ward_id']);
        });
        DB::statement('UPDATE appointments SET payment_id = NULL WHERE payment_id = 0');
        DB::statement('UPDATE appointments SET consult_id = NULL WHERE consult_id = 0');
        DB::statement('UPDATE appointments SET ward_id = NULL WHERE ward_id = 0');
        DB::statement('ALTER TABLE appointments MODIFY payment_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE appointments MODIFY consult_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE appointments MODIFY ward_id BIGINT UNSIGNED NULL');
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete()->onUpdate('cascade');
            $table->foreign('consult_id')->references('id')->on('consultations')->nullOnDelete()->onUpdate('cascade');
            $table->foreign('ward_id')->references('id')->on('wards')->nullOnDelete()->onUpdate('cascade');
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['review_id']);
        });
        DB::statement('UPDATE consultations SET payment_id = NULL WHERE payment_id = 0');
        DB::statement('UPDATE consultations SET review_id = NULL WHERE review_id = 0');
        DB::statement('ALTER TABLE consultations MODIFY payment_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE consultations MODIFY review_id BIGINT UNSIGNED NULL');
        Schema::table('consultations', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete()->onUpdate('cascade');
            $table->foreign('review_id')->references('id')->on('reviews')->nullOnDelete()->onUpdate('cascade');
        });

        Schema::table('teletests', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['review_id']);
        });
        DB::statement('UPDATE teletests SET payment_id = NULL WHERE payment_id = 0');
        DB::statement('UPDATE teletests SET review_id = NULL WHERE review_id = 0');
        DB::statement('ALTER TABLE teletests MODIFY payment_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE teletests MODIFY review_id BIGINT UNSIGNED NULL');
        Schema::table('teletests', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete()->onUpdate('cascade');
            $table->foreign('review_id')->references('id')->on('reviews')->nullOnDelete()->onUpdate('cascade');
        });

        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropForeign(['ward_id']);
        });
        DB::statement('UPDATE lab_tests SET ward_id = NULL WHERE ward_id = 0');
        DB::statement('ALTER TABLE lab_tests MODIFY ward_id BIGINT UNSIGNED NULL');
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->foreign('ward_id')->references('id')->on('wards')->nullOnDelete()->onUpdate('cascade');
        });

        Schema::table('drugs', function (Blueprint $table) {
            $table->dropForeign(['ward_id']);
        });
        DB::statement('UPDATE drugs SET ward_id = NULL WHERE ward_id = 0');
        DB::statement('ALTER TABLE drugs MODIFY ward_id BIGINT UNSIGNED NULL');
        Schema::table('drugs', function (Blueprint $table) {
            $table->foreign('ward_id')->references('id')->on('wards')->nullOnDelete()->onUpdate('cascade');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['transfer_id']);
        });
        DB::statement('UPDATE payments SET transfer_id = NULL WHERE transfer_id = 0');
        DB::statement('ALTER TABLE payments MODIFY transfer_id BIGINT UNSIGNED NULL');
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('transfer_id')->references('id')->on('transfers')->nullOnDelete()->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['consult_id']);
            $table->dropForeign(['ward_id']);
        });
        DB::statement('ALTER TABLE appointments MODIFY payment_id BIGINT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE appointments MODIFY consult_id BIGINT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE appointments MODIFY ward_id BIGINT UNSIGNED NOT NULL DEFAULT 0');
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('consult_id')->references('id')->on('consultations')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('ward_id')->references('id')->on('wards')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['review_id']);
        });
        DB::statement('ALTER TABLE consultations MODIFY payment_id BIGINT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE consultations MODIFY review_id BIGINT UNSIGNED NOT NULL DEFAULT 0');
        Schema::table('consultations', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('review_id')->references('id')->on('reviews')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table('teletests', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['review_id']);
        });
        DB::statement('ALTER TABLE teletests MODIFY payment_id BIGINT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE teletests MODIFY review_id BIGINT UNSIGNED NOT NULL DEFAULT 0');
        Schema::table('teletests', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('review_id')->references('id')->on('reviews')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropForeign(['ward_id']);
        });
        DB::statement('ALTER TABLE lab_tests MODIFY ward_id BIGINT UNSIGNED NOT NULL DEFAULT 0');
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->foreign('ward_id')->references('id')->on('wards')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table('drugs', function (Blueprint $table) {
            $table->dropForeign(['ward_id']);
        });
        DB::statement('ALTER TABLE drugs MODIFY ward_id BIGINT UNSIGNED NOT NULL DEFAULT 0');
        Schema::table('drugs', function (Blueprint $table) {
            $table->foreign('ward_id')->references('id')->on('wards')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['transfer_id']);
        });
        DB::statement('ALTER TABLE payments MODIFY transfer_id BIGINT UNSIGNED NOT NULL DEFAULT 0');
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('transfer_id')->references('id')->on('transfers')->onDelete('cascade')->onUpdate('cascade');
        });
    }
};
