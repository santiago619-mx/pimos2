<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Contracts\Validation\Validator;  // Importar la interfaz Validator  
use Illuminate\Http\Exceptions\HttpResponseException;  // Importar la excepción HttpResponseException  

class StoreInventarioRequest extends FormRequest
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
            // El producto debe existir en la tabla 'productos' (columna 'id')
            // y debe ser único en la tabla 'inventarios' (columna 'producto_id')
            'producto_id' => 'required|exists:productos,id|unique:inventarios,producto_id',
            
            // La cantidad es obligatoria, debe ser un número entero y no puede ser negativa
            'cantidad_existencias' => 'required|integer|min:0',
        ];
    }
    
    // Manejar la falla de validación y devolver una respuesta JSON personalizada
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación al crear inventario',
            'errors' => $validator->errors()
        ], 422));
    }
}