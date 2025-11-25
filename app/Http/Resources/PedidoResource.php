<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Importar los recursos relacionados
use App\Http\Resources\UserResource; 
use App\Http\Resources\DetallePedidoResource; 

class PedidoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Tu modelo usa 'id', que es el ID del Pedido
        return [
            'id' => $this->id, 
            'tipo' => 'pedido',
            'atributos' => [
                // *** AGREGADO: Se incluye el ID del pedido para mayor claridad ***
                'pedido_id' => $this->id,
                'total' => (float) $this->total,
                'estado' => $this->estado,
                'fecha_pedido' => $this->created_at->format('Y-m-d H:i:s'),
            ],
            'relaciones' => [
                // Relación con el Usuario
                'usuario' => $this->whenLoaded('user', function () {
                    return new UserResource($this->user);
                }),
                
                // Relación con los Detalles del Pedido (Colección)
                'detalles' => $this->whenLoaded('detallesPedidos', function () {
                    // Usamos DetallePedidoResource::collection() para formatear el array de detalles
                    return DetallePedidoResource::collection($this->detallesPedidos);
                }),
            ],
        ];
    }
}