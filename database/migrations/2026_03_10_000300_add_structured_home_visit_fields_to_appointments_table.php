<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('dispatch_model', 80)->nullable()->after('channel');
            $table->string('address_source', 80)->nullable()->after('dispatch_model');
            $table->string('contact_profile', 80)->nullable()->after('address_source');
            $table->text('visit_reason')->nullable()->after('contact_profile');
            $table->string('preferred_window', 120)->nullable()->after('visit_reason');
            $table->string('expected_duration', 120)->nullable()->after('preferred_window');
            $table->json('red_flags_json')->nullable()->after('expected_duration');
            $table->unsignedBigInteger('preferred_hospital_id')->nullable()->after('red_flags_json');
            $table->string('preferred_hospital_name', 255)->nullable()->after('preferred_hospital_id');
            $table->unsignedBigInteger('preferred_clinician_id')->nullable()->after('preferred_hospital_name');
            $table->string('preferred_clinician_name', 255)->nullable()->after('preferred_clinician_id');
            $table->string('preference_note', 500)->nullable()->after('preferred_clinician_name');
            $table->string('additional_notes', 1000)->nullable()->after('preference_note');

            $table->index('preferred_hospital_id', 'appointments_pref_hospital_idx');
            $table->index('preferred_clinician_id', 'appointments_pref_clinician_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_pref_hospital_idx');
            $table->dropIndex('appointments_pref_clinician_idx');
            $table->dropColumn([
                'dispatch_model',
                'address_source',
                'contact_profile',
                'visit_reason',
                'preferred_window',
                'expected_duration',
                'red_flags_json',
                'preferred_hospital_id',
                'preferred_hospital_name',
                'preferred_clinician_id',
                'preferred_clinician_name',
                'preference_note',
                'additional_notes',
            ]);
        });
    }
};
