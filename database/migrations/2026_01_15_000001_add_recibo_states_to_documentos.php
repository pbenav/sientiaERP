<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modificar el enum de estado para incluir estados de recibos
        DB::statement("ALTER TABLE documentos MODIFY COLUMN estado ENUM('borrador', 'confirmado', 'parcial', 'completado', 'anulado', 'pendiente', 'cobrado', 'pagado') DEFAULT 'borrador'");
    }

    public function down(): void
    {
        // Volver al enum original
        DB::statement("ALTER TABLE documentos MODIFY COLUMN estado ENUM('borrador', 'confirmado', 'parcial', 'completado', 'anulado') DEFAULT 'borrador'");
    }
};
