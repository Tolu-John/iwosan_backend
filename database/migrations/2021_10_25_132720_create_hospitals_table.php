<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHospitalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hospitals', function (Blueprint $table) {
           
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->double('rating')->unsigned()->default(0.0);
            $table->text('about_us')->nullable();
            $table->string('website')->nullable();
            $table->string('hospital_img')->nullable();
            $table->string('email')->unique();
            $table->string('firedb_id');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_code')->nullable();
            $table->integer('home_visit_price')->nullable();
            $table->integer('virtual_visit_price')->nullable();
            $table->integer('virtual_ward_price')->nullable();
            $table->string('lat')->nullable();
            $table->string('lon')->nullable();
            $table->text('address')->nullable();
            $table->string('password');
            $table->boolean('super_admin_approved')->default(0);
            $table->foreignId('admin_id')->nullable()
            ->constrained('users')
            ->onDelete('cascade')
            ->onUpdate('cascade');

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
        Schema::dropIfExists('hospitals');
    }
}
