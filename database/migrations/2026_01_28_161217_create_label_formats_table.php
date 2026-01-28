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
        Schema::create('label_formats', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->enum('tipo', ['standard', 'custom'])->default('standard');
            
            // Dimensiones documento (A4 por defecto: 210x297mm)
            $table->decimal('document_width', 8, 2)->default(210); // mm
            $table->decimal('document_height', 8, 2)->default(297); // mm
            
            // Dimensiones etiqueta
            $table->decimal('label_width', 8, 2); // mm
            $table->decimal('label_height', 8, 2); // mm
            
            // Layout
            $table->integer('labels_per_row');
            $table->integer('labels_per_column')->nullable();
            $table->integer('labels_per_sheet')->nullable(); // auto-calculado
            
            // Márgenes
            $table->decimal('margin_top', 8, 2)->default(0);
            $table->decimal('margin_bottom', 8, 2)->default(0);
            $table->decimal('margin_left', 8, 2)->default(0);
            $table->decimal('margin_right', 8, 2)->default(0);
            
            // Espaciado entre etiquetas
            $table->decimal('horizontal_spacing', 8, 2)->default(0);
            $table->decimal('vertical_spacing', 8, 2)->default(0);
            
            // Metadatos
            $table->string('manufacturer')->nullable(); // Avery, Herma, MATTIO, etc.
            $table->string('model_number')->nullable();
            $table->boolean('activo')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('label_formats');
    }
};
