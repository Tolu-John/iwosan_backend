<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('carers', function (Blueprint $table) {
            if (!Schema::hasColumn('carers', 'primary_qualification')) {
                $table->string('primary_qualification')->nullable()->after('qualifications');
            }
            if (!Schema::hasColumn('carers', 'specialties')) {
                $table->json('specialties')->nullable()->after('primary_qualification');
            }
            if (!Schema::hasColumn('carers', 'license_number')) {
                $table->string('license_number')->nullable()->after('specialties');
            }
            if (!Schema::hasColumn('carers', 'issuing_body')) {
                $table->string('issuing_body')->nullable()->after('license_number');
            }
            if (!Schema::hasColumn('carers', 'years_experience')) {
                $table->unsignedSmallInteger('years_experience')->nullable()->after('issuing_body');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carers', function (Blueprint $table) {
            if (Schema::hasColumn('carers', 'years_experience')) {
                $table->dropColumn('years_experience');
            }
            if (Schema::hasColumn('carers', 'issuing_body')) {
                $table->dropColumn('issuing_body');
            }
            if (Schema::hasColumn('carers', 'license_number')) {
                $table->dropColumn('license_number');
            }
            if (Schema::hasColumn('carers', 'specialties')) {
                $table->dropColumn('specialties');
            }
            if (Schema::hasColumn('carers', 'primary_qualification')) {
                $table->dropColumn('primary_qualification');
            }
        });
    }
};
