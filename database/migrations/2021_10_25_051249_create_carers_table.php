<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('carers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
            ->constrained('users')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('hospital_id')
            ->constrained('hospitals')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->text('bio')->nullable();
            $table->string('position')->nullable();
            $table->double('rating')->unsigned()->default(0.0);


            $table->boolean('onHome_leave')->default(0);
            $table->boolean('onVirtual_leave')->default(0);
            $table->boolean('admin_approved')->default(0);
            $table->boolean('super_admin_approved')->default(0);
            $table->text('qualifications')->nullable();
            $table->text('virtual_day_time')->nullable();
            $table->text('home_day_time')->nullable();
        
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
        Schema::dropIfExists('carers');
    }
}
