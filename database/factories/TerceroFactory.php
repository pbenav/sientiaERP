<?php

namespace Database\Factories;

use App\Models\Tercero;
use App\Models\TipoTercero;
use App\Models\FormaPago;
use Illuminate\Database\Eloquent\Factories\Factory;

class TerceroFactory extends Factory
{
    protected $model = Tercero::class;

    public function definition(): array
    {
        $nombreComercial = fake()->company();
        
        return [
            'codigo' => null, // Se genera en el boot del modelo
            'nombre_comercial' => $nombreComercial,
            'razon_social' => $nombreComercial . ' ' . fake()->randomElement(['S.L.', 'S.A.', 'S.L.U.']),
            'nif_cif' => fake()->unique()->regexify('[ABCDEFGHJKLMNPQRSUVW][0-9]{7}[A-Z0-9]'),
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->phoneNumber(),
            'movil' => fake()->phoneNumber(),
            'web' => 'https://' . fake()->domainName(),
            'persona_contacto' => fake()->name(),
            'direccion_fiscal' => fake()->streetAddress(),
            'codigo_postal_fiscal' => str_pad(fake()->numberBetween(1000, 52000), 5, '0', STR_PAD_LEFT),
            'poblacion_fiscal' => fake()->city(),
            'provincia_fiscal' => fake()->state(),
            'pais_fiscal' => 'España',
            'forma_pago_id' => FormaPago::inRandomOrder()->first()?->id,
            'dias_pago' => fake()->randomElement([0, 15, 30, 60]),
            'activo' => true,
            'observaciones' => fake()->optional()->sentence(),
        ];
    }

    public function cliente(): self
    {
        return $this->afterCreating(function (Tercero $tercero) {
            $tipo = TipoTercero::where('codigo', 'CLI')->first();
            if ($tipo) $tercero->tipos()->attach($tipo->id);
        });
    }

    public function proveedor(): self
    {
        return $this->afterCreating(function (Tercero $tercero) {
            $tipo = TipoTercero::where('codigo', 'PRO')->first();
            if ($tipo) $tercero->tipos()->attach($tipo->id);
        });
    }
}
