<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Importar el recurso de inventario
use App\Http\Resources\InventarioResource; 

/**
 * @OA\Schema(
 * schema="ProductoResource",
 * title="Producto Resource",
 * description="Estructura de la respuesta detallada para un solo Producto",
 * @OA\Property(property="id", type="integer", example=1, description="ID del producto"),
 * @OA\Property(property="tipo", type="string", example="producto"),
 * @OA\Property(
 * property="atributos",
 * type="object",
 * @OA\Property(property="nombre", type="string", example="Oso Gomita Clásico"),
 * @OA\Property(property="sabor", type="string", example="Fresa Ácida"),
 * @OA\Property(property="tamano", type="string", example="Mediano"),
 * @OA\Property(property="precio", type="number", format="float", example=5.50)
 * ),
 * @OA\Property(property="relaciones", ref="#/components/schemas/ProductoResourceRelations")
 * )
 * @OA\Schema(
 * schema="ProductoResourceRelations",
 * type="object",
 * @OA\Property(property="inventario", ref="#/components/schemas/InventarioResource", nullable=true, description="Stock asociado al producto, si existe.")
 * )
 */
class ProductoResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => 'producto',
            'atributos' => [
                'nombre' => $this->nombre_gomita,
                'sabor' => $this->sabor,
                'tamano' => $this->tamano,
                'precio' => (float) $this->precio, 
            ],
            'relaciones' => [
                // Usa la relación HasMany y el InventarioResource corregido.
                'inventario' => $this->whenLoaded('inventario', function () {
                    if ($this->inventario->isNotEmpty()) {
                         // Usar InventarioResource para formatear el primer registro.
                         return new InventarioResource($this->inventario->first());
                    }
                    return null;
                }),
            ],
        ];
    }
}