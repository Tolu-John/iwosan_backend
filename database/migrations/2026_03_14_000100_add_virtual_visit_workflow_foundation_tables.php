<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'status_reason_note')) {
                $table->text('status_reason_note')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'queued_at')) {
                $table->timestamp('queued_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'waiting_room_opened_at')) {
                $table->timestamp('waiting_room_opened_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'clinician_admitted_at')) {
                $table->timestamp('clinician_admitted_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'consent_required_at')) {
                $table->timestamp('consent_required_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'consent_granted_at')) {
                $table->timestamp('consent_granted_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'consent_version')) {
                $table->string('consent_version', 40)->nullable();
            }
            if (!Schema::hasColumn('appointments', 'session_link_sent_at')) {
                $table->timestamp('session_link_sent_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'session_ready_at')) {
                $table->timestamp('session_ready_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'clinician_joined_at')) {
                $table->timestamp('clinician_joined_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'session_live_at')) {
                $table->timestamp('session_live_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'session_interrupted_at')) {
                $table->timestamp('session_interrupted_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'session_failed_at')) {
                $table->timestamp('session_failed_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'session_ended_at')) {
                $table->timestamp('session_ended_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'closeout_started_at')) {
                $table->timestamp('closeout_started_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'closeout_submitted_at')) {
                $table->timestamp('closeout_submitted_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'review_prompted_at')) {
                $table->timestamp('review_prompted_at')->nullable();
            }
            if (!Schema::hasColumn('appointments', 'record_released_at')) {
                $table->timestamp('record_released_at')->nullable();
            }
        });

        if (!Schema::hasTable('virtual_visit_status_history')) {
            Schema::create('virtual_visit_status_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')
                    ->constrained('appointments')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->string('action_key', 120)->nullable();
                $table->string('actor_role', 50)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('reason_code', 120)->nullable();
                $table->text('reason_note')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['appointment_id', 'created_at'], 'virtual_visit_status_hist_appt_created_idx');
                $table->index(['to_status', 'created_at'], 'virtual_visit_status_hist_to_created_idx');
            });
        }

        if (!Schema::hasTable('virtual_session_events')) {
            Schema::create('virtual_session_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')
                    ->constrained('appointments')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
                $table->string('event_type', 80);
                $table->timestamp('event_at')->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamps();

                $table->index(['appointment_id', 'event_at'], 'virtual_session_events_appt_event_idx');
                $table->index(['event_type', 'event_at'], 'virtual_session_events_type_event_idx');
            });
        }

        if (!Schema::hasTable('virtual_visit_consents')) {
            Schema::create('virtual_visit_consents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')
                    ->constrained('appointments')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
                $table->string('consent_version', 40);
                $table->string('consent_text_hash', 128)->nullable();
                $table->unsignedBigInteger('granted_by_user_id')->nullable();
                $table->timestamp('granted_at');
                $table->string('channel', 30)->default('in_app');
                $table->string('ip', 64)->nullable();
                $table->string('device_fingerprint', 255)->nullable();
                $table->timestamps();

                $table->index(['appointment_id', 'granted_at'], 'virtual_visit_consents_appt_granted_idx');
                $table->index(['granted_by_user_id', 'granted_at'], 'virtual_visit_consents_user_granted_idx');
            });
        }

        if (!Schema::hasTable('virtual_visit_disputes')) {
            Schema::create('virtual_visit_disputes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')
                    ->constrained('appointments')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
                $table->string('status', 60)->default('dispute_open');
                $table->string('reason_code', 120)->nullable();
                $table->text('reason_note')->nullable();
                $table->string('opened_by_role', 30)->nullable();
                $table->unsignedBigInteger('opened_by_id')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->text('resolution_summary')->nullable();
                $table->unsignedBigInteger('resolved_by_id')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['appointment_id', 'status'], 'virtual_visit_disputes_appt_status_idx');
                $table->index(['status', 'opened_at'], 'virtual_visit_disputes_status_opened_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_visit_disputes');
        Schema::dropIfExists('virtual_visit_consents');
        Schema::dropIfExists('virtual_session_events');
        Schema::dropIfExists('virtual_visit_status_history');

        Schema::table('appointments', function (Blueprint $table) {
            foreach ([
                'status_reason_note',
                'queued_at',
                'waiting_room_opened_at',
                'clinician_admitted_at',
                'consent_required_at',
                'consent_granted_at',
                'consent_version',
                'session_link_sent_at',
                'session_ready_at',
                'clinician_joined_at',
                'session_live_at',
                'session_interrupted_at',
                'session_failed_at',
                'session_ended_at',
                'closeout_started_at',
                'closeout_submitted_at',
                'review_prompted_at',
                'record_released_at',
            ] as $column) {
                if (Schema::hasColumn('appointments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

