<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            $table->string('sample_type')->nullable()->after('code');
            $table->string('turnaround_time')->nullable()->after('sample_type');
            $table->text('preparation_notes')->nullable()->after('turnaround_time');
            $table->boolean('is_active')->default(1)->after('preparation_notes');
            $table->string('status_reason')->nullable()->after('is_active');
        });
    }

    public function down()
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'sample_type',
                'turnaround_time',
                'preparation_notes',
                'is_active',
                'status_reason',
            ]);
        });
    }
};
