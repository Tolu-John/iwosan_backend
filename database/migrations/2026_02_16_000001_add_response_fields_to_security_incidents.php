<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_incidents', function (Blueprint $table) {
            $table->string('status')->default('open')->after('severity');
            $table->foreignId('assigned_to')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->text('response_notes')->nullable()->after('metadata');
            $table->timestamp('resolved_at')->nullable()->after('detected_at');
            $table->index(['status', 'created_at'], 'security_incidents_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('security_incidents', function (Blueprint $table) {
            $table->dropIndex('security_incidents_status_created_idx');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['status', 'response_notes', 'resolved_at']);
        });
    }
};
