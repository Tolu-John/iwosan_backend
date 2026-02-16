<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timelines', function (Blueprint $table) {
            if (!Schema::hasColumn('timelines', 'author_id')) {
                $table->unsignedBigInteger('author_id')->nullable()->after('type_id');
            }
            if (!Schema::hasColumn('timelines', 'author_role')) {
                $table->string('author_role')->nullable()->after('author_id');
            }
            if (!Schema::hasColumn('timelines', 'meta')) {
                $table->json('meta')->nullable()->after('author_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('timelines', function (Blueprint $table) {
            if (Schema::hasColumn('timelines', 'meta')) {
                $table->dropColumn('meta');
            }
            if (Schema::hasColumn('timelines', 'author_role')) {
                $table->dropColumn('author_role');
            }
            if (Schema::hasColumn('timelines', 'author_id')) {
                $table->dropColumn('author_id');
            }
        });
    }
};
