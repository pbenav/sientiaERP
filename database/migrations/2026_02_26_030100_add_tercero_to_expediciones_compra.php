<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Añadir tercero_id (FK al proveedor) si no existe ya
        if (! Schema::hasColumn('expediciones_compra', 'tercero_id')) {
            Schema::table('expediciones_compra', function (Blueprint $table) {
                $table->foreignId('tercero_id')
                      ->nullable()
                      ->after('expedicion_id')
                      ->constrained('terceros')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('expediciones_compra', function (Blueprint $table) {
            $table->dropForeign(['tercero_id']);
            $table->dropColumn('tercero_id');
        });
    }
};
