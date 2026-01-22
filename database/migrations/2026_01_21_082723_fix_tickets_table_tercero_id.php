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
        Schema::table('tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('tickets', 'tercero_id')) {
                $table->foreignId('tercero_id')->nullable()->after('user_id')->constrained('terceros');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'tercero_id')) {
                $table->dropForeign(['tercero_id']);
                $table->dropColumn('tercero_id');
            }
        });
    }
};
