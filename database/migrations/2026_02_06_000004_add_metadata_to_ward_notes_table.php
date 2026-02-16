<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ward_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('ward_notes', 'note_type')) {
                $table->string('note_type')->default('observation')->after('text');
            }
            if (!Schema::hasColumn('ward_notes', 'author_id')) {
                $table->unsignedBigInteger('author_id')->nullable()->after('note_type');
            }
            if (!Schema::hasColumn('ward_notes', 'author_role')) {
                $table->string('author_role')->nullable()->after('author_id');
            }
            if (!Schema::hasColumn('ward_notes', 'recorded_at')) {
                $table->timestamp('recorded_at')->nullable()->after('author_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ward_notes', function (Blueprint $table) {
            if (Schema::hasColumn('ward_notes', 'recorded_at')) {
                $table->dropColumn('recorded_at');
            }
            if (Schema::hasColumn('ward_notes', 'author_role')) {
                $table->dropColumn('author_role');
            }
            if (Schema::hasColumn('ward_notes', 'author_id')) {
                $table->dropColumn('author_id');
            }
            if (Schema::hasColumn('ward_notes', 'note_type')) {
                $table->dropColumn('note_type');
            }
        });
    }
};
