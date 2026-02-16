<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lab_result_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_result_id')
                ->constrained('lab_results')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->string('action');
            $table->json('changes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->onUpdate('cascade');
            $table->string('created_role')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lab_result_audit_logs');
    }
};
