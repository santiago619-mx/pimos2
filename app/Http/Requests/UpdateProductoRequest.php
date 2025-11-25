<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
// IMPORTACIONES REQUERIDAS (YA CORREGIDAS EN EL PASO ANTERIOR)
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
        // Obtiene el ID del producto que viene de la ruta (URL).
        // Si tu ruta es /api/productos/{producto}, la clave es 'producto'.
        // NOTA: Asumiendo que el parámetro de ruta es 'producto' o que usas 'id'.
        $productoId = $this->route('producto') ?? $this->route('id'); 
        
        return [
            // CORRECCIÓN DE SINTAXIS: Se usan comas para separar los elementos de la matriz de reglas.
            // Los elementos deben ser reglas de validación o instancias de Rule.
            'nombre_gomita' => [
    'sometimes', // Solo se valida si se envía
    'required',  // Si se envía, debe ser obligatorio
    'string',    // <--- ERROR: Esto debería estar concatenado o usar una coma.
    'max:255',   // <--- ERROR: Esto también.
    // unique: ...
    Rule::unique('productos', 'nombre_gomita')->ignore($productoId),
            ],
            // Las reglas concatenadas con pipes (|) no necesitan el array, pero funciona.
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
            'message' => 'Error de validación',
            'errors' => $validator->errors()
        ], 422));
    }
}