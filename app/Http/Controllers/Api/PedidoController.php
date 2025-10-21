<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\PedidoResource;     // Importado
use App\Http\Resources\PedidoCollection;  // Importado

/**
 * Gestiona la lógica de Pedidos.
 */
class PedidoController extends Controller
{
    /**
     * Muestra una lista de todos los pedidos.
     * MODIFICACIÓN: Usa PedidoCollection y carga relaciones.
     * @return \App\Http\Resources\PedidoCollection
     */
    public function index()
    {
        try {
            // MUY IMPORTANTE: Cargar todas las relaciones que el Resource usará
            $pedidos = Pedido::with(['user', 'detallesPedidos.producto']) 
                             ->latest() 
                             ->paginate(20); 

            return new PedidoCollection($pedidos); 

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener la lista de pedidos.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Crea un nuevo pedido con sus líneas de detalle.
     * MODIFICACIÓN: Retorna el Resource del pedido creado (201).
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Esta validación y lógica asume un usuario autenticado y la estructura de carrito.
        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|exists:productos,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            $total = 0;
            $detallesParaCrear = [];

            // Obtener productos y calcular el total
            foreach ($validatedData['detalles'] as $detalle) {
                $producto = Producto::find($detalle['producto_id']);
                
                // Cálculo del subtotal y total
                $subtotal = $producto->precio * $detalle['cantidad'];
                $total += $subtotal;

                // Preparamos los datos del detalle
                $detallesParaCrear[] = [
                    'producto_id' => $detalle['producto_id'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $producto->precio, // Registrar el precio al momento de la compra
                ];
            }
            
            // Crear el Pedido maestro
            $pedido = Pedido::create([
                'user_id' => $validatedData['user_id'],
                'total' => $total,
                'estado' => 'pendiente', // Estado inicial
            ]);
            
            // Adjuntar los detalles al pedido
            $pedido->detallesPedidos()->createMany($detallesParaCrear);

            DB::commit();

            // Carga las relaciones para que el Resource las serialice correctamente
            $pedido->load(['user', 'detallesPedidos.producto']);
            
            return response()->json([
                'message' => 'Pedido creado con éxito.', 
                'data' => new PedidoResource($pedido) // Usa el Resource
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Datos de entrada inválidos.', 'messages' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al procesar el pedido.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Muestra un pedido específico.
     * MODIFICACIÓN: Usa PedidoResource y carga relaciones.
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id)
    {
        // MUY IMPORTANTE: Cargar todas las relaciones que el Resource usará
        $pedido = Pedido::with(['user', 'detallesPedidos.producto'])
                        ->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        return new PedidoResource($pedido); // Usa el Resource
    }

    /**
     * Actualiza un pedido existente (típicamente su estado).
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        try {
            // Se valida solo el campo 'estado'
            $validatedData = $request->validate([
                'estado' => 'sometimes|required|in:pendiente,procesando,enviado,entregado,cancelado',
            ]);

            $pedido->update($validatedData);

            // Carga las relaciones para devolver el pedido actualizado en el formato Resource
            $pedido->load(['user', 'detallesPedidos.producto']);
            
            return response()->json([
                'message' => 'Pedido actualizado con éxito.', 
                'data' => new PedidoResource($pedido) // Usa el Resource para la respuesta
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['error' => 'Datos de entrada inválidos.', 'messages' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el pedido.', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Elimina un pedido.
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        try {
            $pedido->delete();
            
            // Respuesta simple sin Resource, 200 OK con mensaje de éxito.
            return response()->json(['message' => 'Pedido eliminado con éxito.'], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el pedido.', 'message' => $e->getMessage()], 500);
        }
    }
}
