<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // El 'this' hace referencia a la instancia del modelo App\Models\User
        return [
            'id' => $this->id,
            'tipo' => 'user',
            'atributos' => [
                'name' => $this->name,
                'email' => $this->email,
                'email_verificado_en' => $this->email_verified_at,
            ],
            // Los usuarios no tienen relaciones cruciales en este contexto
        ];
    }
}