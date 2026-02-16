<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_templates', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('whatsapp');
            $table->string('name');
            $table->string('language')->default('en');
            $table->json('variables')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['provider', 'name', 'language'], 'comm_templates_provider_name_lang_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_templates');
    }
};
