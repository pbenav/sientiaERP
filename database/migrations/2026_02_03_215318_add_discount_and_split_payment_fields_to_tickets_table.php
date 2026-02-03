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
        Schema::table('tickets', function (Blueprint $table) {
            $table->decimal('descuento_porcentaje', 5, 2)->default(0)->after('status');
            $table->decimal('descuento_importe', 10, 2)->default(0)->after('descuento_porcentaje');
            $table->decimal('pago_efectivo', 10, 2)->default(0)->after('payment_method');
            $table->decimal('pago_tarjeta', 10, 2)->default(0)->after('pago_efectivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['descuento_porcentaje', 'descuento_importe', 'pago_efectivo', 'pago_tarjeta']);
        });
    }
};
