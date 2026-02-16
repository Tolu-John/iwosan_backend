<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hospital_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')
                ->constrained('hospitals')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->integer('previous_home_visit_price')->nullable();
            $table->integer('previous_virtual_visit_price')->nullable();
            $table->integer('previous_virtual_ward_price')->nullable();
            $table->integer('home_visit_price');
            $table->integer('virtual_visit_price');
            $table->integer('virtual_ward_price');
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->onUpdate('cascade');
            $table->string('reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('hospital_price_histories');
    }
};
