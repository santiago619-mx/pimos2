<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Contracts\Validation\Validator;  // Importar la interfaz Validator  
use Illuminate\Http\Exceptions\HttpResponseException;  // Importar la excepción HttpResponseException  

class UpdateInventarioRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 'sometimes' significa que solo se valida si el campo viene en la petición
            'cantidad_existencias' => 'sometimes|required|integer|min:0',
        ];
    }
    
    // Manejar la falla de validación y devolver una respuesta JSON personalizada
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación al actualizar inventario',
            'errors' => $validator->errors()
        ], 422));
    }
}