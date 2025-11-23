<?php

namespace Database\Factories;

use App\Models\Inventario; 
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventario>
 */

// ... (omito código repetido, el contenido es correcto)
class InventarioFactory extends Factory
{
    protected $model = Inventario::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // FK: Asigna un producto ID existente al azar.
            'producto_id' => Producto::factory(), 
            // Genera una cantidad de existencias entre 10 y 500
            'cantidad_existencias' => $this->faker->numberBetween(10, 500),
        ];
    }
}