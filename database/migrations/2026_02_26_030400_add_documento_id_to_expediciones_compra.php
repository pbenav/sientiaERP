<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expediciones_compra', function (Blueprint $table) {
            $table->foreignId('documento_id')
                  ->nullable()
                  ->after('tercero_id')
                  ->constrained('documentos')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expediciones_compra', function (Blueprint $table) {
            $table->dropForeign(['documento_id']);
            $table->dropColumn('documento_id');
        });
    }
};
