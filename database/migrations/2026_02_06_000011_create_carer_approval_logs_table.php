<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('carer_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carer_id')
                ->constrained('carers')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreignId('hospital_id')
                ->constrained('hospitals')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->string('status');
            $table->string('reason')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->onUpdate('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('carer_approval_logs');
    }
};
