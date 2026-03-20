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
        if (!Schema::hasColumn('impuestos', 'recargo')) {
            Schema::table('impuestos', function (Blueprint $table) {
                $table->decimal('recargo', 5, 2)->default(0)->after('valor');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('impuestos', function (Blueprint $table) {
            $table->dropColumn('recargo');
        });
    }
};
