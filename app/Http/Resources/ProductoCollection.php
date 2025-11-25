<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

// Importar el recurso simple para mapear
use App\Http\Resources\ProductoResource; 

class ProductoCollection extends ResourceCollection
{
    /**
     * Transforma la colección de recursos en un array.
     */
    public function toArray(Request $request): array
    {
        return [
            // Mapea la colección usando el ProductoResource
            'data' => $this->collection->map(function ($producto) {
                // Usamos un mapeo directo para el listado, incluyendo el stock actual.
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