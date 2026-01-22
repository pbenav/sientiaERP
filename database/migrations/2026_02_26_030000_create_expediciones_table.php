<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla padre: expediciones (un viaje / feria)
        if (! Schema::hasTable('expediciones')) {
            Schema::create('expediciones', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->date('fecha');
                $table->string('lugar')->nullable();
                $table->text('descripcion')->nullable();
                $table->timestamps();
            });
        }

        // 2. Vincular compras existentes a su expedición
        if (! Schema::hasColumn('expediciones_compra', 'expedicion_id')) {
            Schema::table('expediciones_compra', function (Blueprint $table) {
                $table->foreignId('expedicion_id')
                      ->nullable()
                      ->after('id')
                      ->constrained('expediciones')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('expediciones_compra', function (Blueprint $table) {
            $table->dropForeign(['expedicion_id']);
            $table->dropColumn('expedicion_id');
        });
        Schema::dropIfExists('expediciones');
    }
};
