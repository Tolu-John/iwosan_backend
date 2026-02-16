<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHConsultationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('h_consultations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_id')
            ->constrained('consultations')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('ward_id')->default(0)
            ->constrained('wards')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->string('address');
            $table->boolean('admitted')->default(0);


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
        Schema::dropIfExists('h_consultations');
    }
}
