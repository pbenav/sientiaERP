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
        // Insertar configuraciones de formato numérico
        DB::table('settings')->insert([
            [
                'key' => 'decimal_separator',
                'value' => 'comma', // Por defecto coma para España
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'thousands_separator',
                'value' => 'dot', // Por defecto punto para España
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['decimal_separator', 'thousands_separator'])->delete();
    }
};
