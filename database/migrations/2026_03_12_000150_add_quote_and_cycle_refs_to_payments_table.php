<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('quote_id')->nullable()->after('type_id')->constrained('home_admission_quotes')->onUpdate('cascade')->nullOnDelete();
            $table->foreignId('billing_cycle_id')->nullable()->after('quote_id')->constrained('episode_billing_cycles')->onUpdate('cascade')->nullOnDelete();

            $table->index(['quote_id', 'status'], 'payments_quote_status_idx');
            $table->index(['billing_cycle_id', 'status'], 'payments_billing_cycle_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_quote_status_idx');
            $table->dropIndex('payments_billing_cycle_status_idx');
            $table->dropColumn(['quote_id', 'billing_cycle_id']);
        });
    }
};
