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
        Schema::table('documentos', function (Blueprint $table) {
            if (Schema::hasColumn('documentos', 'forma_pago')) {
                $table->dropColumn('forma_pago');
            }
            
            if (Schema::hasColumn('documentos', 'dias_pago')) {
                $table->dropColumn('dias_pago');
            }
        });
        
        Schema::table('documentos', function (Blueprint $table) {
            if (!Schema::hasColumn('documentos', 'forma_pago_id')) {
                $table->foreignId('forma_pago_id')->nullable()->after('total')->constrained('formas_pago');
            }
            
            // Intentar añadir índice, ignorar si ya existe
            try {
                $table->index('documento_origen_id');
            } catch (\Exception $e) {
                // Índice ya existe, continuar
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            if (Schema::hasColumn('documentos', 'forma_pago_id')) {
                $table->dropForeign(['forma_pago_id']);
                $table->dropColumn('forma_pago_id');
            }
            
            if (!Schema::hasColumn('documentos', 'forma_pago')) {
                $table->enum('forma_pago', ['contado', 'transferencia', 'tarjeta', 'pagare', 'recibo'])->nullable();
            }
            if (!Schema::hasColumn('documentos', 'dias_pago')) {
                $table->integer('dias_pago')->nullable();
            }
            
            try {
                $table->dropIndex(['documento_origen_id']);
            } catch (\Exception $e) {
                // Índice no existe, continuar
            }
        });
    }
};
