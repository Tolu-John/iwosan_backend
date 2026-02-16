<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disclosure_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->string('scope')->default('export');
            $table->string('resource')->nullable();
            $table->json('filters')->nullable();
            $table->text('justification');
            $table->string('status')->default('pending');
            $table->text('review_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'status'], 'disclosure_requests_requester_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disclosure_requests');
    }
};
