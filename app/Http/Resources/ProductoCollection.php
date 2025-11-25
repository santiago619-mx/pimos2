<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

// Importar el recurso simple para mapear
use App\Http\Resources\ProductoResource; 

/**
 * @OA\Schema(
 * schema="ProductoCollection",
 * title="Producto Collection",
 * description="Estructura de la respuesta para el listado de Productos",
 * @OA\Property(
 * property="data",
 * type="array",
 * @OA\Items(
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="tipo", type="string", example="producto"),
 * @OA\Property(
 * property="atributos",
 * type="object",
 * @OA\Property(property="nombre", type="string", example="Oso Gomita Clásico"),
 * @OA\Property(property="sabor", type="string", example="Fresa Ácida"),
 * @OA\Property(property="precio", type="number", format="float", example=5.50),
 * @OA\Property(property="stock_actual", type="integer", example=100, description="Cantidad de existencias en inventario")
 * )
 * )
 * )
 * )
 */
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