<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_tercero', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique(); // 'CLI', 'PRO', 'EMP', etc.
            $table->string('nombre', 100); // 'Cliente', 'Proveedor', etc.
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Insertar tipos por defecto
        DB::table('tipo_tercero')->insert([
            ['codigo' => 'CLI', 'nombre' => 'Cliente', 'descripcion' => 'Cliente final o empresa', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'PRO', 'nombre' => 'Proveedor', 'descripcion' => 'Proveedor de productos o servicios', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'EMP', 'nombre' => 'Empleado', 'descripcion' => 'Empleado de la empresa', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'TRA', 'nombre' => 'Transportista', 'descripcion' => 'Empresa de transporte', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_tercero');
    }
};
