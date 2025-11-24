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
        // NOTA: Si necesitas actualizar los detalles, debes añadir las reglas aquí
        // junto con la validación de stock por Closure, similar a StorePedidoRequest.
        return [
            'estado' => 'sometimes|required|string|in:pendiente,enviado,cancelado,entregado',
            'total' => 'sometimes|required|numeric|min:0.01',
        ];
    }

  
    // Manejar la falla de validación y devolver una respuesta JSON personalizada
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación',
            'errors' => $validator->errors()
        ], 422));
    }
}


