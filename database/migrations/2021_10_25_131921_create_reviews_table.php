<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();


            $table->foreignId('patient_id')
            ->constrained('patients')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('carer_id')
            ->constrained('carers')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('consultation_id')
            ->constrained('consultations')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->text('text');
            $table->double('rating')->unsigned();
            $table->boolean('recomm');

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
        Schema::dropIfExists('reviews');
    }
}
