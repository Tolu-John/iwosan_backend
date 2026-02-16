<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('reviews')
            ->select('patient_id', 'consultation_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('patient_id', 'consultation_id')
            ->having('cnt', '>', 1)
            ->first();

        if ($duplicates) {
            throw new RuntimeException('Duplicate reviews found. Clean up duplicates before adding unique constraint.');
        }

        Schema::table('reviews', function (Blueprint $table) {
            $table->unique(['patient_id', 'consultation_id'], 'reviews_patient_consult_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique('reviews_patient_consult_unique');
        });
    }
};
