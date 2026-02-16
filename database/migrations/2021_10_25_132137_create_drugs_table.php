<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDrugsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('drugs', function (Blueprint $table) {
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

            $table->string('stop_date')->nullable();
            $table->string('start_date')->nullable();
            $table->string('drug_type');


            $table->string('duration');
            $table->string('quantity');
            $table->boolean('started');
            $table->boolean('finished');
            $table->string('dosage');
            $table->text('extra_notes');
            $table->string('name');
            $table->string('carer_name');

            
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
        Schema::dropIfExists('drugs');
    }
}
