<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Clase que envuelve la colección paginada de pedidos.
 * Se utiliza para transformar una lista (Request de index) de Pedido en un JSON,
 * asegurando que cada elemento individual use PedidoResource.
 */
class PedidoCollection extends ResourceCollection
{
    /**
     * Define qué Resource debe usarse para transformar cada elemento de la colección.
     *
     * @var string
     */
    public $collects = PedidoResource::class;

    /**
     * Transforma la colección de pedidos en una estructura JSON.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Esto automáticamente aplica PedidoResource y maneja la paginación.
        return parent::toArray($request);
    }
}