<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\PedidoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí es donde puedes registrar rutas API para tu aplicación.
|
*/

// RUTA BASE DE AUTENTICACIÓN (Ruta que viene por defecto)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// 2. RUTAS DE GESTIÓN DE RECURSOS

// ProductoController: Proporciona las 5 rutas RESTful estándar (CRUD completo).
// Genera: index, store, show, update, destroy
Route::apiResource('productos', ProductoController::class);

// InventarioController: Rutas Resource para stock (index, show, update, destroy).
// El InventarioController usa el 'producto_id' como clave en sus métodos.
Route::apiResource('inventario', InventarioController::class)->only([
    'index', // GET /api/inventario
    'show',  // GET /api/inventario/{inventario} -> mapea a producto_id
    'update', // PUT/PATCH /api/inventario/{inventario} -> mapea a producto_id
    'destroy', // DELETE /api/inventario/{inventario} -> mapea a producto_id
]);

// PedidoController: Proporciona las rutas RESTful para Pedidos, excluyendo la eliminación (destroy).
// Genera: index, store, show, update
Route::apiResource('pedidos', PedidoController::class)->except(['destroy']);
