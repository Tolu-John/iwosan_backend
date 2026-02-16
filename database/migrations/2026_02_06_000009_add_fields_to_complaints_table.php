<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (!Schema::hasColumn('complaints', 'category')) {
                $table->string('category')->default('other')->after('complaint');
            }
            if (!Schema::hasColumn('complaints', 'severity')) {
                $table->string('severity')->default('low')->after('category');
            }
            if (!Schema::hasColumn('complaints', 'status')) {
                $table->string('status')->default('open')->after('severity');
            }
            if (!Schema::hasColumn('complaints', 'assigned_to')) {
                $table->unsignedBigInteger('assigned_to')->nullable()->after('status');
            }
            if (!Schema::hasColumn('complaints', 'first_response_at')) {
                $table->timestamp('first_response_at')->nullable()->after('assigned_to');
            }
            if (!Schema::hasColumn('complaints', 'response_notes')) {
                $table->text('response_notes')->nullable()->after('first_response_at');
            }
            if (!Schema::hasColumn('complaints', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('response_notes');
            }
            if (!Schema::hasColumn('complaints', 'resolution_notes')) {
                $table->text('resolution_notes')->nullable()->after('resolved_at');
            }
            if (!Schema::hasColumn('complaints', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('resolution_notes');
            }
            if (!Schema::hasColumn('complaints', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('closed_at');
            }
            if (!Schema::hasColumn('complaints', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (Schema::hasColumn('complaints', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
            if (Schema::hasColumn('complaints', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
            if (Schema::hasColumn('complaints', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
            if (Schema::hasColumn('complaints', 'resolution_notes')) {
                $table->dropColumn('resolution_notes');
            }
            if (Schema::hasColumn('complaints', 'resolved_at')) {
                $table->dropColumn('resolved_at');
            }
            if (Schema::hasColumn('complaints', 'response_notes')) {
                $table->dropColumn('response_notes');
            }
            if (Schema::hasColumn('complaints', 'first_response_at')) {
                $table->dropColumn('first_response_at');
            }
            if (Schema::hasColumn('complaints', 'assigned_to')) {
                $table->dropColumn('assigned_to');
            }
            if (Schema::hasColumn('complaints', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('complaints', 'severity')) {
                $table->dropColumn('severity');
            }
            if (Schema::hasColumn('complaints', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
