<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
// [1] IMPORTACIÓN REQUERIDA: Define la clase Validator correctamente
use Illuminate\Contracts\Validation\Validator;
// [2] IMPORTACIÓN REQUERIDA: Para usar HttpResponseException
use Illuminate\Http\Exceptions\HttpResponseException;
// También incluir las reglas de validación que se usan en las reglas (aunque no es la causa del error)
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
        // NOTA: En este contexto, si la autorización se maneja con Spatie en el Controller,
        // devolver 'true' aquí es común.
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
        // La firma de este método ahora es compatible con el FormRequest de Laravel.
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación',
            'errors' => $validator->errors()
        ], 422));
    }
}