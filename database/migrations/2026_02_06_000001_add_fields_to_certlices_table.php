<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certlices', function (Blueprint $table) {
            if (!Schema::hasColumn('certlices', 'cert_type')) {
                $table->string('cert_type')->nullable()->after('type');
            }
            if (!Schema::hasColumn('certlices', 'issuer')) {
                $table->string('issuer')->nullable()->after('cert_type');
            }
            if (!Schema::hasColumn('certlices', 'license_number')) {
                $table->string('license_number')->nullable()->after('issuer');
            }
            if (!Schema::hasColumn('certlices', 'status')) {
                $table->string('status')->default('pending')->after('license_number');
            }
            if (!Schema::hasColumn('certlices', 'issued_at')) {
                $table->date('issued_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('certlices', 'expires_at')) {
                $table->date('expires_at')->nullable()->after('issued_at');
            }
            if (!Schema::hasColumn('certlices', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('expires_at');
            }
            if (!Schema::hasColumn('certlices', 'verified_by')) {
                $table->unsignedBigInteger('verified_by')->nullable()->after('verified_at');
            }
            if (!Schema::hasColumn('certlices', 'notes')) {
                $table->text('notes')->nullable()->after('verified_by');
            }
            if (!Schema::hasColumn('certlices', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('certlices', function (Blueprint $table) {
            if (Schema::hasColumn('certlices', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('certlices', 'verified_by')) {
                $table->dropColumn('verified_by');
            }
            if (Schema::hasColumn('certlices', 'verified_at')) {
                $table->dropColumn('verified_at');
            }
            if (Schema::hasColumn('certlices', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
            if (Schema::hasColumn('certlices', 'issued_at')) {
                $table->dropColumn('issued_at');
            }
            if (Schema::hasColumn('certlices', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('certlices', 'license_number')) {
                $table->dropColumn('license_number');
            }
            if (Schema::hasColumn('certlices', 'issuer')) {
                $table->dropColumn('issuer');
            }
            if (Schema::hasColumn('certlices', 'cert_type')) {
                $table->dropColumn('cert_type');
            }
            if (Schema::hasColumn('certlices', 'created_at')) {
                $table->dropTimestamps();
            }
        });
    }
};
