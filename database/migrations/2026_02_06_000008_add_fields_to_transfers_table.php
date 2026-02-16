<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('transfers', 'status')) {
                $table->string('status')->default('pending')->after('reason');
            }
            if (!Schema::hasColumn('transfers', 'reference')) {
                $table->string('reference')->nullable()->after('status');
            }
            if (!Schema::hasColumn('transfers', 'currency')) {
                $table->string('currency', 3)->default('NGN')->after('reference');
            }
            if (!Schema::hasColumn('transfers', 'method')) {
                $table->string('method')->nullable()->after('currency');
            }
            if (!Schema::hasColumn('transfers', 'requested_at')) {
                $table->timestamp('requested_at')->nullable()->after('method');
            }
            if (!Schema::hasColumn('transfers', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('requested_at');
            }
            if (!Schema::hasColumn('transfers', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('processed_at');
            }
            if (!Schema::hasColumn('transfers', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('transfers', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('failed_at');
            }
            if (!Schema::hasColumn('transfers', 'requested_by')) {
                $table->unsignedBigInteger('requested_by')->nullable()->after('failure_reason');
            }
            if (!Schema::hasColumn('transfers', 'requested_role')) {
                $table->string('requested_role')->nullable()->after('requested_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            if (Schema::hasColumn('transfers', 'requested_role')) {
                $table->dropColumn('requested_role');
            }
            if (Schema::hasColumn('transfers', 'requested_by')) {
                $table->dropColumn('requested_by');
            }
            if (Schema::hasColumn('transfers', 'failure_reason')) {
                $table->dropColumn('failure_reason');
            }
            if (Schema::hasColumn('transfers', 'failed_at')) {
                $table->dropColumn('failed_at');
            }
            if (Schema::hasColumn('transfers', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
            if (Schema::hasColumn('transfers', 'processed_at')) {
                $table->dropColumn('processed_at');
            }
            if (Schema::hasColumn('transfers', 'requested_at')) {
                $table->dropColumn('requested_at');
            }
            if (Schema::hasColumn('transfers', 'method')) {
                $table->dropColumn('method');
            }
            if (Schema::hasColumn('transfers', 'currency')) {
                $table->dropColumn('currency');
            }
            if (Schema::hasColumn('transfers', 'reference')) {
                $table->dropColumn('reference');
            }
            if (Schema::hasColumn('transfers', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
