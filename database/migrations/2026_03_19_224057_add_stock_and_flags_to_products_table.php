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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('requires_stock')->default(true)->after('stock');
            $table->boolean('is_salable')->default(true)->after('requires_stock');
            $table->boolean('is_purchasable')->default(true)->after('is_salable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['requires_stock', 'is_salable', 'is_purchasable']);
        });
    }
};
