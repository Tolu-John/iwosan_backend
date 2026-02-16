<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_results', function (Blueprint $table) {
            $table->string('result_picture_front')->nullable()->after('result_picture');
            $table->string('result_picture_back')->nullable()->after('result_picture_front');
        });
    }

    public function down(): void
    {
        Schema::table('lab_results', function (Blueprint $table) {
            $table->dropColumn(['result_picture_front', 'result_picture_back']);
        });
    }
};
