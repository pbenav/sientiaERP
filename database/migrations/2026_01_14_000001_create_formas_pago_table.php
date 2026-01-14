<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('formas_pago')) {
            Schema::create('formas_pago', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 50)->unique();
                $table->string('nombre', 100);
                $table->enum('tipo', ['contado', 'transferencia', 'tarjeta', 'pagare', 'recibo_bancario', 'efectivo']);
                $table->json('tramos')->nullable();
                $table->text('descripcion')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formas_pago');
    }
};
