<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

// Importar el recurso simple para mapear
use App\Http\Resources\ProductoResource; 

class ProductoCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Mapea la colección usando el ProductoResource
            'data' => $this->collection->map(function ($producto) {
                // Puedes optar por usar ProductoResource::make($producto) o simplificar como abajo:
                return [
                    'id' => $producto->id,
                    'tipo' => 'producto',
                    'atributos' => [
                        'nombre' => $producto->nombre_gomita,
                        'sabor' => $producto->sabor,
                        'precio' => (float) $producto->precio,
                        'stock_actual' => $producto->inventario->isNotEmpty() 
                        ? (int) $producto->inventario->first()->cantidad_existencias  : 0,
                    ],
                ];
            }),
        ];
    }
}