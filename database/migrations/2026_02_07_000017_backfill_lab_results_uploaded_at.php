<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lab_results')
            ->whereNull('uploaded_at')
            ->update(['uploaded_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // no-op: cannot safely revert data backfill
    }
};
