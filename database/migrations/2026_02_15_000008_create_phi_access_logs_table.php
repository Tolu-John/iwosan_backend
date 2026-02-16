<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phi_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role')->nullable();
            $table->string('route')->nullable();
            $table->string('method')->nullable();
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->timestamp('accessed_at')->useCurrent();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'route'], 'phi_access_logs_user_route_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phi_access_logs');
    }
};
