<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teletests', function (Blueprint $table) {
            if (!Schema::hasColumn('teletests', 'status_reason_note')) {
                $table->text('status_reason_note')->nullable();
            }
            if (!Schema::hasColumn('teletests', 'departed_at')) {
                $table->timestamp('departed_at')->nullable();
            }
            if (!Schema::hasColumn('teletests', 'arrived_at')) {
                $table->timestamp('arrived_at')->nullable();
            }
            if (!Schema::hasColumn('teletests', 'reassigned_at')) {
                $table->timestamp('reassigned_at')->nullable();
            }
            if (!Schema::hasColumn('teletests', 'reassigned_from')) {
                $table->unsignedBigInteger('reassigned_from')->nullable();
            }
            if (!Schema::hasColumn('teletests', 'reassigned_to')) {
                $table->unsignedBigInteger('reassigned_to')->nullable();
            }
            if (!Schema::hasColumn('teletests', 'current_eta_minutes')) {
                $table->unsignedInteger('current_eta_minutes')->nullable();
            }
            if (!Schema::hasColumn('teletests', 'eta_last_updated_at')) {
                $table->timestamp('eta_last_updated_at')->nullable();
            }
        });

        if (!Schema::hasTable('teletest_status_history')) {
            Schema::create('teletest_status_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teletest_id')
                    ->constrained('teletests')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->string('action_key')->nullable();
                $table->string('actor_role', 50)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('reason_code', 80)->nullable();
                $table->text('reason_note')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['teletest_id', 'created_at'], 'teletest_status_history_test_created_idx');
                $table->index(['to_status', 'created_at'], 'teletest_status_history_to_created_idx');
            });
        }

        if (!Schema::hasTable('teletest_sample_events')) {
            Schema::create('teletest_sample_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teletest_id')
                    ->constrained('teletests')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
                $table->string('event_type', 40);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamp('event_at')->nullable();
                $table->timestamps();

                $table->index(['teletest_id', 'event_type'], 'teletest_sample_events_test_type_idx');
                $table->index(['event_type', 'event_at'], 'teletest_sample_events_type_event_idx');
            });
        }

        if (!Schema::hasTable('teletest_recollections')) {
            Schema::create('teletest_recollections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teletest_id')
                    ->constrained('teletests')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
                $table->string('reason_code', 80)->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('status', 60)->default('requested');
                $table->timestamps();

                $table->index(['teletest_id', 'status'], 'teletest_recollections_test_status_idx');
            });
        }

        if (!Schema::hasTable('teletest_results')) {
            Schema::create('teletest_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teletest_id')
                    ->constrained('teletests')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
                $table->text('report_url')->nullable();
                $table->unsignedBigInteger('validated_by')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->text('result_summary')->nullable();
                $table->boolean('abnormal_flag')->default(false);
                $table->timestamps();

                $table->unique('teletest_id', 'teletest_results_teletest_unique');
                $table->index(['abnormal_flag', 'validated_at'], 'teletest_results_abnormal_validated_idx');
            });
        }

        if (!Schema::hasTable('teletest_result_deliveries')) {
            Schema::create('teletest_result_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teletest_id')
                    ->constrained('teletests')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
                $table->string('channel', 30);
                $table->string('delivery_status', 30)->default('pending');
                $table->timestamp('delivered_at')->nullable();
                $table->string('receipt_ref', 120)->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamps();

                $table->index(['teletest_id', 'channel'], 'teletest_result_deliveries_test_channel_idx');
                $table->index(['delivery_status', 'delivered_at'], 'teletest_result_deliveries_status_delivered_idx');
            });
        }

        Schema::table('escalations', function (Blueprint $table) {
            if (!Schema::hasColumn('escalations', 'encounter_type')) {
                $table->string('encounter_type', 40)->nullable();
            }
            if (!Schema::hasColumn('escalations', 'encounter_id')) {
                $table->unsignedBigInteger('encounter_id')->nullable();
            }
            if (!Schema::hasColumn('escalations', 'teletest_id')) {
                $table->unsignedBigInteger('teletest_id')->nullable();
            }
            $table->index(['encounter_type', 'encounter_id'], 'escalations_encounter_idx');
            $table->index(['teletest_id', 'status'], 'escalations_teletest_status_idx');
        });

        Schema::table('complaints', function (Blueprint $table) {
            if (!Schema::hasColumn('complaints', 'encounter_type')) {
                $table->string('encounter_type', 40)->nullable();
            }
            if (!Schema::hasColumn('complaints', 'encounter_id')) {
                $table->unsignedBigInteger('encounter_id')->nullable();
            }
            if (!Schema::hasColumn('complaints', 'teletest_id')) {
                $table->unsignedBigInteger('teletest_id')->nullable();
            }
            $table->index(['encounter_type', 'encounter_id'], 'complaints_encounter_idx');
            $table->index(['teletest_id', 'status'], 'complaints_teletest_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (Schema::hasColumn('complaints', 'teletest_id')) {
                $table->dropColumn('teletest_id');
            }
            if (Schema::hasColumn('complaints', 'encounter_type')) {
                $table->dropColumn('encounter_type');
            }
            if (Schema::hasColumn('complaints', 'encounter_id')) {
                $table->dropColumn('encounter_id');
            }
            try {
                $table->dropIndex('complaints_encounter_idx');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropIndex('complaints_teletest_status_idx');
            } catch (\Throwable $e) {
            }
        });

        Schema::table('escalations', function (Blueprint $table) {
            if (Schema::hasColumn('escalations', 'teletest_id')) {
                $table->dropColumn('teletest_id');
            }
            if (Schema::hasColumn('escalations', 'encounter_type')) {
                $table->dropColumn('encounter_type');
            }
            if (Schema::hasColumn('escalations', 'encounter_id')) {
                $table->dropColumn('encounter_id');
            }
            try {
                $table->dropIndex('escalations_encounter_idx');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropIndex('escalations_teletest_status_idx');
            } catch (\Throwable $e) {
            }
        });

        Schema::dropIfExists('teletest_result_deliveries');
        Schema::dropIfExists('teletest_results');
        Schema::dropIfExists('teletest_recollections');
        Schema::dropIfExists('teletest_sample_events');
        Schema::dropIfExists('teletest_status_history');

        Schema::table('teletests', function (Blueprint $table) {
            foreach ([
                'status_reason_note',
                'departed_at',
                'arrived_at',
                'reassigned_at',
                'reassigned_from',
                'reassigned_to',
                'current_eta_minutes',
                'eta_last_updated_at',
            ] as $column) {
                if (Schema::hasColumn('teletests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
