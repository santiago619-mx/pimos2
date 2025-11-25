<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 * schema="InventarioResource",
 * title="Inventario Resource",
 * description="Estructura de la respuesta para un registro de Inventario",
 * @OA\Property(property="id", type="integer", example=5, description="ID del registro de inventario"),
 * @OA\Property(property="tipo", type="string", example="inventario"),
 * @OA\Property(
 * property="atributos",
 * type="object",
 * @OA\Property(property="cantidad_existencias", type="integer", example=100)
 * ),
 * @OA\Property(
 * property="relaciones",
 * type="object",
 * @OA\Property(
 * property="producto",
 * type="object",
 * description="Detalles básicos del producto",
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="nombre", type="string", example="Gomita de Fresa")
 * )
 * )
 * )
 */
class InventarioResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // CORRECCIÓN FINAL: Se usa explícitamente $this->inventario_id,
        // la clave primaria definida en el modelo, para evitar el null.
        return [
            'id' => $this->inventario_id, 
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