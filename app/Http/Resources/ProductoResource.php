<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Importar el recurso de inventario
use App\Http\Resources\InventarioResource; 

class ProductoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => 'producto',
            'atributos' => [
                'nombre' => $this->nombre_gomita,
                'sabor' => $this->sabor,
                'tamaño' => $this->tamano,
                'precio' => (float) $this->precio, 
            ],
            'relaciones' => [
                // 'inventario' es una relación HasMany. Asumo que solo quieres mostrar el primer/único registro.
                'inventario' => $this->whenLoaded('inventario', function () {
                    if ($this->inventario->isNotEmpty()) {
                         // Usar InventarioResource para formatear la relación
                        return new InventarioResource($this->inventario->first());
                    }
                    return null;
                }),
            ],
        ];
    }
}