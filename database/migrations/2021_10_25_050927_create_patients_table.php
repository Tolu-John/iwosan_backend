<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePatientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
            ->constrained('users')
            ->onUpdate('cascade')
            ->onDelete('cascade');
            
        
            $table->string('bloodtype')->nullable();
            $table->string('genotype')->nullable();
            $table->string('temperature')->default('0');

            $table->string('sugar_level')->default('0');
            $table->string('bloodpressure')->default('0');
            $table->string('bp_dia')->default('0');
            $table->string('bp_sys')->default('0');
            $table->double('weight')->unsigned()->default(0.0);
            $table->double('height')->unsigned()->default(0.0);

            $table->string('kin_name')->nullable();
            $table->string('kin_phone')->nullable();
            $table->text('kin_address')->nullable();

            $table->string('other_kin_name')->nullable();
            $table->string('other_kin_phone')->nullable();
            $table->text('other_kin_address')->nullable();

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
        Schema::dropIfExists('patients');
    }
}
