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
        Schema::table('documentos', function (Blueprint $table) {
            if (!Schema::hasColumn('documentos', 'verifactu_fecha_hora_huso')) {
                $table->string('verifactu_fecha_hora_huso', 40)->nullable()->after('verifactu_huella');
            }
            if (!Schema::hasColumn('documentos', 'verifactu_tipo_huella')) {
                $table->string('verifactu_tipo_huella', 10)->default('01')->after('verifactu_fecha_hora_huso');
            }
        });

        Schema::table('tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('tickets', 'verifactu_fecha_hora_huso')) {
                $table->string('verifactu_fecha_hora_huso', 40)->nullable()->after('verifactu_huella');
            }
            if (!Schema::hasColumn('tickets', 'verifactu_tipo_huella')) {
                $table->string('verifactu_tipo_huella', 10)->default('01')->after('verifactu_fecha_hora_huso');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn(['verifactu_fecha_hora_huso', 'verifactu_tipo_huella']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['verifactu_fecha_hora_huso', 'verifactu_tipo_huella']);
        });
    }
};
