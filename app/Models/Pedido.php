<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    use HasFactory;

    // Especifica la clave primaria (PK)
    protected $primaryKey = 'pedido_id';

    // Campos que pueden ser asignados masivamente
    protected $fillable = [
        'user_id',
        'total',
        'estado',
    ];

    // Relación Inversa: El pedido pertenece a un usuario (cliente).
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relación: Un pedido tiene muchas líneas de detalle.
    public function detallesPedidos(): HasMany
    {
        return $this->hasMany(DetallePedido::class, 'pedido_id');
    }
}