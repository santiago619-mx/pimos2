<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventario;
use App\Http\Resources\InventarioResource;
use App\Http\Requests\StoreInventarioRequest; // Importar StoreInventarioRequest
use App\Http\Requests\UpdateInventarioRequest; // Importar UpdateInventarioRequest
use Symfony\Component\HttpFoundation\Response;

// Importar el trait AuthorizesRequests para la autorización de políticas
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 

/**
 * @OA\Tag(
 * name="Inventario",
 * description="Operaciones de gestión de stock de productos"
 * )
 * Gestiona la lógica CRUD para el Inventario (Stock).
 */
class InventarioController extends Controller
{
    // Usar el trait AuthorizesRequests
    use AuthorizesRequests; 

    /**
     * @OA\Get(
     * path="/api/inventario",
     * summary="Consultar todo el inventario",
     * description="Retorna una lista de todos los registros de inventario con los productos asociados.",
     * tags={"Inventario"},
     * security={{"bearer_token":{}}},
     * @OA\Response(
     * response=200,
     * description="Operación exitosa",
     * @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/InventarioResource"))
     * ),
     * @OA\Response(response=403, description="No autorizado"),
     * @OA\Response(response=500, description="Error interno del servidor")
     * )
     */
    public function index()
    {
        // Autorización: Permiso de Lectura
        $this->authorize('inventario.ver'); 

        try {
            $inventarios = Inventario::with('producto')->get();
            return InventarioResource::collection($inventarios);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener el inventario.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * @OA\Post(
     * path="/api/inventario",
     * summary="Crear nuevo registro de inventario",
     * description="Crea un nuevo registro de stock para un producto que no tenga uno.",
     * tags={"Inventario"},
     * security={{"bearer_token":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"producto_id","cantidad_existencias"},
     * @OA\Property(property="producto_id", type="integer", example=1, description="ID del producto a inventariar."),
     * @OA\Property(property="cantidad_existencias", type="integer", example=50, description="Cantidad inicial de existencias.")
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Registro de inventario creado con éxito.",
     * @OA\JsonContent(ref="#/components/schemas/InventarioResource")
     * ),
     * @OA\Response(response=403, description="No autorizado"),
     * @OA\Response(response=422, description="Error de validación")
     * )
     */
    // Se usa el Request personalizado para la validación
    public function store(StoreInventarioRequest $request)
    {
        // Autorización: Permiso de Creación
        $this->authorize('inventario.crear'); 

        // Usamos safe() para obtener solo los datos validados y prevenir mass assignment
        $validated = $request->safe()->only(['producto_id', 'cantidad_existencias']);

        try {
            $inventario = Inventario::create($validated);
            $inventario->load('producto');

            // Devolver respuesta 201 (Created)
            return response()->json([
                'message' => 'Registro de inventario creado con éxito.', 
                'data' => new InventarioResource($inventario)
            ], Response::HTTP_CREATED);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear el registro de inventario.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * @OA\Get(
     * path="/api/inventario/{id}",
     * summary="Consultar un registro de inventario",
     * description="Retorna un registro de inventario específico por ID.",
     * tags={"Inventario"},
     * security={{"bearer_token":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer"),
     * description="ID del registro de inventario."
     * ),
     * @OA\Response(
     * response=200,
     * description="Operación exitosa",
     * @OA\JsonContent(ref="#/components/schemas/InventarioResource")
     * ),
     * @OA\Response(response=403, description="No autorizado"),
     * @OA\Response(response=404, description="Registro no encontrado")
     * )
     */
    public function show(Inventario $inventario)
    {
        // Autorización: Permiso de Lectura
        $this->authorize('inventario.ver'); 

        $inventario->load('producto');
        return new InventarioResource($inventario);
    }

    /**
     * @OA\Put(
     * path="/api/inventario/{id}",
     * summary="Actualizar stock (cantidad)",
     * description="Actualiza la cantidad de existencias de un registro de inventario. Solo se puede actualizar la cantidad.",
     * tags={"Inventario"},
     * security={{"bearer_token":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer"),
     * description="ID del registro de inventario a actualizar."
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"cantidad_existencias"},
     * @OA\Property(property="cantidad_existencias", type="integer", example=100, description="Nueva cantidad de existencias.")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Inventario actualizado con éxito.",
     * @OA\JsonContent(ref="#/components/schemas/InventarioResource")
     * ),
     * @OA\Response(response=403, description="No autorizado"),
     * @OA\Response(response=422, description="Error de validación"),
     * @OA\Response(response=404, description="Registro no encontrado")
     * )
     */
    // Se usa el Request personalizado para la validación
    public function update(UpdateInventarioRequest $request, Inventario $inventario)
    {
        // Autorización: Permiso de Actualización de Stock
        $this->authorize('inventario.ajustar_stock');

        // Usamos safe() para obtener solo los datos validados y prevenir mass assignment
        $validated = $request->safe()->only(['cantidad_existencias']);

        try {
            $inventario->update($validated);
            $inventario->load('producto');
            
            // Devolver respuesta 200 (OK)
            return response()->json(['message' => 'Inventario actualizado con éxito.', 'data' => new InventarioResource($inventario)], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el inventario.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * @OA\Delete(
     * path="/api/inventario/{id}",
     * summary="Eliminar registro de inventario",
     * description="Elimina un registro de inventario (stock) por ID.",
     * tags={"Inventario"},
     * security={{"bearer_token":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer"),
     * description="ID del registro de inventario a eliminar."
     * ),
     * @OA\Response(response=204, description="Registro de inventario eliminado con éxito. (No Content)"),
     * @OA\Response(response=403, description="No autorizado"),
     * @OA\Response(response=404, description="Registro no encontrado")
     * )
     */
    public function destroy(Inventario $inventario)
    {
        // Autorización: Permiso de Eliminación de Registro (Solo Admin)
        $this->authorize('inventario.eliminar_registro');
        
        try {
            $inventario->delete();
            
            // Devolver respuesta 204 (No Content)
            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el registro de inventario.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}