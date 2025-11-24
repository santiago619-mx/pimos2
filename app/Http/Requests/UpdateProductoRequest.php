<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $productoId = $this->route('producto'); 
        
        return [
            // 'sometimes' asegura que la validación solo se ejecute si el campo está presente en el request.
            'nombre_gomita' => [
                'sometimes', // Solo se valida si se envía
                'required',  // Si se envía, debe ser obligatorio
                'string',
                'max:255',
                // unique: Verifica unicidad, ignorando el ID del producto actual para evitar errores al guardar el mismo nombre.
                Rule::unique('productos', 'nombre_gomita')->ignore($productoId),
            ],
            'sabor' => 'sometimes|required|string|max:255',
            'tamano' => 'sometimes|required|string|max:255',
            'precio' => 'sometimes|required|numeric|min:0.01',
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
