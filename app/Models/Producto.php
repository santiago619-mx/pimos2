<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    // Campos que pueden ser asignados masivamente (Mass Assigned)
    protected $fillable = [
        'nombre_gomita',
        'sabor',
        'tamano',
        'precio',
    ];

    // Relación: Un producto tiene muchas líneas de inventario.
    public function inventario(): HasMany
    {
        return $this->hasMany(Inventario::class, 'producto_id');
    }

    // Relación: Un producto puede estar en muchas líneas de detalles de pedido.
    public function detallesPedidos(): HasMany
    {
        return $this->hasMany(DetallePedido::class, 'producto_id');
    }
}