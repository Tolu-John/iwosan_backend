<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('complaint_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')
                ->constrained('complaints')
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
        Schema::dropIfExists('complaint_audit_logs');
    }
};
