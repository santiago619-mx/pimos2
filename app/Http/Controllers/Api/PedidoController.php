<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Inventario; 
use App\Http\Resources\PedidoResource;
use App\Http\Resources\PedidoCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StorePedidoRequest; 
use App\Http\Requests\UpdatePedidoRequest; 
use Symfony\Component\HttpFoundation\Response; 
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 * name="Pedidos",
 * description="Operaciones relacionadas con la gestión de Pedidos y la lógica de Inventario."
 * )
 * Gestiona la lógica CRUD de los Pedidos, incluyendo la deducción y reversión de stock.
 * Utiliza transacciones y bloqueo pesimista (lockForUpdate) para garantizar la atomicidad del inventario.
 */
class PedidoController extends Controller
{
    use AuthorizesRequests; 
    
    /**
     * Reverte el stock al inventario. Este proceso debe ejecutarse dentro de una transacción.
     * Es crucial que el Pedido ya tenga cargada la relación 'detallesPedidos'.
     *
     * @param Pedido $pedido
     * @return void
     */
    private function revertirStock(Pedido $pedido): void
    {
        // Aseguramos que la relación 'detallesPedidos' esté cargada, aunque normalmente se haría en el controlador principal.
        $pedido->loadMissing('detallesPedidos'); 

        foreach ($pedido->detallesPedidos as $detalle) {
            
            // Bloquear explícitamente el Inventario para la reversión
            // Esto garantiza que ninguna otra transacción modifique la fila de inventario hasta el DB::commit().
            $inventario = Inventario::where('producto_id', $detalle->producto_id)
                                 ->lockForUpdate()
                                 ->first();

            if ($inventario) {
                // Devolver las unidades al inventario
                $inventario->cantidad_existencias += $detalle->cantidad;
                $inventario->save();
            } else {
                // Advertencia en el log si un inventario relacionado no se encuentra
                Log::warning("Inventario no encontrado para reversión de Pedido {$pedido->id}, Producto ID: {$detalle->producto_id}");
            }
        }
    }

    /**
     * @OA\Get(
     * path="/api/pedidos",
     * summary="Consultar lista de pedidos",
     * description="Retorna una lista paginada de todos los pedidos. Requiere permisos de Administrador.",
     * tags={"Pedidos"},
     * security={{"bearer_token":{}}},
     * @OA\Response(
     * response=200,
     * description="Lista de pedidos obtenida con éxito.",
     * @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/PedidoResource"))
     * ),
     * @OA\Response(
     * response=401,
     * description="No autenticado (Token Bearer ausente o inválido)."
     * ),
     * @OA\Response(
     * response=403,
     * description="No autorizado (El usuario no es Administrador)."
     * )
     * )
     */
    public function index()
    {
        // Autorización para ver la lista completa de pedidos (usa viewAny de PedidoPolicy)
        $this->authorize('viewAny', Pedido::class); 

        try {
            // Cargar las relaciones necesarias para el resource
            $pedidos = Pedido::with(['user', 'detallesPedidos.producto.inventario'])->paginate(10);
            return new PedidoCollection($pedidos);

        } catch (\Exception $e) {
            Log::error("Error al obtener la lista de pedidos: " . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener los pedidos.', 
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     * path="/api/pedidos",
     * summary="Crear un nuevo pedido",
     * description="Crea un pedido, calcula el total y deduce el inventario mediante una transacción de base de datos.",
     * tags={"Pedidos"},
     * security={{"bearer_token":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"detalles"},
     * @OA\Property(
     * property="user_id", 
     * type="integer", 
     * description="Opcional. ID del usuario, si no se proporciona, usa el autenticado.", 
     * example="1"
     * ),
     * @OA\Property(
     * property="detalles", 
     * type="array", 
     * description="Lista de productos y cantidades a ordenar.",
     * @OA\Items(
     * required={"producto_id", "cantidad"},
     * @OA\Property(property="producto_id", type="integer", example=1),
     * @OA\Property(property="cantidad", type="integer", example=2)
     * )
     * )
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Pedido creado y stock deducido con éxito.",
     * @OA\JsonContent(
     * type="object", 
     * @OA\Property(property="message", type="string", example="Pedido creado y stock actualizado con éxito."),
     * @OA\Property(property="data", ref="#/components/schemas/PedidoResource")
     * )
     * ),
     * @OA\Response(
     * response=400,
     * description="Stock insuficiente o datos de detalle inválidos."
     * ),
     * @OA\Response(
     * response=401,
     * description="No autenticado."
     * ),
     * @OA\Response(
     * response=403,
     * description="No autorizado para crear pedidos."
     * ),
     * @OA\Response(
     * response=404,
     * description="Producto o inventario no encontrado."
     * )
     * )
     */
    public function store(StorePedidoRequest $request)
    {
        $this->authorize('create', Pedido::class); 

        $validatedData = $request->validated();
        $total = 0;
        
        // Determinar el ID del usuario: usar el proporcionado o el autenticado
        $userId = $validatedData['user_id'] ?? auth()->id();

        if (!$userId) {
            return response()->json(['error' => 'Se requiere un ID de usuario válido para crear el pedido.'], Response::HTTP_UNAUTHORIZED);
        }
        
        try {
            DB::beginTransaction();

            $pedido = Pedido::create([
                'user_id' => $userId, 
                // Estado por defecto 'pendiente'
                'estado' => $validatedData['estado'] ?? 'pendiente', 
                'total' => 0, // El total se recalculará y actualizará al final
            ]);

            $detalles = [];
            foreach ($validatedData['detalles'] as $detalle) {
                
                $cantidadSolicitada = (int) $detalle['cantidad'];
                
                // 1. Bloquear explícitamente el Inventario y obtener el producto relacionado
                // MEJORA: Eager loading de 'producto' para asegurar que esté disponible.
                $inventario = Inventario::with('producto') 
                                             ->where('producto_id', $detalle['producto_id'])
                                             ->lockForUpdate() // Bloqueo de fila crucial
                                             ->first();

                if (!$inventario) {
                    DB::rollBack();
                    return response()->json(['error' => 'Inventario no encontrado para el Producto ID: ' . $detalle['producto_id']], Response::HTTP_NOT_FOUND);
                }
                
                $producto = $inventario->producto; 
                
                if (!$producto) {
                    DB::rollBack();
                    return response()->json(['error' => 'Producto no encontrado: ID ' . $detalle['producto_id']], Response::HTTP_NOT_FOUND);
                }

                $precioUnitario = (float) $producto->precio; 
                $subtotal = $cantidadSolicitada * $precioUnitario;

                // 2. Verificar stock y deducir dentro de la transacción bloqueada
                if ($inventario->cantidad_existencias >= $cantidadSolicitada) {
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
                    // Obtener stock disponible de forma segura para el mensaje de error
                    $stock = $inventario->cantidad_existencias;
                    return response()->json([
                        'error' => 'Stock insuficiente para ' . ($producto->nombre ?? 'producto'), 
                        'disponible' => $stock
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // 3. Crear detalles y actualizar total del pedido
            $pedido->detallesPedidos()->createMany($detalles);
            $pedido->total = $total;
            $pedido->save();

            DB::commit();

            // Cargar relaciones finales para la respuesta
            $pedido->load(['user', 'detallesPedidos.producto.inventario']);
            return response()->json([
                'message' => 'Pedido creado y stock actualizado con éxito.', 
                'data' => new PedidoResource($pedido)
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al crear el pedido: " . $e->getMessage()); 
            
            return response()->json([
                'error' => 'Error interno al crear el pedido.', 
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     * path="/api/pedidos/{id}",
     * summary="Consultar un pedido específico",
     * description="Retorna los detalles de un pedido por su ID. Accesible por el dueño del pedido o un Administrador.",
     * tags={"Pedidos"},
     * security={{"bearer_token":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID del pedido a consultar.",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=200,
     * description="Pedido obtenido con éxito.",
     * @OA\JsonContent(ref="#/components/schemas/PedidoResource")
     * ),
     * @OA\Response(
     * response=401,
     * description="No autenticado."
     * ),
     * @OA\Response(
     * response=403,
     * description="No autorizado (No es el dueño ni Administrador)."
     * ),
     * @OA\Response(
     * response=404,
     * description="Pedido no encontrado."
     * )
     * )
     */
    public function show(int $id)
    {
        $pedido = Pedido::with(['user', 'detallesPedidos.producto.inventario'])->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }
        
        // Autorización para ver (permite al dueño y al Admin)
        $this->authorize('view', $pedido); 

        return new PedidoResource($pedido);
    }

    /**
     * Actualiza un pedido existente (PUT/PATCH /api/pedidos/{id}).
     * * @OA\Patch(
     * path="/api/pedidos/{id}",
     * tags={"Pedidos"},
     * summary="Actualiza el estado o el total de un pedido",
     * security={{"bearer_token": {}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID del pedido a actualizar",
     * @OA\Schema(type="integer")
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="estado", type="string", enum={"pendiente", "enviado", "cancelado", "entregado"}, example="enviado", description="Nuevo estado del pedido."),
     * @OA\Property(property="total", type="number", format="float", example=55.99, description="Nuevo total del pedido (opcional).")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Pedido actualizado o cancelado con éxito (con reversión de stock).",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Pedido actualizado con éxito."),
     * @OA\Property(property="data", ref="#/components/schemas/PedidoResource") 
     * )
     * ),
     * @OA\Response(
     * response=404,
     * description="Pedido no encontrado."
     * ),
     * @OA\Response(
     * response=403,
     * description="No se puede cambiar el estado de un pedido ya entregado."
     * ),
     * @OA\Response(
     * response=500,
     * description="Error interno al actualizar o cancelar el pedido."
     * )
     * )
     * * @OA\Put(
     * path="/api/pedidos/{id}",
     * tags={"Pedidos"},
     * summary="Reemplaza o actualiza completamente un pedido (igual que PATCH para este recurso)",
     * security={{"bearer_token": {}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID del pedido a actualizar",
     * @OA\Schema(type="integer")
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="estado", type="string", enum={"pendiente", "enviado", "cancelado", "entregado"}, example="enviado", description="Nuevo estado del pedido."),
     * @OA\Property(property="total", type="number", format="float", example=55.99, description="Nuevo total del pedido (opcional).")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Pedido actualizado o cancelado con éxito (con reversión de stock).",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Pedido actualizado con éxito."),
     * @OA\Property(property="data", ref="#/components/schemas/PedidoResource") 
     * )
     * ),
     * @OA\Response(
     * response=404,
     * description="Pedido no encontrado."
     * ),
     * @OA\Response(
     * response=403,
     * description="No se puede cambiar el estado de un pedido ya entregado."
     * ),
     * @OA\Response(
     * response=500,
     * description="Error interno al actualizar o cancelar el pedido."
     * )
     * )
     */
    public function update(UpdatePedidoRequest $request, int $id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            // ESTE DEBE SER SIEMPRE EL PRIMER CHEQUEO: Verifica que $pedido NO sea nulo
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        // Autorización de la política antes de cualquier cambio
        $this->authorize('update', $pedido);

        $validatedData = $request->validated();
        
        // 1. COMPROBACIÓN DE ESTADO FINAL (Lógica del usuario, colocada correctamente)
        // Si el pedido ya está entregado o cancelado, se considera un estado final
        // y se bloquea CUALQUIER otra actualización.
        if ($pedido->estado === 'entregado' || $pedido->estado === 'cancelado') {
             // Solo si hay datos válidos en la solicitud (es decir, se intenta actualizar algo)
             if (!empty($validatedData)) {
                 return response()->json([
                     'error' => 'No se permite actualizar un pedido que ya está finalizado (Entregado o Cancelado).'
                 ], 403);
             }
        }
        
        try {
            // Revertir inventario si el estado cambia a 'cancelado' (Lógica crucial)
            if (isset($validatedData['estado']) && $validatedData['estado'] === 'cancelado' && $pedido->estado !== 'cancelado') {
                
                // Lógica de reversión de stock (utilizando el método privado seguro)
                DB::beginTransaction();
                
                // Aseguramos que los detalles estén cargados para el helper
                $pedido->load('detallesPedidos'); 
                $this->revertirStock($pedido); // Usa el método robusto con lockForUpdate

                $pedido->update($validatedData);
                DB::commit();

                $pedido->load(['user', 'detallesPedidos.producto.inventario']);
                return response()->json([
                    'message' => 'Pedido CANCELADO con éxito. Stock revertido.', 
                    'data' => new PedidoResource($pedido)
                ], 200);

            }

            // Actualización estándar para otros campos/estados
            $pedido->update($validatedData);
            
            $pedido->load(['user', 'detallesPedidos.producto.inventario']);
            return response()->json([
                'message' => 'Pedido actualizado con éxito.', 
                'data' => new PedidoResource($pedido)
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack(); // En caso de que la cancelación fallara después del beginTransaction
            Log::error("Error al actualizar el pedido {$id}: " . $e->getMessage());
            return response()->json(['error' => 'Error al actualizar el pedido.', 'message' => $e->getMessage()], 500);
        }
    }

   /**
 * @OA\Delete(
 *     path="/api/pedidos/{pedido}",
 *     summary="Eliminar un pedido",
 *     description="Elimina un pedido y revierte el stock asociado. Solo para Administradores.",
 *     tags={"Pedidos"},
 *     security={{"bearer_token":{}}},
 *     @OA\Parameter(
 *         name="pedido",
 *         in="path",
 *         required=true,
 *         description="ID del pedido a eliminar.",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Pedido eliminado con éxito.",
 *       @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Pedido eliminado con éxito. Stock revertido.")
 *        )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="No autenticado."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="No autorizado."
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Pedido no encontrado."
 *     )
 * )
 */

   public function destroy(int $id)
{
    $pedido = Pedido::with('detallesPedidos')->find($id);

    if (!$pedido) {
        return response()->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
    }

    // Autorización mediante PedidoPolicy
    $this->authorize('delete', $pedido);

    if ($pedido->estado === 'entregado') {
        return response()->json(['error' => 'No se puede eliminar un pedido ya entregado.'], Response::HTTP_FORBIDDEN);
    }

    try {
        DB::beginTransaction();

        // Revertir el stock
        $this->revertirStock($pedido);

        // Eliminar el pedido
        $pedido->delete();

        DB::commit();

        // Cambiamos 204 → 200 y enviamos mensaje
        return response()->json([
            'message' => 'Pedido eliminado con éxito. Stock revertido.'
        ], Response::HTTP_OK);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Error al eliminar pedido {$pedido->id}: " . $e->getMessage());

        return response()->json([
            'error' => 'Error al eliminar el pedido y revertir el stock.'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
}