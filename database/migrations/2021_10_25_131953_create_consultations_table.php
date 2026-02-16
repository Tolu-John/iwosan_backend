<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsultationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('patient_id')
            ->constrained('patients')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('carer_id')
            ->constrained('carers')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('hospital_id')
            ->constrained('hospitals')
            ->onUpdate('cascade')
            ->onDelete('cascade');


            $table->foreignId('payment_id')
            ->nullable()
            ->constrained('payments')
            ->onUpdate('cascade')
            ->nullOnDelete();

            $table->foreignId('review_id')
            ->nullable()
            ->constrained('reviews')
            ->onUpdate('cascade')
            ->nullOnDelete();

            $table->string('status');
            $table->string('treatment_type');
            $table->text('diagnosis');
            $table->text('consult_notes');
            $table->string('date_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('consultations');
    }
}
