<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PedidoPolicy
{
    /**
     * Helper para verificar si el usuario es Administrador.
     */
    private function isAdmin(User $user): bool
    {
        // NOTA: Reemplazar con lógica de roles real en producción.
        return $user->email === 'admin@gomitas.com';
    }

    /**
     * Determina si el usuario puede ver la lista de TODOS los pedidos.
     * Mapea a: PedidoController@index
     */
    public function viewAny(User $user): bool
    {
        // Solo los administradores pueden ver la lista completa de pedidos
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede ver un pedido específico.
     * Mapea a: PedidoController@show
     */
    public function view(User $user, Pedido $pedido): bool
    {
        // El admin puede ver cualquier pedido, o el usuario puede ver su PROPIO pedido
        return $this->isAdmin($user) || $user->id === $pedido->user_id;
    }

    /**
     * Determina si el usuario puede crear un pedido.
     * Mapea a: PedidoController@store
     */
    public function create(User $user): bool
    {
        // Cualquier usuario autenticado puede crear un pedido
        return true;
    }

    /**
     * Determina si el usuario puede actualizar un pedido (ej: cambiar estado a 'enviado' o 'entregado').
     * Mapea a: PedidoController@update (para cambios de estado no-cancelación)
     */
    public function update(User $user, Pedido $pedido): bool
    {
        // La actualización de estados (procesamiento) está reservada para el administrador.
        return $this->isAdmin($user);
    }
    
    /**
     * Determina si el usuario puede CANCELAR un pedido (lo que implica reversión de stock).
     * Mapea a: PedidoController@update (para el caso específico de 'cancelado')
     */
    public function cancel(User $user, Pedido $pedido): bool
    {
        // La cancelación con reversión de stock es una acción de alta criticidad, reservada para el administrador.
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede eliminar un pedido.
     * Mapea a: PedidoController@destroy
     */
    public function delete(User $user, Pedido $pedido): bool
    {
        // Se utiliza el mismo permiso de 'cancelar' por la criticidad de la reversión de stock.
        return $this->cancel($user, $pedido);
    }
}