<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWardNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ward_notes', function (Blueprint $table) {
            
            $table->id();
            
            $table->foreignId('ward_id')
            ->constrained('wards')
            ->onUpdate('cascade')
            ->onDelete('cascade');

        

            $table->text('text');
    
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
        Schema::dropIfExists('ward_notes');
    }
}
