<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla base para todos los documentos de negocio
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // 'presupuesto', 'pedido', 'albaran', 'factura', 'recibo'
            $table->string('numero', 50)->unique(); // PRE-2026-0001, PED-2026-0001, etc.
            $table->string('serie', 10)->default('A'); // Serie del documento
            $table->date('fecha');
            $table->foreignId('tercero_id')->constrained('terceros');
            $table->foreignId('user_id')->constrained(); // Usuario que crea el documento
            
            // Documento origen (para conversiones)
            $table->foreignId('documento_origen_id')->nullable()->constrained('documentos');
            
            // Estado del documento
            $table->enum('estado', ['borrador', 'confirmado', 'parcial', 'completado', 'anulado'])->default('borrador');
            
            // Totales
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('base_imponible', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('irpf', 12, 2)->default(0); // Retención IRPF
            $table->decimal('recargo_equivalencia', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            
            // Condiciones de pago
            $table->enum('forma_pago', ['contado', 'transferencia', 'tarjeta', 'pagare', 'recibo'])->nullable();
            $table->integer('dias_pago')->nullable();
            
            // Observaciones
            $table->text('observaciones')->nullable(); // Visibles en el documento
            $table->text('observaciones_internas')->nullable(); // Solo internas
            
            // Fechas especiales según tipo de documento
            $table->date('fecha_validez')->nullable(); // Para presupuestos
            $table->date('fecha_entrega')->nullable(); // Para pedidos/albaranes
            $table->date('fecha_vencimiento')->nullable(); // Para facturas/recibos
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tipo', 'estado']);
            $table->index(['tercero_id', 'tipo']);
            $table->index('fecha');
        });

        // Líneas de documentos
        Schema::create('documento_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products'); // Puede ser null para líneas de texto libre
            
            $table->integer('orden')->default(0); // Orden de la línea en el documento
            $table->string('codigo', 50)->nullable(); // Código del producto (snapshot)
            $table->text('descripcion');
            
            $table->decimal('cantidad', 10, 3)->default(1);
            $table->string('unidad', 10)->default('Ud'); // Unidad de medida
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('descuento', 5, 2)->default(0); // % descuento
            $table->decimal('subtotal', 12, 2);
            $table->decimal('iva', 5, 2)->default(21); // % IVA
            $table->decimal('importe_iva', 12, 2);
            $table->decimal('irpf', 5, 2)->default(0); // % IRPF
            $table->decimal('importe_irpf', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            
            $table->timestamps();
            
            $table->index('documento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_lineas');
        Schema::dropIfExists('documentos');
    }
};
