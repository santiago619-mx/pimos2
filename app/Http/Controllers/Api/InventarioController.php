<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\InventarioResource; // Importado

/**
 * Gestiona la visualización y actualización del Inventario.
 */
class InventarioController extends Controller
{
    /**
     * Muestra la lista completa del inventario (stock de todos los productos).
     * MODIFICACIÓN: Usa InventarioResource::collection() y carga relaciones.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Carga la relación 'producto'
            $inventario = Inventario::with('producto')->get();
            
            // Usa el método collection() en el Resource
            return InventarioResource::collection($inventario); 

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener el inventario.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Muestra el stock de un producto específico.
     * MODIFICACIÓN: Usa InventarioResource y carga relaciones.
     * @param int $producto_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $producto_id)
    {
        $inventario = Inventario::where('producto_id', $producto_id)
                                ->with('producto') // Carga la relación
                                ->first();

        if (!$inventario) {
            return response()->json(['error' => 'Stock no encontrado para ese producto.'], 404);
        }

        return new InventarioResource($inventario); // Usa el Resource
    }

    /**
     * Actualiza la cantidad de existencias para un producto específico (o la crea si no existe).
     * MODIFICACIÓN: Retorna el Resource del inventario actualizado (200 o 201).
     * @param \Illuminate\Http\Request $request
     * @param int $producto_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $producto_id)
    {
        try {
            $validatedData = $request->validate([
                'cantidad_existencias' => 'required|integer|min:0',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Datos de entrada inválidos.', 'messages' => $e->errors()], 422);
        }

        $inventario = Inventario::where('producto_id', $producto_id)->first();
        $isNew = false;

        if (!$inventario) {
            // Si no existe, verificamos que el Producto exista para crear el Inventario
            if (!Producto::find($producto_id)) {
                return response()->json(['error' => 'El producto asociado no existe.'], 404);
            }

            // Crear el registro de inventario (código 201)
            $inventario = Inventario::create([
                'producto_id' => $producto_id,
                'cantidad_existencias' => $validatedData['cantidad_existencias']
            ]);
            $isNew = true;
        } else {
            // Actualizar el registro de inventario (código 200)
            $inventario->update($validatedData);
        }

        // Carga la relación 'producto' antes de devolver el Resource
        $inventario->load('producto');

        $statusCode = $isNew ? 201 : 200;
        $message = $isNew ? 'Inventario inicial creado con éxito.' : 'Stock actualizado con éxito.';

        return response()->json([
            'message' => $message, 
            'data' => new InventarioResource($inventario) // Usa el Resource
        ], $statusCode);
    }
    
    /**
     * Elimina el registro de inventario para un producto específico.
     * @param int $producto_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $producto_id)
    {
        $inventario = Inventario::where('producto_id', $producto_id)->first();

        if (!$inventario) {
            return response()->json(['error' => 'Registro de inventario no encontrado para el producto.'], 404);
        }

        try {
            $inventario->delete();
            
            // Retorna una respuesta simple 200 OK, ya que no hay datos de inventario que devolver.
            return response()->json(['message' => 'Registro de inventario eliminado con éxito.'], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el registro de inventario.', 'message' => $e->getMessage()], 500);
        }
    }
}
