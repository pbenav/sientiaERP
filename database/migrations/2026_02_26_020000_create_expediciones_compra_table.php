<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expediciones_compra', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->default(now());
            $table->string('proveedor');
            $table->string('direccion')->nullable();
            $table->decimal('importe', 10, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->boolean('pagado')->default(false);
            $table->boolean('recogido')->default(false);
            $table->string('documento_path')->nullable(); // albarán subido
            $table->boolean('archivado')->default(false); // para el "resetear"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expediciones_compra');
    }
};
