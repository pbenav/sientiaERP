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
            $table->foreignId('forma_pago_id')->nullable()->after('forma_pago')->constrained('formas_pago')->nullOnDelete();
            $table->string('bic', 11)->nullable()->after('iban');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->dropForeign(['forma_pago_id']);
            $table->dropColumn(['forma_pago_id', 'bic']);
        });
    }
};
