<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWardWeightsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ward_weights', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('ward_id')
            ->constrained('wards')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->string('value');
    
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
        Schema::dropIfExists('ward_weights');
    }
}
