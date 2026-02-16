<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('lab_results', function (Blueprint $table) {
            $table->timestamp('uploaded_at')->nullable()->after('extra_notes');
            $table->string('source')->nullable()->after('uploaded_at');
            $table->foreignId('uploaded_by')
                ->nullable()
                ->after('source')
                ->constrained('users')
                ->nullOnDelete()
                ->onUpdate('cascade');
            $table->string('uploaded_role')->nullable()->after('uploaded_by');
        });
    }

    public function down()
    {
        Schema::table('lab_results', function (Blueprint $table) {
            $table->dropColumn(['uploaded_at', 'source', 'uploaded_by', 'uploaded_role']);
        });
    }
};
