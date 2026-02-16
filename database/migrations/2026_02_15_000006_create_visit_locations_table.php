<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->string('source')->default('whatsapp');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['consultation_id'], 'visit_locations_consult_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_locations');
    }
};
