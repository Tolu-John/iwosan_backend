<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['patient_id', 'status'], 'appointments_patient_status_idx');
            $table->index(['carer_id', 'status'], 'appointments_carer_status_idx');
            $table->index(['payment_id'], 'appointments_payment_idx');
            $table->index(['consult_id'], 'appointments_consult_idx');
            $table->index(['ward_id'], 'appointments_ward_idx');
            $table->index(['date_time'], 'appointments_date_time_idx');
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->index(['patient_id', 'status'], 'consultations_patient_status_idx');
            $table->index(['carer_id', 'status'], 'consultations_carer_status_idx');
            $table->index(['hospital_id', 'status'], 'consultations_hospital_status_idx');
            $table->index(['payment_id'], 'consultations_payment_idx');
            $table->index(['review_id'], 'consultations_review_idx');
            $table->index(['date_time'], 'consultations_date_time_idx');
        });

        Schema::table('teletests', function (Blueprint $table) {
            $table->index(['patient_id', 'status'], 'teletests_patient_status_idx');
            $table->index(['carer_id', 'status'], 'teletests_carer_status_idx');
            $table->index(['hospital_id', 'status'], 'teletests_hospital_status_idx');
            $table->index(['payment_id'], 'teletests_payment_idx');
            $table->index(['review_id'], 'teletests_review_idx');
            $table->index(['date_time'], 'teletests_date_time_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['patient_id', 'status'], 'payments_patient_status_idx');
            $table->index(['carer_id', 'status'], 'payments_carer_status_idx');
            $table->index(['type', 'type_id'], 'payments_type_type_id_idx');
            $table->index(['reference'], 'payments_reference_idx');
            $table->index(['gateway_transaction_id'], 'payments_gateway_tx_idx');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->index(['hospital_id', 'status'], 'transfers_hospital_status_idx');
            $table->index(['carer_id', 'status'], 'transfers_carer_status_idx');
            $table->index(['payment_id'], 'transfers_payment_idx');
            $table->index(['requested_at'], 'transfers_requested_at_idx');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['patient_id'], 'reviews_patient_idx');
            $table->index(['carer_id'], 'reviews_carer_idx');
            $table->index(['consultation_id'], 'reviews_consultation_idx');
            $table->index(['status'], 'reviews_status_idx');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->index(['patient_id', 'status'], 'complaints_patient_status_idx');
            $table->index(['hospital_id', 'status'], 'complaints_hospital_status_idx');
            $table->index(['category'], 'complaints_category_idx');
            $table->index(['severity'], 'complaints_severity_idx');
        });

        Schema::table('wards', function (Blueprint $table) {
            $table->index(['patient_id'], 'wards_patient_idx');
            $table->index(['carer_id'], 'wards_carer_idx');
            $table->index(['hospital_id'], 'wards_hospital_idx');
            $table->index(['appt_id'], 'wards_appt_idx');
            $table->index(['discharged'], 'wards_discharged_idx');
        });

        Schema::table('lab_results', function (Blueprint $table) {
            $table->index(['patient_id'], 'lab_results_patient_idx');
            $table->index(['carer_id'], 'lab_results_carer_idx');
            $table->index(['teletest_id'], 'lab_results_teletest_idx');
            $table->index(['created_at'], 'lab_results_created_at_idx');
        });

        Schema::table('lab_tests', function (Blueprint $table) {
            $table->index(['consultation_id'], 'lab_tests_consultation_idx');
            $table->index(['ward_id'], 'lab_tests_ward_idx');
            $table->index(['status'], 'lab_tests_status_idx');
        });

        Schema::table('drugs', function (Blueprint $table) {
            $table->index(['consultation_id'], 'drugs_consultation_idx');
            $table->index(['ward_id'], 'drugs_ward_idx');
            $table->index(['status'], 'drugs_status_idx');
        });

        Schema::table('gen_vitals', function (Blueprint $table) {
            $table->index(['patient_id', 'type'], 'gen_vitals_patient_type_idx');
            $table->index(['taken_at'], 'gen_vitals_taken_at_idx');
        });

        Schema::table('ward_audit_logs', function (Blueprint $table) {
            $table->index(['ward_id', 'created_at'], 'ward_audit_logs_ward_created_idx');
        });

        Schema::table('ward_vital_audit_logs', function (Blueprint $table) {
            $table->index(['ward_id', 'created_at'], 'ward_vital_audit_logs_ward_created_idx');
        });

        Schema::table('lab_result_audit_logs', function (Blueprint $table) {
            $table->index(['lab_result_id', 'created_at'], 'lab_result_audit_logs_result_created_idx');
        });

        Schema::table('review_audit_logs', function (Blueprint $table) {
            $table->index(['review_id', 'created_at'], 'review_audit_logs_review_created_idx');
        });

        Schema::table('complaint_audit_logs', function (Blueprint $table) {
            $table->index(['complaint_id', 'created_at'], 'complaint_audit_logs_complaint_created_idx');
        });

        Schema::table('payment_audit_logs', function (Blueprint $table) {
            $table->index(['payment_id', 'created_at'], 'payment_audit_logs_payment_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_patient_status_idx');
            $table->dropIndex('appointments_carer_status_idx');
            $table->dropIndex('appointments_payment_idx');
            $table->dropIndex('appointments_consult_idx');
            $table->dropIndex('appointments_ward_idx');
            $table->dropIndex('appointments_date_time_idx');
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropIndex('consultations_patient_status_idx');
            $table->dropIndex('consultations_carer_status_idx');
            $table->dropIndex('consultations_hospital_status_idx');
            $table->dropIndex('consultations_payment_idx');
            $table->dropIndex('consultations_review_idx');
            $table->dropIndex('consultations_date_time_idx');
        });

        Schema::table('teletests', function (Blueprint $table) {
            $table->dropIndex('teletests_patient_status_idx');
            $table->dropIndex('teletests_carer_status_idx');
            $table->dropIndex('teletests_hospital_status_idx');
            $table->dropIndex('teletests_payment_idx');
            $table->dropIndex('teletests_review_idx');
            $table->dropIndex('teletests_date_time_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_patient_status_idx');
            $table->dropIndex('payments_carer_status_idx');
            $table->dropIndex('payments_type_type_id_idx');
            $table->dropIndex('payments_reference_idx');
            $table->dropIndex('payments_gateway_tx_idx');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropIndex('transfers_hospital_status_idx');
            $table->dropIndex('transfers_carer_status_idx');
            $table->dropIndex('transfers_payment_idx');
            $table->dropIndex('transfers_requested_at_idx');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_patient_idx');
            $table->dropIndex('reviews_carer_idx');
            $table->dropIndex('reviews_consultation_idx');
            $table->dropIndex('reviews_status_idx');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex('complaints_patient_status_idx');
            $table->dropIndex('complaints_hospital_status_idx');
            $table->dropIndex('complaints_category_idx');
            $table->dropIndex('complaints_severity_idx');
        });

        Schema::table('wards', function (Blueprint $table) {
            $table->dropIndex('wards_patient_idx');
            $table->dropIndex('wards_carer_idx');
            $table->dropIndex('wards_hospital_idx');
            $table->dropIndex('wards_appt_idx');
            $table->dropIndex('wards_discharged_idx');
        });

        Schema::table('lab_results', function (Blueprint $table) {
            $table->dropIndex('lab_results_patient_idx');
            $table->dropIndex('lab_results_carer_idx');
            $table->dropIndex('lab_results_teletest_idx');
            $table->dropIndex('lab_results_created_at_idx');
        });

        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropIndex('lab_tests_consultation_idx');
            $table->dropIndex('lab_tests_ward_idx');
            $table->dropIndex('lab_tests_status_idx');
        });

        Schema::table('drugs', function (Blueprint $table) {
            $table->dropIndex('drugs_consultation_idx');
            $table->dropIndex('drugs_ward_idx');
            $table->dropIndex('drugs_status_idx');
        });

        Schema::table('gen_vitals', function (Blueprint $table) {
            $table->dropIndex('gen_vitals_patient_type_idx');
            $table->dropIndex('gen_vitals_taken_at_idx');
        });

        Schema::table('ward_audit_logs', function (Blueprint $table) {
            $table->dropIndex('ward_audit_logs_ward_created_idx');
        });

        Schema::table('ward_vital_audit_logs', function (Blueprint $table) {
            $table->dropIndex('ward_vital_audit_logs_ward_created_idx');
        });

        Schema::table('lab_result_audit_logs', function (Blueprint $table) {
            $table->dropIndex('lab_result_audit_logs_result_created_idx');
        });

        Schema::table('review_audit_logs', function (Blueprint $table) {
            $table->dropIndex('review_audit_logs_review_created_idx');
        });

        Schema::table('complaint_audit_logs', function (Blueprint $table) {
            $table->dropIndex('complaint_audit_logs_complaint_created_idx');
        });

        Schema::table('payment_audit_logs', function (Blueprint $table) {
            $table->dropIndex('payment_audit_logs_payment_created_idx');
        });
    }
};
