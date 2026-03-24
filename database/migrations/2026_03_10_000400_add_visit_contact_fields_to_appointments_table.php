<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('visit_contact_name', 255)->nullable()->after('additional_notes');
            $table->string('visit_contact_phone', 80)->nullable()->after('visit_contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['visit_contact_name', 'visit_contact_phone']);
        });
    }
};
