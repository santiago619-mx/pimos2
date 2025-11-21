<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Pedido;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Access\HandlesAuthorization;

class PedidoPolicy
{
    use HandlesAuthorization;

    /**
     * Permite a los administradores pasar todas las comprobaciones de política.
     * Esto soluciona el fallo en destroy administrator.
     *
     * @param User $user
     * @param string $ability
     * @return bool|null
     */
    public function before(User $user, string $ability): ?bool
    {
        // Si el usuario es administrador, siempre permite la acción.
        // ASUMIMOS que el método hasRole('Administrador') existe en el modelo User
        if (method_exists($user, 'hasRole') && $user->hasRole('Administrador')) {
            return true;
        }

        return null;
    }

    /**
     * Determina si el usuario puede ver cualquier modelo (solo Administrador).
     */
    public function viewAny(User $user): bool 
    { 
        return (method_exists($user, 'hasRole') && $user->hasRole('Administrador'));
    }

    /**
     * Determina si el dueño puede ver su propio pedido. (Corregido: Ya estaba bien).
     */
    public function view(User $user, Pedido $pedido): bool
    {
        return $user->id === $pedido->user_id;
    }

    /**
     * Determina si el usuario puede crear un pedido.
     */
    public function create(User $user): bool 
    { 
        return true; 
    }

    /**
     * Determina si el usuario puede actualizar el modelo (solo Admin para cambios de estado).
     */
    public function update(User $user, Pedido $pedido): bool
    {
        // La lógica de actualización de estado que NO es cancelación solo está permitida al Admin
        // que pasa por el hook 'before'. Si llega aquí, es un usuario normal y no puede actualizar estados.
        return false;
    }
    
    /**
     * Determina si el dueño puede CANCELAR su propio pedido. (Corregido: Ya estaba bien).
     */
    public function cancel(User $user, Pedido $pedido): bool
    {
        return $user->id === $pedido->user_id;
    }


    /**
     * Determina si el usuario puede eliminar el modelo de pedido. (Corregido: Ya estaba bien).
     */
    public function delete(User $user, Pedido $pedido): bool
    {
        // Si es Admin, pasa por 'before'. Si llega aquí, es un usuario normal.
        return $user->id === $pedido->user_id;
    }

    public function restore(User $user, Pedido $pedido): bool { return $user->id === $pedido->user_id; }
    public function forceDelete(User $user, Pedido $pedido): bool { return $user->id === $pedido->user_id; }
}