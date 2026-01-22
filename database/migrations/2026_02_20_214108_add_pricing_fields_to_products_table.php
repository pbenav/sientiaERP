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
            $table->decimal('purchase_price', 12, 2)->nullable()->after('price');
            $table->decimal('profit', 12, 2)->nullable()->after('purchase_price');
            $table->decimal('profit_margin', 12, 2)->nullable()->after('profit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'profit', 'profit_margin']);
        });
    }
};
