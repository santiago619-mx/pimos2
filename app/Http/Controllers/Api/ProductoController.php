<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\ProductoResource;     // Importado para show, store, update
use App\Http\Resources\ProductoCollection;  // Importado para index

/**
 * Gestiona la lógica CRUD de las Gomitas (Productos).
 */
class ProductoController extends Controller
{
    /**
     * Muestra una lista de todos los productos.
     * MODIFICACIÓN: Usa ProductoCollection para estandarizar la respuesta de lista.
     * @return \App\Http\Resources\ProductoCollection
     */
    public function index()
    {
        try {
            // Carga la relación de inventario para que el Resource pueda acceder al stock
            $productos = Producto::with('inventario')->get();
            return new ProductoCollection($productos); 

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener la lista de productos.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Almacena un nuevo producto en la base de datos.
     * MODIFICACIÓN: Retorna el Resource del producto creado (201).
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'nombre_gomita' => 'required|string|max:255|unique:productos,nombre_gomita',
                'sabor' => 'required|string|max:255',
                'tamano' => 'required|string|max:255',
                'precio' => 'required|numeric|min:0.01',
                'cantidad_existencias' => 'nullable|integer|min:0',
            ]);

            $producto = Producto::create($validatedData);

            // Crear el registro de inventario inicial (si se proporcionó la cantidad)
            if (isset($validatedData['cantidad_existencias']))
            {
                $producto->inventario()->create([
                    'cantidad_existencias' => $validatedData['cantidad_existencias']
                ]);
            }
            
            $producto->load('inventario');

            return response()->json([
                'message' => 'Producto creado con éxito.', 
                'data' => new ProductoResource($producto) // Usa el Resource
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['error' => 'Datos de entrada inválidos.', 'messages' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear el producto.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Muestra un producto específico.
     * MODIFICACIÓN: Usa ProductoResource para estandarizar la respuesta de item.
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id)
    {
        $producto = Producto::with('inventario')->find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        return new ProductoResource($producto); // Usa el Resource
    }

    /**
     * Actualiza un producto existente.
     * MODIFICACIÓN: Retorna el Resource del producto actualizado (200).
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        try {
            $validatedData = $request->validate([
                // unique ignora el producto actual
                'nombre_gomita' => 'sometimes|required|string|max:255|unique:productos,nombre_gomita,' . $id,
                'sabor' => 'sometimes|required|string|max:255',
                'tamano' => 'sometimes|required|string|max:255',
                'precio' => 'sometimes|required|numeric|min:0.01',
            ]);

            $producto->update($validatedData);
            $producto->load('inventario');

            return response()->json(['message' => 'Producto actualizado con éxito.', 'data' => new ProductoResource($producto)], 200); // Usa el Resource

        } catch (ValidationException $e) {
            return response()->json(['error' => 'Datos de entrada inválidos.', 'messages' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el producto.', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Elimina un producto de la base de datos.
     * Sin modificaciones de Resource, ya que la respuesta es simple (200 OK).
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        try {
            $producto->delete();
            
            return response()->json(['message' => 'Producto eliminado con éxito.'], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el producto.', 'message' => $e->getMessage()], 500);
        }
    }
}
