<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->unique();
            $table->string('firedb_id');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('user_img')->nullable();
            $table->string('gender')->nullable();
            $table->string('phone')->nullable();
            $table->string('age')->nullable();  

            $table->string('lat')->nullable();
            $table->string('lon')->nullable();
            $table->text('address')->nullable();
            
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
}
