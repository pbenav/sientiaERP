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
            $table->string('verifactu_huella', 128)->nullable()->index()->after('total')->comment('Hash SHA-256 del registro');
            $table->string('verifactu_huella_anterior', 128)->nullable()->after('verifactu_huella')->comment('Hash del registro anterior encadenado');
            $table->string('verifactu_status', 20)->default('pending')->after('verifactu_huella_anterior')->index();
            $table->string('verifactu_aeat_id', 128)->nullable()->after('verifactu_status')->comment('ID de respuesta de la AEAT');
            $table->text('verifactu_qr_url')->nullable()->after('verifactu_aeat_id');
            $table->text('verifactu_signature')->nullable()->after('verifactu_qr_url');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('verifactu_huella', 128)->nullable()->index()->after('total')->comment('Hash SHA-256 del registro');
            $table->string('verifactu_huella_anterior', 128)->nullable()->after('verifactu_huella')->comment('Hash del registro anterior encadenado');
            $table->string('verifactu_status', 20)->default('pending')->after('verifactu_huella_anterior')->index();
            $table->string('verifactu_aeat_id', 128)->nullable()->after('verifactu_status')->comment('ID de respuesta de la AEAT');
            $table->text('verifactu_qr_url')->nullable()->after('verifactu_aeat_id');
            $table->text('verifactu_signature')->nullable()->after('verifactu_qr_url');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn(['verifactu_huella', 'verifactu_huella_anterior', 'verifactu_status', 'verifactu_aeat_id', 'verifactu_qr_url', 'verifactu_signature']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['verifactu_huella', 'verifactu_huella_anterior', 'verifactu_status', 'verifactu_aeat_id', 'verifactu_qr_url', 'verifactu_signature']);
        });
    }
};
