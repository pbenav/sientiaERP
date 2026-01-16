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
        Schema::table('documento_lineas', function (Blueprint $table) {
            $table->decimal('recargo_equivalencia', 5, 2)->default(0)->after('importe_iva'); // % RE
            $table->decimal('importe_recargo_equivalencia', 12, 2)->default(0)->after('recargo_equivalencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documento_lineas', function (Blueprint $table) {
            $table->dropColumn(['recargo_equivalencia', 'importe_recargo_equivalencia']);
        });
    }
};
