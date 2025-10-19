<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventario extends Model
{
    use HasFactory;

    // Especifica la clave primaria (PK)
    protected $primaryKey = 'inventario_id';

    // Campos que pueden ser asignados masivamente
    protected $fillable = [
        'producto_id',
        'cantidad_existencias',
    ];

    // Relación Inversa: El inventario pertenece a un producto.
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
