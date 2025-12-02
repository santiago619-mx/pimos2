<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Http\Resources\ProductoResource; 
use App\Http\Resources\ProductoCollection; 
use App\Http\Requests\StoreProductoRequest; 
use App\Http\Requests\UpdateProductoRequest; 
use Illuminate\Http\Request; 
use Symfony\Component\HttpFoundation\Response;

// Trait para usar $this->authorize('permiso')
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 

/**
 * @OA\Tag(
 * name="Productos",
 * description="Operaciones de gestión de productos (gomitas)"
 * )
 * Gestiona la lógica CRUD de los Productos.
 */
class ProductoController extends Controller
{
    // Usar el trait AuthorizesRequests para la autorización de políticas
    use AuthorizesRequests; 

    /**
     * @OA\Get(
     * path="/api/productos",
     * summary="Consultar lista de productos",
     * description="Retorna una lista paginada de todos los productos disponibles con el stock actual.",
     * tags={"Productos"},
     * security={{"bearer_token":{}}},
     * @OA\Response(
     * response=200,
     * description="Operación exitosa",
     * @OA\JsonContent(ref="#/components/schemas/ProductoCollection")
     * ),
     * @OA\Response(response=403, description="No autorizado"),
     * @OA\Response(response=500, description="Error interno del servidor")
     * )
     */
    public function index()
    {
        // Autorización basada en Spatie: el usuario debe tener el permiso 'productos.ver'
        $this->authorize('productos.ver'); 

        try {
            // Carga la relación de inventario para el stock
            $productos = Producto::with('inventario')->get();
            return new ProductoCollection($productos); 

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener productos.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     * path="/api/productos",
     * summary="Crear un nuevo producto",
     * description="Registra un nuevo producto. Opcionalmente, permite definir el stock inicial.",
     * tags={"Productos"},
     * security={{"bearer_token":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"nombre_gomita", "sabor", "tamano", "precio"},
     * @OA\Property(property="nombre_gomita", type="string", example="Oso Gomita Clásico", description="Nombre del producto, debe ser único."),
     * @OA\Property(property="sabor", type="string", example="Fresa Ácida", description="Sabor del producto."),
     * @OA\Property(property="tamano", type="string", example="Mediano", description="Tamaño del producto."),
     * @OA\Property(property="precio", type="number", format="float", example=5.50, description="Precio unitario."),
     * @OA\Property(property="cantidad_existencias", type="integer", example=100, nullable=true, description="Stock inicial (opcional).")
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Producto creado con éxito.",
     * @OA\JsonContent(ref="#/components/schemas/ProductoResource")
     * ),
     * @OA\Response(response=403, description="No autorizado"),
     * @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function store(StoreProductoRequest $request)
    {
        // Autorización basada en Spatie: el usuario debe tener el permiso 'productos.crear'
        $this->authorize('productos.crear'); 

        $validatedData = $request->validated(); 

        try {
            // Se crea el producto con los datos validados, excluyendo 'cantidad_existencias'
            $producto = Producto::create($request->safe()->except('cantidad_existencias'));

            if (isset($validatedData['cantidad_existencias']))
            {
                // Se crea el inventario asociado
                $producto->inventario()->create([
                    'cantidad_existencias' => $validatedData['cantidad_existencias']
                ]);
            }
            
            $producto->load('inventario');

            // Respuesta con código 201 (Created)
            return response()->json([
                'message' => 'Producto creado con éxito.', 
                'data' => new ProductoResource($producto) 
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear el producto.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     * path="/api/productos/{id}",
     * summary="Consultar un producto",
     * description="Retorna la información detallada de un producto específico, incluyendo su stock.",
     * tags={"Productos"},
     * security={{"bearer_token":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer"),
     * description="ID del producto."
     * ),
     * @OA\Response(
     * response=200,
     * description="Operación exitosa",
     * @OA\JsonContent(ref="#/components/schemas/ProductoResource")
     * ),
     * @OA\Response(response=403, description="No autorizado"),
     * @OA\Response(response=404, description="Producto no encontrado")
     * )
     */
    // USANDO ROUTE MODEL BINDING
    public function show(Producto $producto) // <-- Cambio de (int $id) a (Producto $producto)
    {
        // Autorización basada en Spatie: el usuario debe tener el permiso 'productos.ver'
        $this->authorize('productos.ver'); 

        // Si el producto no se encuentra, Laravel ya lanza un 404 automáticamente.
        $producto->load('inventario');

        return new ProductoResource($producto); 
    }

    /**
     * @OA\Put(
     * path="/api/productos/{id}",
     * summary="Actualizar producto",
     * description="Actualiza los detalles de un producto existente por ID. El stock debe actualizarse en el endpoint de Inventario.",
     * tags={"Productos"},
     * security={{"bearer_token":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer"),
     * description="ID del producto a actualizar."
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="nombre_gomita", type="string", example="Oso Gomita Clásico 2.0", description="Nombre del producto, debe ser único."),
     * @OA\Property(property="precio", type="number", format="float", example=6.00, description="Nuevo precio unitario.")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Producto actualizado con éxito.",
     * @OA\JsonContent(ref="#/components/schemas/ProductoResource")
     * ),
     * @OA\Response(response=403, description="No autorizado"),
     * @OA\Response(response=422, description="Error de validación"),
     * @OA\Response(response=404, description="Producto no encontrado")
     * )
     */
    // USANDO ROUTE MODEL BINDING
    public function update(UpdateProductoRequest $request, Producto $producto) // <-- Cambio de (int $id) a (Producto $producto)
    {
        // Autorización basada en Spatie: el usuario debe tener el permiso 'productos.editar'
        $this->authorize('productos.editar');

        // Ya no se necesita el chequeo de 404
        $validatedData = $request->validated(); 

        try {
            $producto->update($validatedData); 
            $producto->load('inventario');

            // Respuesta con código 200 (OK)
            return response()->json(['message' => 'Producto actualizado con éxito.', 'data' => new ProductoResource($producto)], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el producto.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * @OA\Delete(
     * path="/api/productos/{id}",
     * summary="Eliminar producto",
     * description="Elimina un producto por ID. Esto también eliminará su registro de inventario asociado.",
     * tags={"Productos"},
     * security={{"bearer_token":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer"),
     * description="ID del producto a eliminar."
     * ),
     * @OA\Response(
     * response=204,
     * description="Producto eliminado con éxito (Sin Contenido).",
     * ),
     * @OA\Response(response=403, description="No autorizado"),
     * @OA\Response(response=404, description="Producto no encontrado")
     * )
     */
    // USANDO ROUTE MODEL BINDING
    public function destroy(Producto $producto) // <-- Cambio de (int $id) a (Producto $producto)
    {
        // Autorización basada en Spatie: el usuario debe tener el permiso 'productos.eliminar'
        $this->authorize('productos.eliminar');
        
        // Si el producto no se encuentra, Laravel ya lanza un 404 automáticamente.
        try {
            $producto->delete();
            
            // Respuesta con código 204 (No Content) - Estándar para DELETE exitoso.
            return response()->json(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el producto.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}