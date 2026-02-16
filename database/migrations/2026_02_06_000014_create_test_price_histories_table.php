<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('test_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')
                ->constrained('tests')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreignId('hospital_id')
                ->constrained('hospitals')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->unsignedInteger('previous_price')->nullable();
            $table->unsignedInteger('price');
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->onUpdate('cascade');
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('test_price_histories');
    }
};
