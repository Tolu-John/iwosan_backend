<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCertlicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('certlices', function (Blueprint $table) {
           
            $table->id();
            $table->unsignedInteger("type_id");
           
            $table->string('type');
           
            $table->text("location");
           
            $table->string("file_name");

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('certlices');
    }
}
