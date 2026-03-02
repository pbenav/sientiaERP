<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_drafts', function (Blueprint $table) {
            $table->id();

            // Origen
            $table->foreignId('expedicion_compra_id')
                ->nullable()
                ->constrained('expediciones_compra')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Estado
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');

            // Datos extraídos por la IA (cabecera)
            $table->string('provider_name')->nullable();
            $table->string('provider_nif')->nullable();
            $table->string('document_number')->nullable();
            $table->date('document_date')->nullable();
            $table->foreignId('matched_provider_id')
                ->nullable()
                ->constrained('terceros')
                ->nullOnDelete();

            // Totales
            $table->decimal('subtotal', 12, 4)->default(0);
            $table->decimal('total_discount', 12, 4)->default(0);
            $table->decimal('total_amount', 12, 4)->default(0);

            // Líneas en JSON (descripción, referencia, cantidad, precios, márgenes...)
            $table->json('items')->nullable();

            // Documento original adjunto
            $table->string('documento_path')->nullable();
            $table->text('raw_text')->nullable();

            // Confirmación
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('documento_id')
                ->nullable()
                ->constrained('documentos')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_drafts');
    }
};
