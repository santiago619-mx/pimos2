<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Producto;
use App\Http\Resources\PedidoResource;
use App\Http\Resources\PedidoCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StorePedidoRequest; 
use App\Http\Requests\UpdatePedidoRequest; 
use Symfony\Component\HttpFoundation\Response; 

// Importar el trait AuthorizesRequests para la autorización de políticas
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 

/**
 * Gestiona la lógica CRUD de los Pedidos, incluyendo la deducción y reversión de stock.
 */
class PedidoController extends Controller
{
    // Usar el trait AuthorizesRequests
    use AuthorizesRequests; 
    
    /**
     * Muestra una lista de todos los pedidos (GET /api/pedidos).
     */
    public function index()
    {
        // Autorización para ver la lista completa de pedidos (usa viewAny de PedidoPolicy)
        $this->authorize('viewAny', Pedido::class); 

        try {
            // Nota: La política restringe el acceso solo al Admin para ver *todos* los pedidos.
            $pedidos = Pedido::with(['user', 'detallesPedidos.producto.inventario'])->paginate(10);
            return new PedidoCollection($pedidos);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los pedidos.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Almacena un nuevo pedido y deduce el inventario (POST /api/pedidos).
     */
    public function store(StorePedidoRequest $request)
    {
        // Autorización para crear un pedido (usa create de PedidoPolicy)
        $this->authorize('create', Pedido::class); 

        $validatedData = $request->validated();
        $total = 0;
        
        // 1. Determinar el user_id: usar el ID validado si se envió (e.g., por un Admin), sino, el autenticado.
        $userId = $validatedData['user_id'] ?? auth()->id();

        if (!$userId) {
            // Esto solo ocurriría si no hay user_id en la solicitud y el usuario no está autenticado (caso borde).
            return response()->json(['error' => 'Se requiere un ID de usuario válido para crear el pedido.'], Response::HTTP_UNAUTHORIZED);
        }
        
        try {
            DB::beginTransaction();

            $pedido = Pedido::create([
                'user_id' => $userId, 
                'estado' => $validatedData['estado'] ?? 'pendiente', 
                'total' => 0,
            ]);

            $detalles = [];
            foreach ($validatedData['detalles'] as $detalle) {
                // Bloquear el producto para asegurar la atomicidad del inventario
                // Utilizamos lockForUpdate dentro de la transacción.
                $producto = Producto::lockForUpdate()->find($detalle['producto_id']);
                
                if (!$producto) {
                    DB::rollBack();
                    return response()->json(['error' => 'Producto no encontrado: ID ' . $detalle['producto_id']], Response::HTTP_NOT_FOUND);
                }

                // Usamos optional chaining para acceder a inventario de forma segura
                $inventario = $producto->inventario->first();
                $cantidadSolicitada = (int) $detalle['cantidad'];
                // Usamos el precio del producto al momento de la compra
                $precioUnitario = (float) $producto->precio; 
                $subtotal = $cantidadSolicitada * $precioUnitario;

                // Deducir Inventario
                if ($inventario && $inventario->cantidad_existencias >= $cantidadSolicitada) {
                    $inventario->cantidad_existencias -= $cantidadSolicitada;
                    $inventario->save();
                    $total += $subtotal;

                    $detalles[] = [
                        'producto_id' => $producto->id,
                        'cantidad' => $cantidadSolicitada,
                        'precio_unitario' => $precioUnitario,
                    ];
                } else {
                    DB::rollBack();
                    $stock = $inventario ? $inventario->cantidad_existencias : 0;
                    // El error de stock lo maneja principalmente CheckStock, pero se mantiene la verificación transaccional
                    return response()->json([
                        'error' => 'Stock insuficiente para ' . ($producto->nombre ?? 'producto'), 
                        'disponible' => $stock
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // Actualizar detalles y total del pedido
            $pedido->detallesPedidos()->createMany($detalles);
            $pedido->total = $total;
            $pedido->save();

            DB::commit();

            $pedido->load(['user', 'detallesPedidos.producto.inventario']);
            return response()->json([
                'message' => 'Pedido creado y stock actualizado con éxito.', 
                'data' => new PedidoResource($pedido)
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear el pedido.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Muestra un pedido específico (GET /api/pedidos/{id}).
     */
    public function show(int $id)
    {
        $pedido = Pedido::with(['user', 'detallesPedidos.producto.inventario'])->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }
        
        // Autorización para ver este pedido específico (usa view de PedidoPolicy)
        $this->authorize('view', $pedido); 

        return new PedidoResource($pedido);
    }

    /**
     * Actualiza un pedido existente (PUT/PATCH /api/pedidos/{id}).
     * Permite cambiar el estado (procesar) o cancelar (requiere permiso estricto).
     */
    public function update(UpdatePedidoRequest $request, int $id)
    {
        $pedido = Pedido::with('detallesPedidos.producto.inventario')->find($id); // Cargar detalles para la reversión

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $validatedData = $request->validated();
        
        // ** LÓGICA DE CANCELACIÓN (Reversión de Stock) **
        if (isset($validatedData['estado']) && $validatedData['estado'] === 'cancelado' && $pedido->estado !== 'cancelado') {
            
            // Usamos la autorización estricta para CANCELAR (usa cancel de PedidoPolicy)
            $this->authorize('cancel', $pedido);
            
            // Seguridad: No permitir cancelar si ya está entregado
            if ($pedido->estado === 'entregado') {
                return response()->json(['error' => 'No se puede cancelar un pedido que ya fue entregado.'], Response::HTTP_FORBIDDEN);
            }
            
            try {
                DB::beginTransaction();

                // Revertir el stock al inventario
                foreach ($pedido->detallesPedidos as $detalle) {
                    // Bloquear el producto antes de acceder a su inventario para la reversión
                    $producto = Producto::lockForUpdate()->find($detalle->producto_id);
                    // Accedemos de forma segura a inventario (puede ser null)
                    $inventario = $producto?->inventario->first(); 

                    if ($inventario) {
                        // Revertir la cantidad del detalle
                        $inventario->cantidad_existencias += $detalle->cantidad;
                        $inventario->save();
                    }
                }
                
                // Actualizar el estado del pedido
                $pedido->update(['estado' => 'cancelado']);
                DB::commit();

                $pedido->load(['user', 'detallesPedidos.producto.inventario']);
                return response()->json([
                    'message' => 'Pedido CANCELADO con éxito. Stock revertido.', 
                    'data' => new PedidoResource($pedido)
                ], Response::HTTP_OK);

            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['error' => 'Error al cancelar el pedido y revertir el stock.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        
        // ** ACTUALIZACIÓN ESTÁNDAR (Para otros estados: 'enviado', 'entregado') **
        
        // Usamos la autorización estándar para actualizar (usa update de PedidoPolicy)
        // Solo permitido al administrador por la política.
        $this->authorize('update', $pedido);

        try {
            // Seguridad: No permitir cambiar nada si ya está entregado o cancelado
            if ($pedido->estado === 'entregado' || $pedido->estado === 'cancelado') {
                return response()->json(['error' => 'No se puede modificar un pedido que ya está en estado "entregado" o "cancelado".'], Response::HTTP_FORBIDDEN);
            }
            
            // Solo actualizamos campos generales como el 'estado'.
            $pedido->update($validatedData);
            
            $pedido->load(['user', 'detallesPedidos.producto.inventario']);
            return response()->json([
                'message' => 'Pedido actualizado con éxito.', 
                'data' => new PedidoResource($pedido)
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el pedido.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Elimina un pedido y revierte el inventario (DELETE /api/pedidos/{id}).
     */
    public function destroy(int $id)
    {
        $pedido = Pedido::with('detallesPedidos.producto.inventario')->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        // Autorización para eliminar (usa delete de PedidoPolicy)
        $this->authorize('delete', $pedido);

        // Condición de seguridad: No eliminar si ya fue entregado
        if ($pedido->estado === 'entregado') {
             return response()->json(['error' => 'No se puede eliminar un pedido ya entregado.'], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::beginTransaction();

            // 1. Revertir el stock al inventario
            // Se utiliza lockForUpdate para asegurar la atomicidad en la reversión.
            foreach ($pedido->detallesPedidos as $detalle) {
                 $producto = Producto::lockForUpdate()->find($detalle->producto_id);
                 $inventario = $producto?->inventario->first();

                if ($inventario) {
                    $inventario->cantidad_existencias += $detalle->cantidad;
                    $inventario->save();
                }
            }

            // 2. Eliminar el pedido
            $pedido->delete();
            
            DB::commit();

            return response()->json(['message' => 'Pedido y detalles eliminados con éxito. Stock revertido.'], Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al eliminar el pedido y revertir el stock.', 
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}