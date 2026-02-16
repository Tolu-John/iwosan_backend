<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();


            $table->foreignId('patient_id')
            ->constrained('patients')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('carer_id')
            ->constrained('carers')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('transfer_id')
            ->nullable()
            ->constrained('transfers')
            ->onUpdate('cascade')
            ->nullOnDelete();
          
            $table->string('type')->nullable();
            $table->integer('type_id')->default('0');
            $table->string('price')->nullable();
            $table->string('status')->nullable();
            $table->string('code')->nullable();
            $table->string('method')->nullable();
            $table->boolean('reuse')->nullable();

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
        Schema::dropIfExists('payments');
    }
}
