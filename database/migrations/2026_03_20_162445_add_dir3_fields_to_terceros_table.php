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
        Schema::table('terceros', function (Blueprint $table) {
            if (!Schema::hasColumn('terceros', 'dir3_oficina_contable')) {
                $table->string('dir3_oficina_contable', 15)->nullable()->after('pais_fiscal');
            }
            if (!Schema::hasColumn('terceros', 'dir3_organo_gestor')) {
                $table->string('dir3_organo_gestor', 15)->nullable()->after('dir3_oficina_contable');
            }
            if (!Schema::hasColumn('terceros', 'dir3_unidad_tramitadora')) {
                $table->string('dir3_unidad_tramitadora', 15)->nullable()->after('dir3_organo_gestor');
            }
            if (!Schema::hasColumn('terceros', 'es_persona_fisica')) {
                $table->boolean('es_persona_fisica')->default(false)->after('nif_cif');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            if (Schema::hasColumn('terceros', 'dir3_oficina_contable')) {
                $table->dropColumn(['dir3_oficina_contable', 'dir3_organo_gestor', 'dir3_unidad_tramitadora', 'es_persona_fisica']);
            }
        });
    }
};
