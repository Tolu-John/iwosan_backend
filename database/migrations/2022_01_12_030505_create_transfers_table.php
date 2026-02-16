<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransfersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();

          

            $table->foreignId('payment_id')
            ->constrained('payments')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('type_id')
            ->constrained('appointments')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('hospital_id')
            ->constrained('hospitals')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('carer_id')
            ->constrained('carers')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->string('recipient');

            $table->text('amount');

            $table->text('reason');

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
        Schema::dropIfExists('transfers');
    }
}
