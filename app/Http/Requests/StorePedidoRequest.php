<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\CheckStock; // Importar la regla de stock

/**
 * Valida los datos al crear un nuevo Pedido.
 * Asegura que el array 'detalles' esté presente y que cada detalle tenga un producto existente y una cantidad válida.
 */
class StorePedidoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        // La autorización se maneja a nivel de Policy (PedidoPolicy::create)
        return true; 
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     */
    public function rules(): array
    {
        return [
            // Se permite enviar 'user_id' (e.g., por un Admin) pero es opcional,
            // si no se envía, el controlador asignará el usuario autenticado.
            'user_id' => 'nullable|integer|exists:users,id', 

            'estado' => 'nullable|string|in:pendiente,enviado,cancelado,entregado', // El controlador lo establece en 'pendiente' por defecto
            'total' => 'nullable|numeric|min:0', // El controlador lo calcula

            // Reglas para los Detalles del Pedido (Array anidado)
            'detalles' => ['required', 'array', 'min:1'],
            // Reglas para cada elemento dentro del array 'detalles'
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            
            // Regla de validación personalizada: verifica que haya suficiente stock.
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', new CheckStock()],
            
            'detalles.*.precio_unitario' => 'nullable|numeric|min:0.01', 
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'El ID de usuario proporcionado no existe.',
            'detalles.required' => 'El pedido debe contener al menos un producto en el array detalles.',
            'detalles.min' => 'El pedido debe contener al menos un producto.',
            'detalles.*.producto_id.exists' => 'El producto con el ID proporcionado no existe.',
            'detalles.*.cantidad.min' => 'La cantidad solicitada debe ser al menos 1.',
        ];
    }
}