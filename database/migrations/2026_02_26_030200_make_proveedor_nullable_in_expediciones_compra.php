<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expediciones_compra', function (Blueprint $table) {
            // proveedor pasa a ser nullable (ahora usamos tercero_id del maestro)
            $table->string('proveedor')->nullable()->change();
            // direccion también era text, por si acaso
            $table->string('direccion')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('expediciones_compra', function (Blueprint $table) {
            $table->string('proveedor')->nullable(false)->change();
        });
    }
};
