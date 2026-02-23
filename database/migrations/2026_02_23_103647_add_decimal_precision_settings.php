<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'intermediate_precision'],
            [
                'value' => '3',
                'label' => 'Decimales en cálculos intermedios (Precios)',
                'group' => 'Formato',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'final_precision'],
            [
                'value' => '2',
                'label' => 'Decimales en documentos finales (Totales)',
                'group' => 'Formato',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['intermediate_precision', 'final_precision'])->delete();
    }
};
