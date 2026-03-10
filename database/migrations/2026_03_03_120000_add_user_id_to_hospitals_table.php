<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            if (!Schema::hasColumn('hospitals', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id');
            }
        });

        $rows = DB::table('hospitals')
            ->select('id', 'firedb_id', 'admin_id', 'user_id')
            ->get();

        foreach ($rows as $row) {
            if (!is_null($row->user_id)) {
                continue;
            }

            $userId = $row->admin_id;
            if (is_null($userId) && !empty($row->firedb_id)) {
                $userId = DB::table('users')
                    ->where('firedb_id', $row->firedb_id)
                    ->value('id');
            }

            if (is_null($userId)) {
                continue;
            }

            DB::table('hospitals')
                ->where('id', $row->id)
                ->update([
                    'user_id' => $userId,
                    'admin_id' => $userId,
                ]);
        }

        Schema::table('hospitals', function (Blueprint $table) {
            $table->foreign('user_id', 'hospitals_user_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->unique('user_id', 'hospitals_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            if (Schema::hasColumn('hospitals', 'user_id')) {
                $table->dropUnique('hospitals_user_id_unique');
                $table->dropForeign('hospitals_user_id_foreign');
                $table->dropColumn('user_id');
            }
        });
    }
};

