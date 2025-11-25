<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida los datos al actualizar un Producto (gomita).
 * Se inyecta en el método 'update($request, $id)' del ProductoController.
 */
class UpdateProductoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * @return bool
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
        // Obtiene el ID del producto que viene de la ruta.
        $productoId = $this->route('producto') ?? $this->route('id'); 
        
        return [
            // Corregido: Usa array de reglas. 'nombre_gomita' debe ser único ignorando el ID actual.
            'nombre_gomita' => [
                'sometimes', 
                'required',
                'string',
                'max:255',
                Rule::unique('productos', 'nombre_gomita')->ignore($productoId),
            ],
            // Los campos restantes deben ser opcionales (sometimes) al actualizar
            'sabor' => 'sometimes|required|string|max:255',
            'tamano' => 'sometimes|required|string|max:255',
            'precio' => 'sometimes|required|numeric|min:0.01',
        ];
    }
    
    /**
     * Manejar la falla de validación y devolver una respuesta JSON personalizada
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación al actualizar producto',
            'errors' => $validator->errors()
        ], 422));
    }
}