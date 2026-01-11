<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terceros', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique(); // Código interno auto-generado
            $table->string('nombre_comercial');
            $table->string('razon_social')->nullable();
            $table->string('nif_cif', 20)->unique();
            
            // Contacto
            $table->string('email')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('movil', 20)->nullable();
            $table->string('web')->nullable();
            $table->string('persona_contacto')->nullable();
            
            // Dirección fiscal
            $table->text('direccion_fiscal')->nullable();
            $table->string('codigo_postal_fiscal', 10)->nullable();
            $table->string('poblacion_fiscal', 100)->nullable();
            $table->string('provincia_fiscal', 100)->nullable();
            $table->string('pais_fiscal', 100)->default('España');
            
            // Dirección de envío (si diferente)
            $table->boolean('direccion_envio_diferente')->default(false);
            $table->text('direccion_envio')->nullable();
            $table->string('codigo_postal_envio', 10)->nullable();
            $table->string('poblacion_envio', 100)->nullable();
            $table->string('provincia_envio', 100)->nullable();
            $table->string('pais_envio', 100)->nullable();
            
            // Datos bancarios
            $table->string('iban', 34)->nullable();
            $table->string('swift', 11)->nullable();
            $table->string('banco')->nullable();
            
            // Condiciones comerciales
            $table->enum('forma_pago', ['contado', 'transferencia', 'tarjeta', 'pagare', 'recibo'])->default('contado');
            $table->integer('dias_pago')->default(0); // Días de pago (0=contado, 30, 60, 90)
            $table->decimal('descuento_comercial', 5, 2)->default(0); // % descuento por defecto
            $table->decimal('limite_credito', 10, 2)->nullable();
            
            // Datos fiscales
            $table->boolean('recargo_equivalencia')->default(false);
            $table->decimal('irpf', 5, 2)->default(0); // % IRPF a retener
            
            // Estado
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('nombre_comercial');
            $table->index('activo');
        });

        // Tabla pivot para tipos de tercero (un tercero puede ser cliente Y proveedor)
        Schema::create('tercero_tipo', function (Blueprint $table) {
            $table->foreignId('tercero_id')->constrained('terceros')->cascadeOnDelete();
            $table->foreignId('tipo_tercero_id')->constrained('tipo_tercero')->cascadeOnDelete();
            $table->primary(['tercero_id', 'tipo_tercero_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tercero_tipo');
        Schema::dropIfExists('terceros');
    }
};
