<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Producto;
use Illuminate\Auth\Access\Response;

class ProductoPolicy
{
    /**
     * Helper para verificar si el usuario es Administrador.
     */
    private function isAdmin(User $user): bool
    {
        // NOTA: Debe coincidir con la lógica usada en PedidoPolicy.
        return $user->email === 'admin@gomitas.com';
    }

    /**
     * Determina si el usuario puede ver cualquier producto (listado index).
     */
    public function viewAny(User $user): bool
    {
        // Todos los usuarios autenticados pueden listar productos.
        return true;
    }

    /**
     * Determina si el usuario puede ver un producto específico (show).
     */
    public function view(User $user, Producto $producto): bool
    {
        // Todos los usuarios autenticados pueden ver detalles de un producto.
        return true;
    }

    /**
     * Determina si el usuario puede crear un nuevo producto (store).
     */
    public function create(User $user): bool
    {
        // Solo el administrador puede crear productos.
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede actualizar un producto (update).
     */
    public function update(User $user, Producto $producto): bool
    {
        // Solo el administrador puede actualizar productos.
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede eliminar un producto (destroy).
     */
    public function delete(User $user, Producto $producto): bool
    {
        // Solo el administrador puede eliminar productos.
        return $this->isAdmin($user);
    }
}