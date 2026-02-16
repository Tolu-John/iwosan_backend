<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTeletestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('teletests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
            ->constrained('patients')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('carer_id')
            ->constrained('carers')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('hospital_id')
            ->constrained('hospitals')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->foreignId('payment_id')
            ->nullable()
            ->constrained('payments')
            ->onUpdate('cascade')
            ->nullOnDelete();

            
            $table->foreignId('review_id')
            ->nullable()
            ->constrained('reviews')
            ->onUpdate('cascade')
            ->nullOnDelete();

            $table->string('test_name');
            $table->string('status');
            $table->string('date_time');
            $table->string('address');
            $table->boolean('admin_approved');
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
        Schema::dropIfExists('teletests');
    }
}
