<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Valida los datos al crear un nuevo Producto (gomita).
 * Se inyecta en el método 'store()' del ProductoController.
 */
class StoreProductoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * @return bool
     */
    public function authorize(): bool
    {
        // La autorización se maneja en el controlador con $this->authorize('productos.crear');
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
            // El nombre debe ser obligatorio, cadena, máximo 255 y único en la tabla 'productos'
            'nombre_gomita' => ['required', 'string', 'max:255', Rule::unique('productos', 'nombre_gomita')],
            'sabor' => 'required|string|max:255',
            'tamano' => 'required|string|max:255',
            // El precio es obligatorio, numérico y debe ser mayor que 0.01
            'precio' => 'required|numeric|min:0.01',
            // El stock inicial es opcional (nullable), pero si se envía, debe ser un entero >= 0
            'cantidad_existencias' => 'nullable|integer|min:0', 
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
            'message' => 'Error de validación al crear producto',
            'errors' => $validator->errors()
        ], 422));
    }
}