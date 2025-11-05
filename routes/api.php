<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Importar los controladores de la API
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\PedidoController;
use App\Http\Controllers\Api\LoginController; // ¡NUEVO: Importar el controlador de login!

/*
|--------------------------------------------------------------------------
| Rutas de la API
|--------------------------------------------------------------------------
| Aquí se registran las rutas de la API para la aplicación.
*/

// --- RUTAS PÚBLICAS (NO REQUIEREN AUTENTICACIÓN) ---
Route::post('login', [LoginController::class, 'store']); // Ruta para el inicio de sesión

// --- RUTAS PROTEGIDAS POR AUTENTICACIÓN (REQUIEREN TOKEN) ---
Route::middleware('auth:sanctum')->group(function () { 

    // Ruta de prueba para obtener el usuario autenticado
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Rutas de Recursos (CRUD Completo)
    Route::apiResource('productos', ProductoController::class);
    Route::apiResource('inventario', InventarioController::class);
    Route::apiResource('pedidos', PedidoController::class);
    
    // Ruta para cerrar sesión
    Route::post('logout', [LoginController::class, 'destroy']);
});