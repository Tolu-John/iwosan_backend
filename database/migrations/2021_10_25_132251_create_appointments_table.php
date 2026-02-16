<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppointmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('patient_id')
            ->constrained('patients')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('carer_id')
            ->constrained('carers')
            ->onUpdate('cascade')
            ->onDelete('cascade');
            
            $table->foreignId('payment_id')
            ->nullable()
            ->constrained('payments')
            ->onUpdate('cascade')
            ->nullOnDelete();

            $table->foreignId('consult_id')
            ->nullable()
            ->constrained('consultations')
            ->onUpdate('cascade')
            ->nullOnDelete();

            
            $table->foreignId('ward_id')
            ->nullable()
            ->constrained('wards')
            ->onUpdate('cascade')
            ->nullOnDelete();


            $table->string('status');
            $table->string('address')->nullable();
            $table->integer('price');
            $table->string('consult_type')->nullable();
            $table->text('extra_notes')->nullable();
            $table->string('channel')->nullable();
            $table->string('appointment_type');
            $table->string('date_time');
            $table->boolean('admin_approved')->default(0);

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
        Schema::dropIfExists('appointments');
    }
}
