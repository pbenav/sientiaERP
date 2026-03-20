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
        Schema::table('impuestos', function (Blueprint $table) {
            if (Schema::hasColumn('impuestos', 'recargo')) {
                $table->decimal('recargo', 5, 2)->default(0)->nullable()->change();
            }
            if (Schema::hasColumn('impuestos', 'es_predeterminado')) {
                $table->boolean('es_predeterminado')->default(false)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('impuestos', function (Blueprint $table) {
            $table->decimal('recargo', 5, 2)->default(0)->nullable(false)->change();
            $table->boolean('es_predeterminado')->default(false)->nullable(false)->change();
        });
    }
};
