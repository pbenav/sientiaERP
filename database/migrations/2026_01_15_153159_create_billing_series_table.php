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
        Schema::create('billing_series', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre');
            $table->foreignId('iva_defecto_id')->nullable()->constrained('impuestos');
            $table->foreignId('irpf_defecto_id')->nullable()->constrained('impuestos');
            $table->boolean('sujeta_irpf')->default(false);
            $table->boolean('devenga_iva')->default(true);
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
        Schema::dropIfExists('billing_series');
    }
};
