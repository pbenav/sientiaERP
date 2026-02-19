<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tickets')) {
            Schema::create('tickets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->comment('Operador de caja');
                $table->foreignId('tercero_id')->nullable()->constrained('terceros');
                $table->string('session_id')->unique()->index()->comment('ID de sesión para recuperación');
                $table->enum('status', ['open', 'completed', 'cancelled'])->default('open')->index();
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('tax', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->enum('payment_method', ['cash', 'card', 'mixed'])->nullable();
                $table->decimal('amount_paid', 10, 2)->nullable();
                $table->decimal('change_given', 10, 2)->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('tickets', 'tercero_id')) {
                    $table->foreignId('tercero_id')->nullable()->after('user_id')->constrained('terceros');
                }
                // Add other potentially missing columns from the base schema if needed
                if (!Schema::hasColumn('tickets', 'session_id')) {
                    $table->string('session_id')->unique()->index()->after('tercero_id');
                }
            });
        }

        if (!Schema::hasTable('ticket_items')) {
            Schema::create('ticket_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained();
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 10, 2);
                $table->decimal('tax_rate', 5, 2);
                $table->decimal('subtotal', 10, 2);
                $table->decimal('tax_amount', 10, 2);
                $table->decimal('total', 10, 2);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_items');
        Schema::dropIfExists('tickets');
    }
};
