<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_admission_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('version')->default(1);
            $table->string('currency', 8)->default('NGN');
            $table->unsignedBigInteger('enrollment_fee_minor')->default(0);
            $table->unsignedBigInteger('recurring_fee_minor')->default(0);
            $table->string('billing_cycle', 40)->nullable();
            $table->unsignedBigInteger('addons_total_minor')->default(0);
            $table->unsignedBigInteger('discount_total_minor')->default(0);
            $table->unsignedBigInteger('tax_total_minor')->default(0);
            $table->unsignedBigInteger('grand_total_minor')->default(0);
            $table->string('quote_status', 40)->default('draft');
            $table->timestamp('valid_until')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['appointment_id', 'version'], 'home_admission_quotes_appointment_version_uniq');
            $table->index(['quote_status', 'valid_until'], 'home_admission_quotes_status_valid_until_idx');
        });

        Schema::create('home_admission_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('home_admission_quotes')->onUpdate('cascade')->onDelete('cascade');
            $table->string('item_type', 60);
            $table->string('item_code', 80)->nullable();
            $table->string('label', 255);
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedBigInteger('unit_price_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor')->default(0);
            $table->timestamps();

            $table->index(['quote_id', 'item_type'], 'home_admission_quote_items_quote_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_admission_quote_items');
        Schema::dropIfExists('home_admission_quotes');
    }
};
