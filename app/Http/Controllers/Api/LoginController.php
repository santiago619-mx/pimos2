<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Se mantiene por si se necesita, aunque no se usa en el método store
use Illuminate\Support\Facades\Hash; // ¡NUEVO! Para verificar contraseñas
use Symfony\Component\HttpFoundation\Response; // ¡NUEVO! Para usar códigos de estado HTTP
use App\Models\User; 
use Illuminate\Validation\ValidationException; // Se mantiene, aunque el ejemplo del profesor no lo usa

class LoginController extends Controller
{
    /**
     * Maneja el intento de inicio de sesión (Login), usando autenticación manual.
     * POST /api/login
     */
    public function store(Request $request)
    {
        // 1. Validar los datos de entrada, usando campos en español
        $request->validate([
            'correo' => 'required|email',
            'contraseña' => 'required',
            'dispositivo' => 'required|string|max:255', // Nombre del dispositivo para el token
        ]);

        // 2. Buscar el usuario por correo electrónico
        $user = User::where('email', $request->correo)->first();
    
        // 3. Verificar si el usuario existe y la contraseña es correcta (manualmente con Hash)
        if (!$user || !Hash::check($request->contraseña, $user->password)) {
            // Devuelve el error 422 (Entidad no procesable) como en el ejemplo del profesor
            return response()->json([
                'message' => 'Las credenciales son incorrectas.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY); 
        }

        // 4. Generar un token de acceso para el usuario, usando el nombre del dispositivo
        $token = $user->createToken($request->dispositivo)->plainTextToken;

        // 5. Devolver la respuesta en el formato deseado
        return response()->json([
            'data' => [
                'attributes' => [
                    'id' => $user->id,
                    'nombre' => $user->name,
                    'correo' => $user->email,
                ],
                'token' => $token,
            ],
        ], Response::HTTP_OK); // 200
    }

    /**
     * Cierra la sesión (Logout), revocando el token actual.
     * POST /api/logout
     */
    public function destroy(Request $request)
    {
        // Revoca solo el token que se usó para hacer la petición actual
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Cierre de sesión exitoso.', // Mensaje ajustado al estilo del profesor
        ], Response::HTTP_OK); // 200
    }
}
