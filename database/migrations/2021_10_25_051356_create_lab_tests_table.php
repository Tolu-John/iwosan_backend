<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLabTestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('consultation_id')
            ->constrained('consultations')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('ward_id')
            ->nullable()
            ->constrained('wards')
            ->onUpdate('cascade')
            ->nullOnDelete();

            $table->string('test_name');
            $table->string('lab_recomm');
            $table->text('extra_notes')->nullable();
            $table->boolean('done')->default(0);


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
        Schema::dropIfExists('lab_tests');
    }
}
