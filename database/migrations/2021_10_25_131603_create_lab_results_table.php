<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLabResultsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('patient_id')
            ->constrained('patients')
            ->onDelete('cascade')
            ->onUpdate('cascade');

            $table->foreignId('carer_id')
            ->nullable()
            ->constrained('carers')
            ->nullOnDelete()
            ->onUpdate('cascade');
            
            $table->foreignId('teletest_id')
            ->nullable()
            ->constrained('teletests')
            ->nullOnDelete()
            ->onUpdate('cascade');
            
            $table->string('name');
            $table->string('lab_name');
            $table->string('result_picture');
            $table->text('extra_notes')->nullable();


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
        Schema::dropIfExists('lab_results');
    }
}
