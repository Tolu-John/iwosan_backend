<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wards', function (Blueprint $table) {
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

            $table->foreignId('appt_id')
            ->constrained('appointments')
            ->onUpdate('cascade')
            ->onDelete('cascade');
            
            $table->text('diagnosis');

            $table->string('admission_date');
            $table->text('ward_vitals');
            $table->boolean('discharged')->default(0);
            $table->string('discharge_date')->nullable();
            $table->string('priority');


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
        Schema::dropIfExists('wards');
    }
}
