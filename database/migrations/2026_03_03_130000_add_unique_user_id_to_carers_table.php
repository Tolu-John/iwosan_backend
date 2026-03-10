<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicates = DB::table('carers')
            ->select('user_id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('user_id')
            ->having('aggregate', '>', 1)
            ->pluck('user_id');

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot add unique index on carers.user_id. Duplicate user_id values found: '
                .$duplicates->implode(', ')
            );
        }

        Schema::table('carers', function (Blueprint $table) {
            $table->unique('user_id', 'carers_user_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carers', function (Blueprint $table) {
            $table->dropUnique('carers_user_id_unique');
        });
    }
};
