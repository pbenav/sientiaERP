<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Control de numeración automática de documentos
        Schema::create('numeracion_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // 'presupuesto', 'pedido', 'albaran', 'factura', 'recibo'
            $table->string('serie', 10)->default('A');
            $table->integer('anio');
            $table->integer('ultimo_numero')->default(0);
            $table->string('formato', 50)->default('{TIPO}-{ANIO}-{NUM}'); // Formato del número
            $table->integer('longitud_numero')->default(4); // Padding de ceros (0001, 0002, etc.)
            
            $table->unique(['tipo', 'serie', 'anio']);
            $table->timestamps();
        });

        // Insertar configuración inicial para cada tipo de documento
        $tipos = ['presupuesto', 'pedido', 'albaran', 'factura', 'recibo'];
        $anioActual = date('Y');
        
        foreach ($tipos as $tipo) {
            DB::table('numeracion_documentos')->insert([
                'tipo' => $tipo,
                'serie' => 'A',
                'anio' => $anioActual,
                'ultimo_numero' => 0,
                'formato' => '{TIPO}-{ANIO}-{NUM}',
                'longitud_numero' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('numeracion_documentos');
    }
};
