<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('hospitals', 'long')) {
            Schema::table('hospitals', function (Blueprint $table) {
                $table->renameColumn('long', 'lon');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hospitals', 'lon')) {
            Schema::table('hospitals', function (Blueprint $table) {
                $table->renameColumn('lon', 'long');
            });
        }
    }
};
