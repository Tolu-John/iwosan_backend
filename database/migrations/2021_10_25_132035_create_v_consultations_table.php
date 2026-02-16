<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVConsultationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('v_consultations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_id')
            ->constrained('consultations')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->string('consult_type');
            $table->string('duration');

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
        Schema::dropIfExists('v_consultations');
    }
}
