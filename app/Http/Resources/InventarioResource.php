<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventarioResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // El 'this' hace referencia a la instancia del modelo App\Models\Inventario
        return [
            // Tu migración llama a la PK 'inventario_id', pero el modelo usa 'id' por defecto
            // Asumo que el modelo está bien, si no, usa $this->inventario_id
            'id' => $this->id, 
            'tipo' => 'inventario',
            'atributos' => [
                'cantidad_existencias' => (int) $this->cantidad_existencias, 
            ],
            'relaciones' => [
                'producto' => $this->whenLoaded('producto', function () {
                    // Solo devolvemos los atributos básicos del producto para evitar bucles (Detalle de Producto en Inventario)
                    return [
                        'id' => $this->producto->id,
                        'nombre' => $this->producto->nombre_gomita,
                    ];
                }),
            ],
        ];
    }
}