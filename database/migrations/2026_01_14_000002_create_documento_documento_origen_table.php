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
        if (!Schema::hasTable('documento_documento_origen')) {
            Schema::create('documento_documento_origen', function (Blueprint $table) {
                $table->id();
                $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();
                $table->foreignId('documento_origen_id')->constrained('documentos')->cascadeOnDelete();
                $table->decimal('cantidad_procesada', 12, 2)->default(0)->comment('Para control de procesamiento parcial');
                $table->timestamps();
                
                $table->unique(['documento_id', 'documento_origen_id'], 'doc_doc_origen_unique');
                $table->index('documento_id');
                $table->index('documento_origen_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_documento_origen');
    }
};
