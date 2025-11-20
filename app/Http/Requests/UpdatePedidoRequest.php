<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator; 
use Illuminate\Http\Exceptions\HttpResponseException; 

/**
 * Valida los datos al actualizar un Pedido.
 */
class UpdatePedidoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true; 
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     */
    public function rules(): array
    {
        return [
            'estado' => 'sometimes|required|string|in:pendiente,enviado,cancelado,entregado',
            'total' => 'sometimes|required|numeric|min:0.01',
        ];
    }

    /**
     * Método para manejar la falla de validación y devolver una respuesta JSON personalizada (como el ejemplo de tu profesor).
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación en la actualización del pedido',
            'errors' => $validator->errors()
        ], 422));
    }
}