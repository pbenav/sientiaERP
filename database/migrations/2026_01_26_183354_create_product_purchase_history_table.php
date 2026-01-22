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
        Schema::create('product_purchase_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('documento_id')->nullable()->constrained('documentos')->onDelete('set null');
            $table->foreignId('documento_linea_id')->nullable()->constrained('documento_lineas')->onDelete('set null');
            $table->foreignId('supplier_id')->nullable()->constrained('terceros')->onDelete('set null');
            
            // Purchase details
            $table->decimal('purchase_price', 10, 2); // Precio de compra unitario
            $table->decimal('quantity', 10, 2); // Cantidad comprada
            $table->decimal('total_amount', 10, 2); // Total de la línea
            $table->string('currency', 3)->default('EUR');
            
            // Metadata
            $table->date('purchase_date'); // Fecha de la compra
            $table->string('document_number')->nullable(); // Número de albarán/factura
            $table->string('supplier_name')->nullable(); // Nombre del proveedor (desnormalizado para histórico)
            $table->text('notes')->nullable(); // Notas adicionales
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index('product_id');
            $table->index('supplier_id');
            $table->index('purchase_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_purchase_history');
    }
};
