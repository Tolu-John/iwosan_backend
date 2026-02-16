<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'tags')) {
                $table->json('tags')->nullable()->after('recomm');
            }
            if (!Schema::hasColumn('reviews', 'status')) {
                $table->string('status')->default('published')->after('tags');
            }
            if (!Schema::hasColumn('reviews', 'response_text')) {
                $table->text('response_text')->nullable()->after('status');
            }
            if (!Schema::hasColumn('reviews', 'response_at')) {
                $table->timestamp('response_at')->nullable()->after('response_text');
            }
            if (!Schema::hasColumn('reviews', 'response_by')) {
                $table->unsignedBigInteger('response_by')->nullable()->after('response_at');
            }
            if (!Schema::hasColumn('reviews', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('response_by');
            }
            if (!Schema::hasColumn('reviews', 'deleted_reason')) {
                $table->string('deleted_reason')->nullable()->after('edited_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'deleted_reason')) {
                $table->dropColumn('deleted_reason');
            }
            if (Schema::hasColumn('reviews', 'edited_at')) {
                $table->dropColumn('edited_at');
            }
            if (Schema::hasColumn('reviews', 'response_by')) {
                $table->dropColumn('response_by');
            }
            if (Schema::hasColumn('reviews', 'response_at')) {
                $table->dropColumn('response_at');
            }
            if (Schema::hasColumn('reviews', 'response_text')) {
                $table->dropColumn('response_text');
            }
            if (Schema::hasColumn('reviews', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('reviews', 'tags')) {
                $table->dropColumn('tags');
            }
        });
    }
};
