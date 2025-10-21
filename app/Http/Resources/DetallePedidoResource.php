<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetallePedidoResource extends JsonResource
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
            'tipo' => 'detalle_pedido',
            'atributos' => [
                'cantidad' => (int) $this->cantidad,
                'precio_unitario' => (float) $this->precio_unitario,
                'subtotal' => (float) $this->cantidad * $this->precio_unitario,
            ],
            'relaciones' => [
                // Incluimos la información básica del producto
                'producto' => $this->whenLoaded('producto', function () {
                    return [
                        'id' => $this->producto->id,
                        'tipo' => 'producto',
                        'nombre' => $this->producto->nombre_gomita,
                        'sabor' => $this->producto->sabor,
                    ];
                }),
            ],
        ];
    }
}