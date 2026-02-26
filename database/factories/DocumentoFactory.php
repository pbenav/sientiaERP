<?php

namespace Database\Factories;

use App\Models\Documento;
use App\Models\Tercero;
use App\Models\User;
use App\Models\Product;
use App\Models\FormaPago;
use App\Models\DocumentoLinea;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentoFactory extends Factory
{
    protected $model = Documento::class;

    public function definition(): array
    {
        $tipo = fake()->randomElement([
            'presupuesto', 'pedido', 'albaran', 'factura',
            'pedido_compra', 'albaran_compra', 'factura_compra'
        ]);

        return [
            'tipo' => $tipo,
            'tercero_id' => Tercero::inRandomOrder()->first()?->id ?? Tercero::factory(),
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'fecha' => fake()->dateTimeBetween('-1 year', 'now'),
            'serie' => 'A',
            'numero' => null, // Se genera al confirmar
            'estado' => 'borrador',
            'forma_pago_id' => FormaPago::inRandomOrder()->first()?->id,
            'observaciones' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Añadir líneas aleatorias al documento
     */
    public function withLines(int $count = null): self
    {
        return $this->afterCreating(function (Documento $documento) use ($count) {
            $numLines = $count ?? rand(1, 5);
            $products = Product::inRandomOrder()->limit($numLines)->get();

            if ($products->isEmpty()) {
                // Si no hay productos, crear algunos rápidos para que no falle
                $products = Product::factory()->count($numLines)->create();
            }

            foreach ($products as $index => $product) {
                $linea = new DocumentoLinea();
                $linea->documento_id = $documento->id;
                $linea->cargarDesdeProducto($product, rand(1, 10));
                $linea->orden = $index;
                $linea->save();
            }

            // Recalcular totales tras añadir líneas
            $documento->recalcularTotales();
            $documento->save();
        });
    }

    /**
     * Estado para que el documento nazca "confirmado" (con número)
     */
    public function confirmado(): self
    {
        return $this->afterCreating(function (Documento $documento) {
            $documento->confirmar();
        });
    }
}
