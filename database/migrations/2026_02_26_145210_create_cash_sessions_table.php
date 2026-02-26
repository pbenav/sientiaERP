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
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->string('estado')->default('open'); // open, closed
            $table->decimal('fondo_apertura', 12, 2)->default(0);
            $table->decimal('efectivo_final_real', 12, 2)->nullable();
            $table->decimal('total_tickets_efectivo', 12, 2)->default(0);
            $table->decimal('total_tickets_tarjeta', 12, 2)->default(0);
            $table->decimal('desfase', 12, 2)->nullable();
            $table->text('notas')->nullable();
            $table->json('desglose_efectivo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
